<?php

namespace App\Http\Middleware;

use App\Enums\Role;
use App\Support\LiveUpdates;
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
    /**
     * Settings in .env that are fine on a laptop but dangerous on the school's
     * server, shown to admins whenever the app is opened from another address
     * than this computer.
     *
     * @return list<string>
     */
    public static function serverWarnings(Request $request): array
    {
        if (in_array($request->getHost(), ['localhost', '127.0.0.1', '::1'], true)) {
            return [];
        }

        $warnings = [];

        if (config('app.debug')) {
            $warnings[] = 'APP_DEBUG=true: halaman error bisa membocorkan password database dan kunci aplikasi. Ubah menjadi APP_DEBUG=false di file .env server.';
        }

        if (! app()->isProduction()) {
            $warnings[] = 'APP_ENV bukan production. Ubah menjadi APP_ENV=production di file .env server.';
        }

        return $warnings;
    }

    public function share(Request $request): array
    {
        $user = $request->user();

        return [
            ...parent::share($request),
            'appName' => config('app.name'),
            'schoolName' => config('app.school_name'),
            'demoMode' => ! config('attendance.enforce_window'),
            'auth' => [
                'user' => $user ? [
                    'id' => $user->id,
                    'name' => $user->name,
                    'username' => $user->username,
                    'role' => $user->role->value,
                    'roleLabel' => $user->role->label(),
                ] : null,
            ],
            'serverWarnings' => fn () => $user?->hasRole(Role::Admin) ? self::serverWarnings($request) : [],
            'navCounts' => fn () => $user ? ['pendingDispensations' => app(LiveUpdates::class)->pendingDispensations($user)] : [],
            'flash' => [
                'success' => fn () => $request->session()->get('success'),
                'error' => fn () => $request->session()->get('error'),
                'gateClosed' => fn () => $request->session()->get('gateClosed'),
                'importErrors' => fn () => $request->session()->get('importErrors', []),
            ],
        ];
    }
}
