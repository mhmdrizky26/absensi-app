<?php

namespace App\Http\Controllers\Admin;

use App\Enums\Role;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\DutyScheduleRequest;
use App\Models\AcademicYear;
use App\Models\DutySchedule;
use App\Models\DutySubstitution;
use App\Models\User;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

/**
 * The weekly duty schedule (guru piket per weekday, the same every week) and
 * one-day substitutions when a scheduled teacher cannot come.
 */
class DutyScheduleController extends Controller
{
    public function index(): Response
    {
        $academicYearId = AcademicYear::active()?->id;

        $teachers = User::query()
            ->where('role', Role::Guru)
            ->where('is_active', true)
            ->with(['homeroomClassrooms' => fn (HasMany $query) => $query->where('academic_year_id', $academicYearId)])
            ->orderBy('name')
            ->get(['id', 'name'])
            ->map(fn (User $teacher): array => [
                'id' => $teacher->id,
                'name' => $teacher->name,
                'homeroom' => $teacher->homeroomClassrooms->first()?->name,
            ]);

        $schedule = DutySchedule::query()->get(['user_id', 'weekday'])->groupBy('weekday');

        return Inertia::render('Admin/DutySchedule', [
            'weekdays' => collect(DutySchedule::WEEKDAYS)->map(fn (string $label, int $weekday): array => [
                'value' => $weekday,
                'label' => $label,
                'userIds' => $schedule->get($weekday, collect())->pluck('user_id')->values(),
            ])->values(),
            'teachers' => $teachers,
            'substitutions' => DutySubstitution::query()
                ->with(['absentUser:id,name', 'substituteUser:id,name'])
                ->whereDate('date', '>=', today())
                ->orderBy('date')
                ->get()
                ->map(fn (DutySubstitution $substitution): array => [
                    'id' => $substitution->id,
                    'date' => $substitution->date->toDateString(),
                    'absent' => $substitution->absentUser?->name,
                    'substitute' => $substitution->substituteUser?->name,
                ]),
        ]);
    }

    /**
     * Replace the whole weekly schedule.
     */
    public function update(DutyScheduleRequest $request): RedirectResponse
    {
        DB::transaction(function () use ($request): void {
            DutySchedule::query()->delete();

            foreach ($request->schedule() as $weekday => $userIds) {
                foreach ($userIds as $userId) {
                    DutySchedule::query()->create(['user_id' => $userId, 'weekday' => $weekday]);
                }
            }
        });

        return back()->with('success', 'Jadwal piket disimpan.');
    }
}
