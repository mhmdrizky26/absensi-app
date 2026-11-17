<?php

namespace App\Support;

use App\Enums\DispensationStatus;
use App\Enums\Role;
use App\Models\Dispensation;
use App\Models\User;
use Illuminate\Database\Events\QueryExecuted;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Str;

/**
 * Lets open pages notice changes made by other users. Every write to a
 * watched table gives its topic a new version (after the transaction
 * commits), so browsers can ask every few seconds for a single cached value
 * and quietly reload only when it changed.
 *
 * Gate scans change attendance many times a minute in the morning, so pages
 * reload for attendance less often than for the other data.
 */
class LiveUpdates
{
    public const ATTENDANCE = 'attendance';

    public const DATA = 'data';

    /**
     * Topic => tables whose changes show up on other users' pages. Finishing
     * the day (attendance_days) is in both: gate tablets follow attendance,
     * and staff pages show it at once instead of waiting like for scans.
     */
    private const TOPICS = [
        self::ATTENDANCE => ['attendances', 'attendance_days'],
        self::DATA => [
            'attendance_days', 'dispensations', 'guardian_contacts', 'risk_episodes', 'risk_messages', 'holidays', 'online_days',
            'students', 'classrooms', 'classroom_student', 'duty_schedules', 'duty_substitutions',
        ],
    ];

    /**
     * Topics written inside a transaction that is still open.
     *
     * @var array<string, true>
     */
    private static array $pending = [];

    /**
     * Watch every insert, update and delete, also bulk queries that skip
     * model events, and give the written topics a new version.
     */
    public static function watchWrites(): void
    {
        Event::listen(function (QueryExecuted $query): void {
            foreach (self::topicsWrittenBy($query->sql) as $topic) {
                if (isset(self::$pending[$topic])) {
                    continue;
                }

                // Outside a transaction this runs at once; inside one, after the
                // commit, so other pages never reload before the data is visible.
                self::$pending[$topic] = true;
                $query->connection->afterRollBack(function () use ($topic): void {
                    unset(self::$pending[$topic]);
                });
                $query->connection->afterCommit(function () use ($topic): void {
                    unset(self::$pending[$topic]);
                    self::bump([$topic]);
                });
            }
        });
    }

    /**
     * The topics of the table an insert, update or delete writes to.
     *
     * @return list<string>
     */
    public static function topicsWrittenBy(string $sql): array
    {
        if (! preg_match('/^\s*(?:insert\s+(?:(?:or\s+)?ignore\s+)?into|replace\s+into|update|delete\s+from)\s+[`"\[]?(\w+)/i', $sql, $match)) {
            return [];
        }

        $table = strtolower($match[1]);

        return array_keys(array_filter(self::TOPICS, fn (array $tables): bool => in_array($table, $tables, true)));
    }

    /**
     * @param  list<string>  $topics
     */
    public static function bump(array $topics): void
    {
        foreach ($topics as $topic) {
            Cache::forever(self::cacheKey($topic), Str::random(12));
        }
    }

    /**
     * The current version of every topic.
     *
     * @return array{attendance: string, data: string}
     */
    public function versions(): array
    {
        $keys = array_map(self::cacheKey(...), array_keys(self::TOPICS));
        $values = Cache::many($keys);
        $versions = [];

        foreach (array_keys(self::TOPICS) as $topic) {
            $version = $values[self::cacheKey($topic)] ?? null;

            if ($version === null) {
                $version = Str::random(12);
                Cache::forever(self::cacheKey($topic), $version);
            }

            $versions[$topic] = $version;
        }

        return $versions;
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

    private static function cacheKey(string $topic): string
    {
        return "live-updates:{$topic}";
    }
}
