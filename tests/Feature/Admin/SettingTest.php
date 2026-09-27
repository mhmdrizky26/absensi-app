<?php

namespace Tests\Feature\Admin;

use App\Models\AcademicYear;
use App\Models\Classroom;
use App\Models\Holiday;
use App\Models\User;
use App\Support\AttendanceWindow;
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

    public function test_admin_changes_scan_hours_and_school_days(): void
    {
        $this->actingAs($this->admin)
            ->put('/pengaturan', ['opens_at' => '06:15', 'closes_at' => '07:45', 'school_days' => [1, 2, 3, 4, 5, 6]])
            ->assertSessionHasNoErrors();

        $window = app(AttendanceWindow::class);
        $this->assertSame('06:15', $window->opensAtTime());
        $this->assertSame('07:45', $window->closesAtTime());
        $this->assertSame([1, 2, 3, 4, 5, 6], $window->schoolDays());
        $this->assertTrue($window->isSchoolDay(now()->setDate(2026, 10, 3))); // Sabtu
    }

    public function test_closing_time_must_be_after_opening_time(): void
    {
        $this->actingAs($this->admin)
            ->put('/pengaturan', ['opens_at' => '08:00', 'closes_at' => '07:00', 'school_days' => [1]])
            ->assertSessionHasErrors('closes_at');
    }

    public function test_admin_adds_a_holiday_range_and_class_scan_stays_shut(): void
    {
        $this->actingAs($this->admin)
            ->post('/libur', ['from' => '2026-12-21', 'to' => '2026-12-23', 'description' => 'Libur semester'])
            ->assertSessionHas('success', '3 hari libur ditambahkan.');

        $this->travelTo(now()->setDate(2026, 12, 22)->setTime(7, 0)); // Selasa
        $classroom = Classroom::factory()->for(AcademicYear::factory()->active())->create();
        auth()->logout();

        $this->post('/absen', ['code' => $classroom->access_code])
            ->assertSessionHasErrors(['code' => 'Hari ini libur (Libur semester), jadi absensi kelas tidak dibuka.']);
    }

    public function test_admin_removes_a_holiday(): void
    {
        $holiday = Holiday::factory()->create();

        $this->actingAs($this->admin)->delete("/libur/{$holiday->id}")->assertSessionHas('success');

        $this->assertModelMissing($holiday);
    }
}
