<?php

namespace Tests\Feature\Notification;

use App\Actions\TrackRiskEpisodes;
use App\Enums\AttendanceSource;
use App\Enums\AttendanceStatus;
use App\Jobs\SendRiskMessage;
use App\Models\AcademicYear;
use App\Models\Attendance;
use App\Models\Classroom;
use App\Models\GuardianContact;
use App\Models\RiskEpisode;
use App\Models\RiskMessage;
use App\Models\Setting;
use App\Models\Student;
use App\Models\User;
use App\Support\WhatsAppClient;
use App\Support\WhatsAppSettings;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request as HttpRequest;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class RiskWhatsAppTest extends TestCase
{
    use RefreshDatabase;

    private AcademicYear $academicYear;

    private Classroom $classroom;

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'attendance.school_days' => [1, 2, 3, 4, 5],
            'app.school_name' => 'SMP Contoh',
            'services.fonnte.token' => 'fonnte-token',
        ]);
        Setting::put([WhatsAppSettings::TEST_MODE => '0']);
        Queue::fake();

        $this->academicYear = AcademicYear::factory()->active()->create([
            'odd_semester_starts_on' => '2026-07-13', 'even_semester_starts_on' => '2027-01-04', 'ends_on' => '2027-06-26',
        ]);
        $wali = User::factory()->waliKelas()->create(['name' => 'Bu Dirja']);
        $this->classroom = Classroom::factory()->for($this->academicYear)->create(['name' => 'VII-A', 'homeroom_teacher_id' => $wali->id]);
    }

    private function student(string $name, bool $withContact = true): Student
    {
        $student = Student::factory()->create(['name' => $name]);
        $student->placeIn($this->classroom, $this->academicYear);

        if ($withContact) {
            GuardianContact::factory()->for($student)->create(['phone' => '6281200000'.str_pad((string) $student->id, 3, '0', STR_PAD_LEFT)]);
        }

        return $student;
    }

    /**
     * @param  array<string, string>  $marks  date => status letter
     */
    private function mark(Student $student, array $marks): void
    {
        foreach ($marks as $date => $status) {
            Attendance::query()->updateOrCreate(
                ['student_id' => $student->id, 'date' => $date],
                ['classroom_id' => $this->classroom->id, 'status' => AttendanceStatus::from($status), 'source' => AttendanceSource::Automatic, 'recorded_at' => now()],
            );
        }
    }

    private function runOn(string $date): array
    {
        $this->travelTo(CarbonImmutable::parse($date)->setTime(15, 0));

        return app(TrackRiskEpisodes::class)->handle(today());
    }

    public function test_a_new_risk_is_sent_once_to_consenting_parents_and_not_repeated_while_it_lasts(): void
    {
        $ayu = $this->student('Ayu');
        GuardianContact::factory()->for($ayu)->withoutConsent()->create();
        $this->mark($ayu, ['2026-10-01' => 'A', '2026-10-02' => 'A', '2026-10-05' => 'A']);
        $budi = $this->student('Budi');
        $this->mark($budi, ['2026-10-01' => 'H', '2026-10-02' => 'H', '2026-10-05' => 'H']);

        $summary = $this->runOn('2026-10-06');

        $this->assertSame(['opened' => 1, 'closed' => 0, 'notified' => 1, 'messages' => 1], $summary);
        $message = RiskMessage::sole();
        $this->assertSame([RiskMessage::STATUS_QUEUED, RiskMessage::TRIGGER_NEW, 'tinggi'], [$message->status, $message->trigger, $message->level]);
        $this->assertStringStartsWith('Alpa 3 hari berturut-turut (s.d. 5 Okt).', $message->reason);
        Queue::assertPushed(SendRiskMessage::class, 1);

        // Hari berikutnya Ayu masih berisiko: tidak dikirim lagi.
        $this->mark($ayu, ['2026-10-06' => 'H']);
        $this->runOn('2026-10-07');
        $this->assertSame(1, RiskMessage::count());
        $this->assertNull(RiskEpisode::sole()->ended_on);
    }

    public function test_marks_from_today_are_not_counted_yet(): void
    {
        $ayu = $this->student('Ayu');
        $this->mark($ayu, ['2026-10-01' => 'A', '2026-10-02' => 'A', '2026-10-05' => 'A']);

        $this->runOn('2026-10-05');

        $this->assertSame(0, RiskEpisode::count());
    }

    public function test_leaving_the_list_closes_the_episode_and_coming_back_sends_again(): void
    {
        $ayu = $this->student('Ayu');
        $this->mark($ayu, ['2026-10-01' => 'A', '2026-10-02' => 'A', '2026-10-05' => 'A']);
        $this->runOn('2026-10-06');

        // Wali kelas memasukkan surat sakit: Ayu keluar dari daftar.
        $this->mark($ayu, ['2026-10-01' => 'S', '2026-10-02' => 'S', '2026-10-05' => 'S']);
        $this->assertSame(1, $this->runOn('2026-10-07')['closed']);
        $this->assertSame('2026-10-07', RiskEpisode::sole()->ended_on->toDateString());

        $this->mark($ayu, ['2026-10-12' => 'A', '2026-10-13' => 'A', '2026-10-14' => 'A']);
        $this->runOn('2026-10-15');

        $this->assertSame(2, RiskEpisode::count());
        $this->assertSame([RiskMessage::TRIGGER_NEW, RiskMessage::TRIGGER_NEW], RiskMessage::orderBy('id')->pluck('trigger')->all());
    }

    public function test_rising_to_high_risk_and_a_new_absent_streak_notify_again(): void
    {
        $ayu = $this->student('Ayu');
        $september = [];

        foreach (CarbonImmutable::parse('2026-09-01')->daysUntil('2026-09-30') as $day) {
            if ($day->isWeekday()) {
                $september[$day->toDateString()] = in_array($day->day, [2, 7, 10, 15, 18], true) ? 'A' : 'H';
            }
        }

        $this->mark($ayu, $september);
        $this->runOn('2026-10-01');
        $this->assertSame('perhatian', RiskEpisode::sole()->level);

        $this->mark($ayu, ['2026-10-01' => 'A', '2026-10-02' => 'A', '2026-10-05' => 'A']);
        $this->runOn('2026-10-06');

        $this->mark($ayu, ['2026-10-06' => 'H', '2026-10-07' => 'H', '2026-10-08' => 'H', '2026-10-09' => 'A', '2026-10-12' => 'A', '2026-10-13' => 'A']);
        $this->runOn('2026-10-14');

        $this->runOn('2026-10-15');

        $this->assertSame(1, RiskEpisode::count());
        $this->assertSame('tinggi', RiskEpisode::sole()->level);
        $this->assertSame(
            [RiskMessage::TRIGGER_NEW, RiskMessage::TRIGGER_ESCALATED, RiskMessage::TRIGGER_NEW_STREAK],
            RiskMessage::orderBy('id')->pluck('trigger')->all(),
        );
    }

    public function test_test_mode_only_records_and_the_daily_limit_holds_the_rest(): void
    {
        foreach (['Ayu', 'Budi'] as $name) {
            $this->mark($this->student($name), ['2026-10-01' => 'A', '2026-10-02' => 'A', '2026-10-05' => 'A']);
        }

        Setting::put([WhatsAppSettings::TEST_MODE => '1']);
        $this->runOn('2026-10-06');
        $this->assertSame([RiskMessage::STATUS_TEST, RiskMessage::STATUS_TEST], RiskMessage::pluck('status')->all());
        Queue::assertNothingPushed();

        RiskEpisode::query()->delete();
        Setting::put([WhatsAppSettings::TEST_MODE => '0', WhatsAppSettings::DAILY_LIMIT => '1']);
        $this->runOn('2026-10-06');

        $this->assertEqualsCanonicalizing([RiskMessage::STATUS_QUEUED, RiskMessage::STATUS_LIMITED], RiskMessage::pluck('status')->all());
        Queue::assertPushed(SendRiskMessage::class, 1);
    }

    public function test_a_student_without_a_consenting_number_is_logged_as_no_contact(): void
    {
        $ayu = $this->student('Ayu', withContact: false);
        $this->mark($ayu, ['2026-10-01' => 'A', '2026-10-02' => 'A', '2026-10-05' => 'A']);

        $this->runOn('2026-10-06');

        $message = RiskMessage::sole();
        $this->assertSame(RiskMessage::STATUS_NO_CONTACT, $message->status);
        $this->assertNull($message->phone);
    }

    public function test_the_job_sends_the_risk_text_through_fonnte(): void
    {
        Http::fake(['api.fonnte.com/*' => Http::response(['status' => true, 'id' => ['80367170'], 'process' => 'pending'])]);
        $ayu = $this->student('Ayu');
        $this->mark($ayu, ['2026-10-01' => 'A', '2026-10-02' => 'A', '2026-10-05' => 'A']);
        $this->runOn('2026-10-06');

        (new SendRiskMessage(RiskMessage::sole()))->handle(app(WhatsAppClient::class));

        Http::assertSent(fn (HttpRequest $request): bool => $request->url() === 'https://api.fonnte.com/send'
            && $request->hasHeader('Authorization', 'fonnte-token')
            && $request['target'] === $ayu->guardianContacts()->value('phone')
            && str_starts_with($request['message'], 'Yth. Bapak/Ibu wali dari Ayu (kelas VII-A). Kehadiran Ananda memerlukan perhatian segera: Alpa 3 hari')
            && str_contains($request['message'], 'menghubungi wali kelas, Bu Dirja. Pesan otomatis dari SMP Contoh'));
        $message = RiskMessage::sole();
        $this->assertSame([RiskMessage::STATUS_SENT, '80367170'], [$message->status, $message->provider_message_id]);
        $this->assertNotNull($message->sent_at);
    }

    public function test_a_rejected_message_keeps_fonntes_reason(): void
    {
        Http::fake(['api.fonnte.com/*' => Http::response(['status' => false, 'reason' => 'device disconnected'])]);
        $ayu = $this->student('Ayu');
        $this->mark($ayu, ['2026-10-01' => 'A', '2026-10-02' => 'A', '2026-10-05' => 'A']);
        $this->runOn('2026-10-06');

        (new SendRiskMessage(RiskMessage::sole()))->handle(app(WhatsAppClient::class));

        $message = RiskMessage::sole();
        $this->assertSame(RiskMessage::STATUS_FAILED, $message->status);
        $this->assertSame('Fonnte menolak (HTTP 200): device disconnected', $message->error);
    }

    public function test_the_scheduled_command_runs_once_per_school_day_from_the_send_time(): void
    {
        $ayu = $this->student('Ayu');
        $this->mark($ayu, ['2026-10-01' => 'A', '2026-10-02' => 'A', '2026-10-05' => 'A']);
        Setting::put([WhatsAppSettings::ENABLED => '1', WhatsAppSettings::SEND_AT => '15:00']);

        $this->travelTo(CarbonImmutable::parse('2026-10-06 14:59'));
        $this->artisan('risiko:kirim-wa')->assertSuccessful();
        $this->assertSame(0, RiskEpisode::count());

        $this->travelTo(CarbonImmutable::parse('2026-10-06 15:00'));
        $this->artisan('risiko:kirim-wa')->assertSuccessful();
        $this->assertSame(1, RiskMessage::count());

        RiskEpisode::query()->delete();
        $this->travelTo(CarbonImmutable::parse('2026-10-06 16:00'));
        $this->artisan('risiko:kirim-wa')->assertSuccessful();
        $this->assertSame(0, RiskMessage::count());

        // Sabtu bukan hari sekolah.
        $this->travelTo(CarbonImmutable::parse('2026-10-10 15:30'));
        $this->artisan('risiko:kirim-wa')->assertSuccessful();
        $this->assertSame(0, RiskEpisode::count());
    }

    public function test_switching_semester_closes_old_episodes(): void
    {
        $ayu = $this->student('Ayu');
        RiskEpisode::factory()->for($ayu)->create(['academic_year_id' => $this->academicYear->id, 'semester' => 'ganjil']);

        $this->runOn('2027-01-05');

        $this->assertNotNull(RiskEpisode::sole()->ended_on);
    }

    public function test_admin_manages_settings_sends_a_test_message_and_resends(): void
    {
        Http::fake(['api.fonnte.com/*' => Http::response(['status' => true, 'id' => ['1']])]);
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)->get('/notifikasi-wa')->assertOk()->assertInertia(fn (Assert $page) => $page
            ->component('Admin/WhatsApp')
            ->where('connection.configured', true)
            ->where('settings.test_mode', false)
        );

        $this->actingAs($admin)->put('/notifikasi-wa', [
            'enabled' => true, 'test_mode' => true, 'send_at' => '14:30', 'daily_limit' => 20, 'on_escalation' => true, 'on_new_streak' => false,
        ])->assertSessionHasNoErrors();
        $settings = app(WhatsAppSettings::class);
        $this->assertSame([true, true, '14:30', 20, false], [$settings->enabled(), $settings->testMode(), $settings->sendAt(), $settings->dailyLimit(), $settings->notifyOnNewStreak()]);

        $this->actingAs($admin)->post('/notifikasi-wa/uji', ['phone' => '0822-1234-5678'])->assertSessionHas('success');
        Http::assertSent(fn (HttpRequest $request): bool => $request['target'] === '6282212345678' && str_starts_with($request['message'], 'Pesan uji dari aplikasi absensi'));
        $this->actingAs($admin)->post('/notifikasi-wa/uji', ['phone' => '12345'])->assertSessionHasErrors('phone');

        $ayu = $this->student('Ayu');
        $this->mark($ayu, ['2026-10-01' => 'A', '2026-10-02' => 'A', '2026-10-05' => 'A']);
        $this->runOn('2026-10-06');
        $message = RiskMessage::sole();
        $this->assertSame(RiskMessage::STATUS_TEST, $message->status);

        $this->actingAs($admin)->post("/notifikasi-wa/pesan/{$message->id}/kirim-ulang")->assertSessionHas('success');
        $this->assertSame(RiskMessage::STATUS_QUEUED, $message->fresh()->status);
        Queue::assertPushed(SendRiskMessage::class);

        $this->actingAs(User::factory()->waliKelas()->create())->get('/notifikasi-wa')->assertForbidden();
    }

    public function test_early_warning_cards_show_whether_parents_were_told(): void
    {
        $ayu = $this->student('Ayu');
        $this->mark($ayu, ['2026-10-01' => 'A', '2026-10-02' => 'A', '2026-10-05' => 'A']);
        $this->runOn('2026-10-06');

        $this->actingAs(User::factory()->guruBk()->create())->get('/siswa-berisiko')->assertInertia(fn (Assert $page) => $page
            ->where('students.0.name', 'Ayu')
            ->where('students.0.notice.statuses', [RiskMessage::STATUS_QUEUED])
        );
    }
}
