<?php

namespace App\Http\Controllers;

use App\Enums\Role;
use App\Enums\StudentStatus;
use App\Models\AcademicYear;
use App\Models\DutySchedule;
use App\Models\DutySubstitution;
use App\Models\Student;
use App\Models\User;
use App\Support\AttendanceTrend;
use App\Support\DutyRoster;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController extends Controller
{
    /**
     * Show the staff dashboard: the menu for the user's role and duties, the
     * duty schedule for a guru, and below it attendance trends.
     */
    public function __invoke(Request $request, AttendanceTrend $trend, DutyRoster $roster): Response
    {
        $user = $request->user();
        $seesTrends = $user->hasRole(Role::Admin, Role::GuruBk) || $user->isHomeroomTeacher();

        return Inertia::render('Dashboard', [
            'stats' => $user->hasRole(Role::Admin) ? $this->adminStats() : null,
            'duty' => $user->hasRole(Role::Guru) ? $this->dutyInfo($user, $roster) : null,
            'trends' => $seesTrends ? $trend->overview($user, $request->integer('classroom') ?: null, $request->string('periode')->value() === 'tahun') : null,
        ]);
    }

    /**
     * The guru's weekly duty days, who is on duty today, and upcoming
     * substitutions that involve them.
     *
     * @return array{days: list<string>, today: list<string>, substitutions: list<array{date: string, isSubstitute: bool, otherName: ?string}>}
     */
    private function dutyInfo(User $user, DutyRoster $roster): array
    {
        return [
            'days' => $user->dutySchedules()->orderBy('weekday')->pluck('weekday')->map(fn (int $weekday): string => DutySchedule::WEEKDAYS[$weekday] ?? '')->filter()->values()->all(),
            'today' => $roster->teachersOn(today())->pluck('name')->all(),
            'substitutions' => DutySubstitution::query()
                ->with(['absentUser:id,name', 'substituteUser:id,name'])
                ->whereDate('date', '>=', today())
                ->where(fn (Builder $query) => $query->where('absent_user_id', $user->id)->orWhere('substitute_user_id', $user->id))
                ->orderBy('date')
                ->limit(10)
                ->get()
                ->map(fn (DutySubstitution $substitution): array => [
                    'date' => $substitution->date->toDateString(),
                    'isSubstitute' => $substitution->substitute_user_id === $user->id,
                    'otherName' => $substitution->substitute_user_id === $user->id ? $substitution->absentUser?->name : $substitution->substituteUser?->name,
                ])
                ->all(),
        ];
    }

    /**
     * @return array{academicYear: ?string, classrooms: int, students: int, users: int}
     */
    private function adminStats(): array
    {
        $academicYear = AcademicYear::active();

        return [
            'academicYear' => $academicYear?->name,
            'classrooms' => $academicYear?->classrooms()->count() ?? 0,
            'students' => Student::query()->where('status', StudentStatus::Active)->count(),
            'users' => User::query()->where('is_active', true)->count(),
        ];
    }
}
