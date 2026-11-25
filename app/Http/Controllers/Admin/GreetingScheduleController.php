<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\GreetingScheduleRequest;
use App\Models\GreetingSchedule;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

/**
 * The weekly sapa pagi schedule: teachers who greet students at the gate and
 * may record attendance from when the gate opens until jam masuk.
 */
class GreetingScheduleController extends Controller
{
    public function index(): Response
    {
        return Inertia::render('Admin/GreetingSchedule', [
            'weekdays' => DutyScheduleController::weekdays(GreetingSchedule::query()->get(['user_id', 'weekday'])->groupBy('weekday')),
            'teachers' => DutyScheduleController::teachers(GreetingScheduleRequest::ROLES),
        ]);
    }

    /**
     * Replace the whole weekly schedule (same form as the duty schedule).
     */
    public function update(GreetingScheduleRequest $request): RedirectResponse
    {
        DB::transaction(function () use ($request): void {
            GreetingSchedule::query()->delete();

            foreach ($request->schedule() as $weekday => $userIds) {
                foreach ($userIds as $userId) {
                    GreetingSchedule::query()->create(['user_id' => $userId, 'weekday' => $weekday]);
                }
            }
        });

        return back()->with('success', 'Jadwal sapa pagi disimpan.');
    }
}
