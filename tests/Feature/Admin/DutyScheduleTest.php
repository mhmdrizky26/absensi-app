<?php

namespace Tests\Feature\Admin;

use App\Models\AcademicYear;
use App\Models\Classroom;
use App\Models\DutySchedule;
use App\Models\DutySubstitution;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class DutyScheduleTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private User $andi;

    private User $rina;

    protected function setUp(): void
    {
        parent::setUp();

        $this->travelTo(now()->setDate(2026, 11, 16)->setTime(7, 0)); // Senin
        $this->admin = User::factory()->admin()->create();
        $this->andi = User::factory()->guru()->create(['name' => 'Pak Andi']);
        $this->rina = User::factory()->guru()->create(['name' => 'Bu Rina']);
    }

    public function test_admin_saves_the_weekly_schedule(): void
    {
        $this->actingAs($this->admin)
            ->put('/jadwal-piket', ['days' => [1 => [$this->andi->id, $this->rina->id], 3 => [$this->andi->id]]])
            ->assertSessionHas('success', 'Jadwal piket disimpan.');

        $this->assertSame([1, 3], $this->andi->dutySchedules()->orderBy('weekday')->pluck('weekday')->all());
        $this->assertSame([1], $this->rina->dutySchedules()->pluck('weekday')->all());

        $this->actingAs($this->admin)->put('/jadwal-piket', ['days' => [3 => [$this->rina->id]]]);

        $this->assertSame(0, $this->andi->dutySchedules()->count());
        $this->assertSame([3], $this->rina->dutySchedules()->pluck('weekday')->all());

        $this->actingAs($this->admin)->get('/jadwal-piket')->assertInertia(fn (Assert $page) => $page
            ->component('Admin/DutySchedule')
            ->has('weekdays', 5)
            ->where('weekdays.2.label', 'Rabu')
            ->where('weekdays.2.userIds', [$this->rina->id])
            ->has('teachers', 2)
        );
    }

    public function test_only_active_guru_accounts_can_be_scheduled(): void
    {
        foreach ([User::factory()->guruBk()->create(), User::factory()->admin()->create(), User::factory()->guru()->inactive()->create()] as $notAllowed) {
            $this->actingAs($this->admin)->put('/jadwal-piket', ['days' => [1 => [$notAllowed->id]]])->assertSessionHasErrors('days.1.0');
        }

        $this->assertDatabaseCount('duty_schedules', 0);
    }

    public function test_piket_menus_open_only_on_the_scheduled_day(): void
    {
        DutySchedule::factory()->create(['user_id' => $this->andi->id, 'weekday' => 2]);

        $this->actingAs($this->andi)->get('/pantauan')->assertForbidden();
        $this->actingAs($this->andi)->get('/dasbor')->assertInertia(fn (Assert $page) => $page
            ->where('auth.user.isOnDuty', false)
            ->where('auth.user.hasDutySchedule', true)
            ->where('duty.days', ['Selasa'])
            ->where('trends', null)
        );

        $this->travelTo(now()->addDay());

        $this->actingAs($this->andi)->get('/pantauan')->assertOk();
        $this->actingAs($this->andi)->get('/terlambat')->assertOk();
        $this->actingAs($this->andi)->get('/dasbor')->assertInertia(fn (Assert $page) => $page
            ->where('auth.user.isOnDuty', true)
            ->where('duty.today', ['Pak Andi'])
        );
    }

    public function test_a_substitute_takes_over_the_duty_for_one_day(): void
    {
        DutySchedule::factory()->create(['user_id' => $this->andi->id, 'weekday' => 3]);

        $this->actingAs($this->admin)
            ->post('/jadwal-piket/pengganti', ['date' => '2026-11-18', 'absent_user_id' => $this->andi->id, 'substitute_user_id' => $this->rina->id])
            ->assertSessionHasNoErrors()
            ->assertSessionHas('success');

        $this->actingAs($this->rina)->get('/dasbor')->assertInertia(fn (Assert $page) => $page
            ->where('duty.substitutions.0.date', '2026-11-18')
            ->where('duty.substitutions.0.isSubstitute', true)
            ->where('duty.substitutions.0.otherName', 'Pak Andi')
        );

        $this->travelTo(now()->setDate(2026, 11, 18));
        $this->assertTrue($this->rina->isOnDuty());
        $this->assertFalse($this->andi->isOnDuty());
        $this->actingAs($this->rina)->get('/pantauan')->assertOk();
        $this->actingAs($this->andi)->get('/pantauan')->assertForbidden();

        $this->travelTo(now()->setDate(2026, 11, 25));
        $this->assertTrue($this->andi->isOnDuty(), 'Minggu berikutnya jadwal tetap berlaku lagi.');
        $this->assertFalse($this->rina->isOnDuty());
    }

    public function test_substitutions_must_replace_someone_on_duty_with_someone_who_is_not(): void
    {
        DutySchedule::factory()->create(['user_id' => $this->andi->id, 'weekday' => 3]);
        DutySchedule::factory()->create(['user_id' => $this->rina->id, 'weekday' => 3]);
        $dodi = User::factory()->guru()->create(['name' => 'Pak Dodi']);

        $this->actingAs($this->admin)
            ->post('/jadwal-piket/pengganti', ['date' => '2026-11-18', 'absent_user_id' => $dodi->id, 'substitute_user_id' => $this->rina->id])
            ->assertSessionHasErrors(['absent_user_id' => 'Pak Dodi tidak sedang piket pada tanggal itu.', 'substitute_user_id' => 'Bu Rina sudah piket pada tanggal itu.']);

        $this->actingAs($this->admin)
            ->post('/jadwal-piket/pengganti', ['date' => '2026-11-18', 'absent_user_id' => $this->andi->id, 'substitute_user_id' => $this->andi->id])
            ->assertSessionHasErrors('substitute_user_id');

        $this->actingAs($this->admin)
            ->post('/jadwal-piket/pengganti', ['date' => '2026-11-11', 'absent_user_id' => $this->andi->id, 'substitute_user_id' => $dodi->id])
            ->assertSessionHasErrors('date');

        $this->actingAs($this->admin)
            ->post('/jadwal-piket/pengganti', ['date' => '2026-11-21', 'absent_user_id' => $this->andi->id, 'substitute_user_id' => $dodi->id])
            ->assertSessionHasErrors(['date' => 'Jadwal piket hanya Senin sampai Jumat.']);

        $this->assertDatabaseCount('duty_substitutions', 0);
    }

    public function test_removing_a_substitution_restores_the_schedule(): void
    {
        DutySchedule::factory()->create(['user_id' => $this->andi->id, 'weekday' => 1]);
        $substitution = DutySubstitution::factory()->create(['date' => '2026-11-16', 'absent_user_id' => $this->andi->id, 'substitute_user_id' => $this->rina->id]);
        $this->assertFalse($this->andi->isOnDuty());

        $this->actingAs($this->admin)->delete("/jadwal-piket/pengganti/{$substitution->id}")->assertSessionHas('success');

        $this->assertTrue($this->andi->isOnDuty());
        $this->assertFalse($this->rina->isOnDuty());
    }

    public function test_a_wali_kelas_on_duty_gets_both_menus(): void
    {
        DutySchedule::factory()->create(['user_id' => $this->andi->id, 'weekday' => 1]);
        Classroom::factory()->for(AcademicYear::factory()->active())->create(['name' => 'VIII-C', 'homeroom_teacher_id' => $this->andi->id]);

        $this->actingAs($this->andi)->get('/dasbor')->assertInertia(fn (Assert $page) => $page
            ->where('auth.user.homeroom', 'VIII-C')
            ->where('auth.user.isOnDuty', true)
            ->has('trends')
        );
        $this->actingAs($this->andi)->get('/pantauan')->assertOk();
        $this->actingAs($this->andi)->get('/rekap')->assertOk();
        $this->actingAs($this->andi)->get('/izin')->assertOk();
    }

    public function test_only_admins_manage_the_schedule(): void
    {
        $this->actingAs($this->andi)->get('/jadwal-piket')->assertForbidden();
        $this->actingAs($this->andi)->put('/jadwal-piket', ['days' => [1 => [$this->andi->id]]])->assertForbidden();
        $this->actingAs($this->andi)->post('/jadwal-piket/pengganti', [])->assertForbidden();
    }
}
