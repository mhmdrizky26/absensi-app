<?php

namespace Tests\Feature\Admin;

use App\Models\Holiday;
use App\Models\Setting;
use App\Models\User;
use App\Support\AttendanceWindow;
use App\Support\WhatsAppClient;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SettingTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = User::factory()->admin()->create();
        config(['attendance.enforce_window' => true]);
    }

    public function test_only_admins_change_settings(): void
    {
        $this->actingAs(User::factory()->guruPiket()->create())->get('/pengaturan')->assertForbidden();
    }

    /**
     * @param  array<int, array{0: string, 1: string}>  $schoolDays  weekday => [opens_at, closes_at]
     * @return list<array{day: int, active: bool, opens_at: string, closes_at: string}>
     */
    private function week(array $schoolDays): array
    {
        return array_map(fn (int $day): array => [
            'day' => $day,
            'active' => isset($schoolDays[$day]),
            'opens_at' => $schoolDays[$day][0] ?? '06:30',
            'closes_at' => $schoolDays[$day][1] ?? '07:00',
        ], range(1, 7));
    }

    public function test_admin_sets_gate_hours_per_day(): void
    {
        $this->actingAs($this->admin)
            ->put('/pengaturan', ['days' => $this->week([1 => ['06:00', '07:00'], 2 => ['06:00', '07:00'], 5 => ['06:00', '06:45'], 6 => ['06:30', '07:30']])])
            ->assertSessionHasNoErrors();

        $window = app(AttendanceWindow::class);
        $this->assertSame([1, 2, 5, 6], $window->schoolDays());
        $this->assertSame('06:45', $window->closesAt(now()->setDate(2026, 10, 2))->format('H:i')); // Jumat
        $this->assertSame('06:30', $window->opensAt(now()->setDate(2026, 10, 3))->format('H:i')); // Sabtu
        $this->assertTrue($window->isSchoolDay(now()->setDate(2026, 10, 3)));
        $this->assertFalse($window->isSchoolDay(now()->setDate(2026, 9, 30))); // Rabu

        $this->actingAs($this->admin)->get('/pengaturan')->assertInertia(fn ($page) => $page
            ->where('settings.days.4', ['day' => 5, 'label' => 'Jumat', 'active' => true, 'opens_at' => '06:00', 'closes_at' => '06:45'])
            ->where('settings.days.2.active', false)
        );
    }

    public function test_schedule_falls_back_to_the_old_school_days_setting(): void
    {
        config(['attendance.school_days' => [1, 2, 3], 'attendance.opens_at' => '06:15', 'attendance.closes_at' => '07:15']);

        $window = app(AttendanceWindow::class);
        $this->assertSame([1, 2, 3], $window->schoolDays());
        $this->assertSame('07:15', $window->closesAt(now()->setDate(2026, 9, 28))->format('H:i'));
    }

    public function test_jam_masuk_must_be_after_opening_time_and_one_day_is_required(): void
    {
        $this->actingAs($this->admin)
            ->put('/pengaturan', ['days' => $this->week([1 => ['08:00', '07:00']])])
            ->assertSessionHasErrors('days.0.closes_at');

        $this->actingAs($this->admin)
            ->put('/pengaturan', ['days' => $this->week([])])
            ->assertSessionHasErrors('days');

        $this->actingAs($this->admin)
            ->put('/pengaturan', ['days' => $this->week([3 => ['', '07:00']])])
            ->assertSessionHasErrors('days.2.opens_at');
    }

    public function test_admin_adds_a_holiday_range_and_the_gate_stays_shut(): void
    {
        $this->actingAs($this->admin)
            ->post('/libur', ['from' => '2026-12-21', 'to' => '2026-12-23', 'description' => 'Libur semester'])
            ->assertSessionHas('success', '3 hari libur ditambahkan.');

        $this->travelTo(now()->setDate(2026, 12, 22)->setTime(7, 0)); // Selasa
        $window = app(AttendanceWindow::class);

        $this->assertFalse($window->isSchoolDay(now()));
        $this->assertSame('Hari ini libur (Libur semester), jadi tab gerbang tidak dibuka.', $window->gateClosedReason(now()));
    }

    public function test_admin_sets_school_name_demo_mode_and_finish_time_without_touching_env(): void
    {
        $this->actingAs($this->admin)
            ->put('/pengaturan/umum', ['app_name' => 'SiHadir', 'school_name' => 'SMP Negeri 1 Contoh', 'timezone' => 'Asia/Makassar', 'demo_mode' => true, 'finalize_at' => '21:30'])
            ->assertSessionHas('success');

        $this->assertSame('SMP Negeri 1 Contoh', config('app.school_name'));
        $this->assertSame('SiHadir', config('app.name'));
        $this->assertSame('Asia/Makassar', config('app.timezone'));
        $this->assertSame('Asia/Makassar', date_default_timezone_get());
        $this->assertSame('Asia/Makassar', now()->getTimezone()->getName());
        $this->assertSame('21:30', config('attendance.finalize_at'));
        $this->assertFalse(app(AttendanceWindow::class)->isEnforced());

        $this->actingAs($this->admin)->get('/pengaturan')->assertInertia(fn ($page) => $page
            ->where('schoolName', 'SMP Negeri 1 Contoh')
            ->where('demoMode', true)
            ->where('appName', 'SiHadir')
            ->where('general', ['app_name' => 'SiHadir', 'school_name' => 'SMP Negeri 1 Contoh', 'timezone' => 'Asia/Makassar', 'demo_mode' => true, 'finalize_at' => '21:30'])
        );

        $this->actingAs($this->admin)->put('/pengaturan/umum', ['app_name' => 'SiHadir', 'school_name' => 'SMP Negeri 1 Contoh', 'timezone' => 'Asia/Jakarta', 'demo_mode' => false, 'finalize_at' => '21:30']);
        $this->assertTrue(app(AttendanceWindow::class)->isEnforced());

        config(['attendance.enforce_window' => false]);
        Setting::applyToConfig();
        $this->assertTrue(config('attendance.enforce_window'), 'Pilihan admin menang atas nilai .env.');
    }

    public function test_general_settings_are_validated_and_admin_only(): void
    {
        $this->actingAs($this->admin)
            ->put('/pengaturan/umum', ['app_name' => '', 'school_name' => '', 'timezone' => 'Europe/London', 'demo_mode' => false, 'finalize_at' => '25:00'])
            ->assertSessionHasErrors(['app_name', 'school_name', 'timezone', 'finalize_at']);

        $this->actingAs(User::factory()->guruPiket()->create())->put('/pengaturan/umum', [])->assertForbidden();
    }

    public function test_admin_stores_the_fonnte_token_encrypted(): void
    {
        $this->actingAs($this->admin)->put('/notifikasi-wa/token', ['token' => ' rahasia-123 '])->assertSessionHas('success');

        $this->assertSame('rahasia-123', config('services.fonnte.token'));
        $this->assertTrue(app(WhatsAppClient::class)->isConfigured());
        $this->assertNotSame('rahasia-123', Setting::query()->find(Setting::FONNTE_TOKEN)->value);

        $this->actingAs($this->admin)->get('/notifikasi-wa')->assertInertia(fn ($page) => $page->where('connection.configured', true));

        $this->actingAs($this->admin)->put('/notifikasi-wa/token', ['token' => ''])->assertSessionHas('success');
        $this->assertNull(config('services.fonnte.token'));
    }

    public function test_admin_removes_a_holiday(): void
    {
        $holiday = Holiday::factory()->create();

        $this->actingAs($this->admin)->delete("/libur/{$holiday->id}")->assertSessionHas('success');

        $this->assertModelMissing($holiday);
    }
}
