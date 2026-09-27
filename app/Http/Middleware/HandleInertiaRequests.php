<?php

namespace App\Http\Middleware;

use App\Enums\DispensationStatus;
use App\Enums\Role;
use App\Models\Dispensation;
use App\Models\User;
use Illuminate\Http\Request;
use Inertia\Middleware;

class HandleInertiaRequests extends Middleware
{
    /**
     * The root template that's loaded on the first page visit.
     *
     * @see https://inertiajs.com/server-side-setup#root-template
     *
     * @var string
     */
    protected $rootView = 'app';

    /**
     * Determines the current asset version.
     *
     * @see https://inertiajs.com/asset-versioning
     */
    public function version(Request $request): ?string
    {
        return parent::version($request);
    }

    /**
     * Define the props that are shared by default.
     *
     * @see https://inertiajs.com/shared-data
     *
     * @return array<string, mixed>
     */
    public function share(Request $request): array
    {
        $user = $request->user();

        return [
            ...parent::share($request),
            'appName' => config('app.name'),
            'schoolName' => config('app.school_name'),
            'auth' => [
                'user' => $user ? [
                    'id' => $user->id,
                    'name' => $user->name,
                    'username' => $user->username,
                    'role' => $user->role->value,
                    'roleLabel' => $user->role->label(),
                ] : null,
            ],
            'navCounts' => fn () => $user ? ['pendingDispensations' => $this->pendingDispensations($user)] : [],
            'flash' => [
                'success' => fn () => $request->session()->get('success'),
                'error' => fn () => $request->session()->get('error'),
                'importErrors' => fn () => $request->session()->get('importErrors', []),
            ],
        ];
    }

    /**
     * Dispensations waiting for this user's decision; for an admin, all
     * pending ones.
     */
    private function pendingDispensations(User $user): int
    {
        return $user->hasRole(Role::Admin)
            ? Dispensation::query()->where('status', DispensationStatus::Pending)->count()
            : Dispensation::query()->awaitingDecisionFrom($user)->count();
    }
}
