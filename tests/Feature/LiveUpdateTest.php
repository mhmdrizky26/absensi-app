<?php

namespace Tests\Feature;

use App\Models\AcademicYear;
use App\Models\Attendance;
use App\Models\Classroom;
use App\Models\Dispensation;
use App\Models\Student;
use App\Models\User;
use App\Support\LiveUpdates;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class LiveUpdateTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_version_changes_at_once_when_another_user_changes_data(): void
    {
        $piket = User::factory()->guruPiket()->create();
        $classroom = Classroom::factory()->for(AcademicYear::factory()->active())->create();

        $before = $this->actingAs($piket)->getJson('/pembaruan')->assertOk()->assertJsonPath('counts.pendingDispensations', 0)->json('versions');

        Dispensation::factory()->create(['classroom_id' => $classroom->id]);

        $after = $this->actingAs($piket)->getJson('/pembaruan?data='.$before['data'])->assertJsonPath('counts.pendingDispensations', 1)->json('versions');

        $this->assertNotSame($before['data'], $after['data']);
        $this->assertSame($before['attendance'], $after['attendance']);
    }

    public function test_a_quiet_poll_does_not_count_again(): void
    {
        $admin = User::factory()->admin()->create();

        $versions = $this->actingAs($admin)->getJson('/pembaruan')->json('versions');

        $this->actingAs($admin)->getJson('/pembaruan?data='.$versions['data'])
            ->assertJsonPath('versions', $versions)
            ->assertJsonPath('counts', null);
    }

    public function test_attendance_has_its_own_version_and_bulk_queries_count_too(): void
    {
        $admin = User::factory()->admin()->create();
        $academicYear = AcademicYear::factory()->active()->create();
        $student = Student::factory()->create();
        $student->placeIn(Classroom::factory()->for($academicYear)->create(), $academicYear);
        $attendance = Attendance::factory()->create(['student_id' => $student->id]);

        $before = $this->actingAs($admin)->getJson('/pembaruan')->json('versions');

        DB::table('attendances')->where('id', $attendance->id)->update(['note' => 'Diubah langsung']);

        $after = $this->actingAs($admin)->getJson('/pembaruan')->json('versions');
        $this->assertNotSame($before['attendance'], $after['attendance']);
        $this->assertSame($before['data'], $after['data']);
    }

    public function test_a_change_counts_only_after_its_transaction_commits(): void
    {
        $classroom = Classroom::factory()->for(AcademicYear::factory()->active())->create();
        $before = app(LiveUpdates::class)->versions();

        DB::beginTransaction();
        Dispensation::factory()->create(['classroom_id' => $classroom->id]);
        $this->assertSame($before, app(LiveUpdates::class)->versions());
        DB::commit();

        $this->assertNotSame($before['data'], app(LiveUpdates::class)->versions()['data']);

        $committed = app(LiveUpdates::class)->versions();
        DB::beginTransaction();
        Dispensation::factory()->create(['classroom_id' => $classroom->id]);
        DB::rollBack();

        $this->assertSame($committed, app(LiveUpdates::class)->versions());
    }

    public function test_only_writes_to_watched_tables_count(): void
    {
        $this->assertSame(LiveUpdates::ATTENDANCE, LiveUpdates::topicWrittenBy('insert into `attendances` (`student_id`) values (?)'));
        $this->assertSame(LiveUpdates::ATTENDANCE, LiveUpdates::topicWrittenBy('update "attendance_days" set "absent_count" = ?'));
        $this->assertSame(LiveUpdates::DATA, LiveUpdates::topicWrittenBy('delete from `classroom_student` where `student_id` = ?'));
        $this->assertNull(LiveUpdates::topicWrittenBy('select * from `attendances`'));
        $this->assertNull(LiveUpdates::topicWrittenBy('update `sessions` set `payload` = ?'));
        $this->assertNull(LiveUpdates::topicWrittenBy('insert into `cache` (`key`) values (?)'));
    }

    public function test_lodging_students_changes_the_version_although_no_row_is_added(): void
    {
        $this->travelTo(now()->setDate(2026, 10, 5)->setTime(7, 0));
        $admin = User::factory()->admin()->create();
        $academicYear = AcademicYear::factory()->active()->create();
        Classroom::factory()->for($academicYear)->create(['grade' => 8, 'name' => 'VIII-A']);
        $roomless = Classroom::factory()->for($academicYear)->create(['grade' => 8, 'name' => 'VIII-K', 'has_room' => false]);
        Student::factory()->create()->placeIn($roomless, $academicYear);

        $before = $this->actingAs($admin)->getJson('/pembaruan')->json('versions');

        $this->actingAs($admin)->post("/kelas/{$roomless->id}/titipkan")->assertSessionHas('success');

        $this->assertNotSame($before['data'], $this->actingAs($admin)->getJson('/pembaruan')->json('versions.data'));
    }

    public function test_guests_get_nothing(): void
    {
        $this->getJson('/pembaruan')->assertUnauthorized();
    }
}
