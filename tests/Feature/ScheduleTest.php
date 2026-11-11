<?php

namespace Tests\Feature;

use Illuminate\Console\Scheduling\Event;
use Illuminate\Console\Scheduling\Schedule;
use Tests\TestCase;

class ScheduleTest extends TestCase
{
    public function test_one_cron_line_runs_everything_including_the_queue_on_shared_hosting(): void
    {
        $events = collect(app(Schedule::class)->events())->mapWithKeys(fn (Event $event): array => [
            trim(str($event->command)->after('artisan')->replace(["'", '"'], '')) => $event->expression,
        ]);

        $this->assertSame('*/10 * * * *', $events['attendance:finalize-days']);
        $this->assertSame('* * * * *', $events['risiko:kirim-wa']);
        $this->assertSame('* * * * *', $events['queue:work --stop-when-empty --max-time=50 --tries=1']);
    }
}
