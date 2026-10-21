<?php

namespace App\Support;

use App\Enums\DispensationStatus;
use App\Enums\Role;
use App\Models\Dispensation;
use App\Models\User;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/**
 * Lets open pages notice changes made by other users. Browsers ask for the
 * data version every few seconds and quietly reload when it changed.
 */
class LiveUpdates
{
    /**
     * Tables whose changes show up on other users' pages.
     */
    private const TABLES = ['attendances', 'attendance_sessions', 'dispensations', 'guardian_contacts', 'risk_episodes', 'risk_messages', 'holidays', 'students'];

    private const CACHE_SECONDS = 5;

    /**
     * A short fingerprint of the row counts and latest change per table;
     * cached briefly so many open pages cost only a few queries.
     */
    public function version(): string
    {
        return Cache::remember('live-updates-version', self::CACHE_SECONDS, function (): string {
            $parts = array_map(function (string $table): string {
                $row = DB::table($table)->selectRaw('count(*) as total, max(updated_at) as changed')->first();

                return "{$row->total}:{$row->changed}";
            }, self::TABLES);

            return substr(sha1(implode('|', $parts)), 0, 16);
        });
    }

    /**
     * Dispensations waiting for this user's decision; for an admin, all pending ones.
     */
    public function pendingDispensations(User $user): int
    {
        return $user->hasRole(Role::Admin)
            ? Dispensation::query()->where('status', DispensationStatus::Pending)->count()
            : Dispensation::query()->awaitingDecisionFrom($user)->count();
    }
}
