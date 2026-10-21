<?php

namespace Tests\Feature;

use App\Models\AcademicYear;
use App\Models\Classroom;
use App\Models\Dispensation;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

class LiveUpdateTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_version_changes_when_another_user_changes_data(): void
    {
        $piket = User::factory()->guruPiket()->create();
        $classroom = Classroom::factory()->for(AcademicYear::factory()->active())->create();

        $before = $this->actingAs($piket)->getJson('/pembaruan')->assertOk()->assertJsonPath('counts.pendingDispensations', 0)->json('version');

        Dispensation::factory()->create(['classroom_id' => $classroom->id]);
        Cache::forget('live-updates-version');

        $after = $this->actingAs($piket)->getJson('/pembaruan')->assertJsonPath('counts.pendingDispensations', 1)->json('version');

        $this->assertNotSame($before, $after);
    }

    public function test_the_version_is_cached_briefly(): void
    {
        $admin = User::factory()->admin()->create();
        $classroom = Classroom::factory()->for(AcademicYear::factory()->active())->create();

        $first = $this->actingAs($admin)->getJson('/pembaruan')->json('version');
        Dispensation::factory()->create(['classroom_id' => $classroom->id]);

        $this->assertSame($first, $this->actingAs($admin)->getJson('/pembaruan')->json('version'));
        $this->travel(6)->seconds();
        $this->assertNotSame($first, $this->actingAs($admin)->getJson('/pembaruan')->json('version'));
    }

    public function test_guests_get_nothing(): void
    {
        $this->getJson('/pembaruan')->assertUnauthorized();
    }
}
