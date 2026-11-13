<?php

namespace App\Support;

use App\Enums\Role;
use App\Models\DutySchedule;
use App\Models\DutySubstitution;
use App\Models\User;
use Carbon\CarbonInterface;
use Illuminate\Support\Collection;

/**
 * Who is guru piket on a given day: the teachers scheduled for its weekday,
 * with that day's substitutions applied.
 */
class DutyRoster
{
    /**
     * @return Collection<int, User>
     */
    public function teachersOn(CarbonInterface $date): Collection
    {
        $substitutions = DutySubstitution::query()->whereDate('date', $date)->get(['absent_user_id', 'substitute_user_id']);

        $userIds = DutySchedule::query()
            ->where('weekday', $date->dayOfWeekIso)
            ->pluck('user_id')
            ->diff($substitutions->pluck('absent_user_id'))
            ->merge($substitutions->pluck('substitute_user_id'))
            ->unique();

        return User::query()
            ->whereKey($userIds)
            ->where('role', Role::Guru)
            ->orderBy('name')
            ->get(['id', 'name']);
    }
}
