<?php

namespace App\Http\Middleware;

use App\Enums\Role;
use App\Models\User;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureUserHasRole
{
    /**
     * Only let the request through when the user holds one of the given roles
     * or duties. Besides the account roles (admin, guru, guru_bk) it accepts:
     * - wali_kelas: a guru leading a class this academic year;
     * - piket: a guru on duty today;
     * - jadwal_piket: a guru with any day on the duty schedule.
     *
     * Usage in routes: ->middleware('role:admin,piket')
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next, string ...$roles): Response
    {
        $user = $request->user();

        abort_unless($user && collect($roles)->contains(fn (string $role): bool => self::holds($user, $role)), 403);

        return $next($request);
    }

    private static function holds(User $user, string $role): bool
    {
        return match ($role) {
            'wali_kelas' => $user->isHomeroomTeacher(),
            'piket' => $user->isOnDuty(),
            'jadwal_piket' => $user->hasDutySchedule(),
            default => $user->hasRole(Role::from($role)),
        };
    }
}
