<?php

namespace App\Http\Middleware;

use App\Enums\Role;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureUserHasRole
{
    /**
     * Only let the request through when the user holds one of the given roles.
     *
     * Usage in routes: ->middleware('role:admin,guru_piket')
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next, string ...$roles): Response
    {
        $allowedRoles = array_map(fn (string $role): Role => Role::from($role), $roles);

        abort_unless($request->user()?->hasRole(...$allowedRoles), 403);

        return $next($request);
    }
}
