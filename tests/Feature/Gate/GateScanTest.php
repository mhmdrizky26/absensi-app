<?php

namespace Tests\Feature\Gate;

use App\Enums\AttendanceSource;
use App\Enums\AttendanceStatus;
use App\Enums\StudentStatus;
use App\Http\Middleware\EnsureGateStation;
use App\Models\AcademicYear;
use App\Models\Attendance;
use App\Models\AttendanceDay;
use App\Models\Classroom;
use App\Models\GateStation;
use App\Models\Student;
use App\Support\GateNetwork;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class GateScanTest extends TestCase
{
    use RefreshDatabase;

    private AcademicYear $academicYear;

    private Classroom $classroom;

    private GateStation $station;

    protected function setUp(): void
    {
        parent::setUp();

        // Senin, 28 September 2026 pukul 06:45: gerbang buka 06:30–07:00.
        $this->travelTo(now()->setDate(2026, 9, 28)->setTime(6, 45));
        config([
            'attendance.enforce_window' => true,
            'attendance.opens_at' => '06:30',
            'attendance.closes_at' => '07:00',
            'attendance.school_days' => [1, 2, 3, 4, 5],
        ]);

        $this->academicYear = AcademicYear::factory()->active()->create();
        $this->classroom = Classroom::factory()->for($this->academicYear)->create(['name' => 'VII-A']);
        $this->station = GateStation::factory()->create(['name' => 'Tab 1', 'code' => 'TAB1']);
    }

    private function student(?Classroom $classroom = null, array $attributes = []): Student
    {
        $classroom ??= $this->classroom;
        $student = Student::factory()->create($attributes);
        $student->placeIn($classroom, $this->academicYear);

        return $student;
    }

    private function asTablet(?GateStation $station = null): static
    {
        $station ??= $this->station;

        return $this->withCredentials()->withCookie(EnsureGateStation::COOKIE, "{$station->id}|{$station->login_token}");
    }

    /**
     * @param  list<array<string, mixed>>  $scans
     * @return list<array<string, mixed>>
     */
    private function sendScans(array $scans, ?GateStation $station = null): array
    {
        return $this->asTablet($station)->postJson('/gerbang/scan', ['scans' => $scans])->assertOk()->json('results');
    }

    public function test_tablet_logs_in_once_with_its_code(): void
    {
        $this->post('/kode', ['code' => ' tab1 '])
            ->assertRedirect(route('gate.scanner'))
            ->assertCookie(EnsureGateStation::COOKIE, "{$this->station->id}|{$this->station->login_token}");

        $this->asTablet()->get('/kode')->assertRedirect(route('gate.scanner'));
        $this->assertNotNull($this->station->fresh()->last_seen_at);
    }

    public function test_home_page_sends_a_logged_in_tablet_to_its_scanner_and_guests_to_staff_login(): void
    {
        $this->get('/')->assertRedirect(route('login'));
        $this->asTablet()->get('/')->assertRedirect(route('gate.scanner'));
    }

    public function test_wrong_or_revoked_code_does_not_log_in(): void
    {
        $this->post('/kode', ['code' => 'TAB9'])->assertSessionHasErrors('code');

        $this->station->revoke();
        $this->post('/kode', ['code' => 'TAB1'])->assertSessionHasErrors(['code' => 'Tab 1 sedang dicabut. Minta admin mengaktifkannya lagi.']);
    }

    public function test_revoking_or_changing_the_code_logs_the_tablet_out(): void
    {
        $cookie = "{$this->station->id}|{$this->station->login_token}";
        $this->station->rotateLoginToken();

        $this->withCookie(EnsureGateStation::COOKIE, $cookie)->get('/gerbang/scan')->assertRedirect(route('code.login'));
        $this->withCredentials()->withCookie(EnsureGateStation::COOKIE, $cookie)->postJson('/gerbang/scan', ['scans' => []])->assertUnauthorized();

        $this->asTablet()->get('/gerbang/scan')->assertOk();
        $this->station->revoke();
        $this->asTablet()->get('/gerbang/scan')->assertRedirect(route('code.login'));
    }

    public function test_school_network_lock_blocks_other_networks(): void
    {
        app(GateNetwork::class)->save(['203.0.113.7']);

        $this->post('/kode', ['code' => 'TAB1'])->assertSessionHasErrors(['code' => 'Tab gerbang hanya bisa dipakai dari jaringan WiFi sekolah.']);
        $this->asTablet()->getJson('/gerbang/scan')->assertUnauthorized();

        $this->withServerVariables(['REMOTE_ADDR' => '203.0.113.7']);
        $this->asTablet()->get('/gerbang/scan')->assertOk();
    }

    public function test_scanner_caches_every_active_student_with_class_and_study_room(): void
    {
        $roomless = Classroom::factory()->for($this->academicYear)->create(['name' => 'VIII-K', 'grade' => 8, 'has_room' => false]);
        $ayu = $this->student(attributes: ['name' => 'Ayu']);
        $budi = $this->student($roomless, ['name' => 'Budi']);
        DB::table('classroom_student')->where('student_id', $budi->id)->update(['study_classroom_id' => $this->classroom->id]);
        $this->student(attributes: ['name' => 'Cici', 'status' => StudentStatus::Graduated]);
        Attendance::factory()->create(['student_id' => $ayu->id, 'classroom_id' => $this->classroom->id, 'date' => '2026-09-28', 'status' => AttendanceStatus::Present]);

        $this->asTablet()->get('/gerbang/scan')->assertInertia(fn (Assert $page) => $page
            ->component('Gate/Scanner')
            ->where('station.name', 'Tab 1')
            ->has('roster', 2)
            ->where('roster.0', ['id' => $ayu->id, 'name' => 'Ayu', 'nis' => $ayu->nis, 'hash' => $ayu->qrHash(), 'classroom' => 'VII-A', 'studyIn' => null])
            ->where('roster.1.classroom', 'VIII-K')
            ->where('roster.1.studyIn', 'VII-A')
            ->where("marks.{$ayu->id}.status", 'H')
            ->where('today.closesAt', now()->setTime(7, 0)->toIso8601String())
            ->where('today.isFinalized', false)
        );
    }

    public function test_scan_before_jam_masuk_is_hadir_for_the_students_own_class(): void
    {
        $roomless = Classroom::factory()->for($this->academicYear)->create(['name' => 'VIII-K', 'has_room' => false]);
        $budi = $this->student($roomless);
        DB::table('classroom_student')->where('student_id', $budi->id)->update(['study_classroom_id' => $this->classroom->id]);

        $results = $this->sendScans([['ref' => 'a', 'payload' => $budi->qrPayload(), 'scanned_at' => now()->subMinutes(5)->toIso8601String()]]);

        $this->assertSame('recorded', $results[0]['result']);
        $attendance = Attendance::sole();
        $this->assertSame(AttendanceStatus::Present, $attendance->status);
        $this->assertSame(AttendanceSource::GateScan, $attendance->source);
        $this->assertSame($roomless->id, $attendance->classroom_id);
        $this->assertSame($this->station->id, $attendance->gate_station_id);
        $this->assertSame('06:40', $attendance->recorded_at->format('H:i'));
    }

    public function test_second_scan_from_another_tablet_is_a_duplicate(): void
    {
        $ayu = $this->student();
        $tab2 = GateStation::factory()->create(['code' => 'TAB2']);

        $this->sendScans([['ref' => 'a', 'payload' => $ayu->qrPayload()]]);
        $results = $this->sendScans([['ref' => 'b', 'payload' => $ayu->qrPayload()]], $tab2);

        $this->assertSame('duplicate', $results[0]['result']);
        $this->assertSame('H', $results[0]['status']);
        $this->assertSame(1, Attendance::count());
    }

    public function test_scan_after_jam_masuk_is_rejected_but_an_offline_scan_from_before_counts(): void
    {
        $ayu = $this->student();
        $budi = $this->student();
        $this->travelTo(now()->setTime(7, 10));

        $results = $this->sendScans([
            ['ref' => 'late', 'payload' => $ayu->qrPayload(), 'scanned_at' => now()->toIso8601String()],
            ['ref' => 'offline', 'payload' => $budi->qrPayload(), 'scanned_at' => now()->setTime(6, 55)->toIso8601String()],
        ]);

        $this->assertSame(['rejected', 'Gerbang sudah ditutup pukul 07:00. Silakan lapor ke guru piket.'], [$results[0]['result'], $results[0]['message']]);
        $this->assertSame('recorded', $results[1]['result']);
        $this->assertSame([$budi->id], Attendance::pluck('student_id')->all());
    }

    public function test_scans_are_refused_on_days_without_school_after_finishing_and_for_yesterdays_queue(): void
    {
        $ayu = $this->student();

        $results = $this->sendScans([['ref' => 'old', 'payload' => $ayu->qrPayload(), 'scanned_at' => now()->subDay()->toIso8601String()]]);
        $this->assertSame('rejected', $results[0]['result']);

        $this->travelTo(now()->setDate(2026, 9, 27)->setTime(6, 45)); // Minggu
        $this->asTablet()->postJson('/gerbang/scan', ['scans' => [['ref' => 'sunday', 'payload' => $ayu->qrPayload()]]])->assertUnauthorized();

        $this->travelTo(now()->setDate(2026, 9, 28)->setTime(6, 45));
        AttendanceDay::create(['date' => '2026-09-28', 'finalized_at' => now(), 'absent_count' => 0]);
        $this->asTablet()->postJson('/gerbang/scan', ['scans' => [['ref' => 'done', 'payload' => $ayu->qrPayload()]]])->assertUnauthorized();
        $this->assertSame(0, Attendance::count());
    }

    public function test_after_jam_masuk_a_tab_cannot_log_in_and_sees_scan_closed(): void
    {
        $this->travelTo(now()->setTime(7, 0));

        $this->from('/kode')->post('/kode', ['code' => 'TAB1'])
            ->assertRedirect('/kode')
            ->assertSessionHas('gateClosed', 'Scan gerbang sudah ditutup pukul 07:00. Siswa yang datang terlambat atau lupa kartu melapor ke guru piket.')
            ->assertCookieMissing(EnsureGateStation::COOKIE);
    }

    public function test_a_logged_in_tab_is_logged_out_when_jam_masuk_passes(): void
    {
        $this->asTablet()->get('/gerbang/scan')->assertOk();
        $this->travelTo(now()->setTime(7, 0));

        $this->asTablet()->get('/gerbang/scan')
            ->assertRedirect(route('code.login'))
            ->assertSessionHas('gateClosed')
            ->assertCookieExpired(EnsureGateStation::COOKIE);
    }

    public function test_queued_scans_may_still_be_sent_for_30_minutes_after_jam_masuk(): void
    {
        $ayu = $this->student();
        $this->travelTo(now()->setTime(7, 25));

        $results = $this->sendScans([['ref' => 'a', 'payload' => $ayu->qrPayload(), 'scanned_at' => now()->setTime(6, 58)->toIso8601String()]]);
        $this->assertSame('recorded', $results[0]['result']);
        $this->asTablet()->postJson('/gerbang/status', ['pending' => 0])->assertOk();

        $this->travelTo(now()->setTime(7, 30));
        $this->asTablet()->postJson('/gerbang/status', ['pending' => 0])->assertUnauthorized();
    }

    public function test_in_demo_mode_the_tab_stays_open_until_the_day_is_finished(): void
    {
        config(['attendance.enforce_window' => false]);
        $this->travelTo(now()->setDate(2026, 9, 27)->setTime(20, 0)); // Minggu malam

        $this->post('/kode', ['code' => 'TAB1'])->assertRedirect(route('gate.scanner'));
        $this->asTablet()->get('/gerbang/scan')->assertOk();

        AttendanceDay::create(['date' => '2026-09-27', 'finalized_at' => now(), 'absent_count' => 0]);

        $this->asTablet()->get('/gerbang/scan')->assertRedirect(route('code.login'))->assertSessionHas('gateClosed');
        $this->from('/kode')->post('/kode', ['code' => 'TAB1'])->assertSessionHas('gateClosed', 'Absen hari ini sudah diselesaikan guru piket, jadi tab gerbang ditutup sampai besok.');
    }

    public function test_revoked_cards_and_inactive_students_are_rejected(): void
    {
        $revoked = $this->student();
        $payload = $revoked->qrPayload();
        $revoked->forceFill(['qr_revoked_at' => now()])->save();
        $graduated = $this->student(attributes: ['status' => StudentStatus::Graduated]);

        $results = $this->sendScans([
            ['ref' => 'a', 'payload' => $payload],
            ['ref' => 'b', 'payload' => $graduated->qrPayload()],
        ]);

        $this->assertSame('Kartu tidak dikenal atau sudah dicabut.', $results[0]['message']);
        $this->assertSame('rejected', $results[1]['result']);
        $this->assertSame(0, Attendance::count());
    }

    public function test_a_student_marked_sakit_who_shows_up_becomes_hadir(): void
    {
        $ayu = $this->student();
        Attendance::factory()->create(['student_id' => $ayu->id, 'classroom_id' => $this->classroom->id, 'date' => '2026-09-28', 'status' => AttendanceStatus::Sick]);

        $this->sendScans([['ref' => 'a', 'payload' => $ayu->qrPayload()]]);

        $attendance = Attendance::sole();
        $this->assertSame(AttendanceStatus::Present, $attendance->status);
        $this->assertSame('Sebelumnya Sakit, ternyata datang ke sekolah.', $attendance->note);
    }

    public function test_tablet_reports_its_queue_every_minute(): void
    {
        $this->asTablet()->postJson('/gerbang/status', ['pending' => 4])
            ->assertOk()
            ->assertJsonPath('today.date', '2026-09-28')
            ->assertJsonPath('today.isSchoolDay', true);

        $this->assertSame(4, $this->station->fresh()->pending_scans);
        $this->assertTrue($this->station->fresh()->isOnline());
    }
}
