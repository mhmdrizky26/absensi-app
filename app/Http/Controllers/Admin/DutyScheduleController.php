<?php

namespace App\Http\Controllers\Admin;

use App\Enums\Role;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\DutyScheduleRequest;
use App\Models\AcademicYear;
use App\Models\DutySchedule;
use App\Models\DutySubstitution;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Collection;
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
        return Inertia::render('Admin/DutySchedule', [
            'weekdays' => self::weekdays(DutySchedule::query()->get(['user_id', 'weekday'])->groupBy('weekday')),
            'teachers' => self::teachers(),
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
     * Active teachers that can be scheduled, with the class they lead.
     *
     * @param  list<Role>  $roles
     * @return Collection<int, array{id: int, name: string, homeroom: ?string}>
     */
    public static function teachers(array $roles = [Role::Guru]): Collection
    {
        $academicYearId = AcademicYear::active()?->id;

        return User::query()
            ->whereIn('role', $roles)
            ->where('is_active', true)
            ->with(['homeroomClassrooms' => fn (HasMany $query) => $query->where('academic_year_id', $academicYearId)])
            ->orderBy('name')
            ->get(['id', 'name'])
            ->map(fn (User $teacher): array => [
                'id' => $teacher->id,
                'name' => $teacher->name,
                'homeroom' => $teacher->homeroomClassrooms->first()?->name,
            ]);
    }

    /**
     * Every school weekday with the teachers scheduled on it.
     *
     * @param  Collection<int, Collection<int, Model>>  $schedule  rows with user_id, grouped by weekday
     * @return Collection<int, array{value: int, label: string, userIds: Collection<int, int>}>
     */
    public static function weekdays(Collection $schedule): Collection
    {
        return collect(DutySchedule::dutyWeekdays())->map(fn (string $label, int $weekday): array => [
            'value' => $weekday,
            'label' => $label,
            'userIds' => $schedule->get($weekday, collect())->pluck('user_id')->values(),
        ])->values();
    }

    /**
     * Replace the whole weekly schedule. Days that are no longer school days
     * lose their guru piket.
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
