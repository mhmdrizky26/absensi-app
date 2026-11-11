<?php

namespace App\Models;

use Database\Factories\GateStationFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

/**
 * A tablet in a stand at the school gate. It logs in with its code once; the
 * login lasts until the admin revokes the tablet or changes its code, which
 * replaces the login token and so logs the tablet out.
 */
#[Fillable(['name', 'code'])]
#[Hidden(['login_token'])]
class GateStation extends Model
{
    /** @use HasFactory<GateStationFactory> */
    use HasFactory;

    /**
     * A tablet that has not checked in for this long is shown as offline.
     */
    public const ONLINE_MINUTES = 3;

    protected static function booted(): void
    {
        static::creating(function (GateStation $station): void {
            $station->login_token ??= Str::random(64);
        });
    }

    protected function casts(): array
    {
        return [
            'last_seen_at' => 'datetime',
            'revoked_at' => 'datetime',
            'pending_scans' => 'integer',
        ];
    }

    /**
     * Codes are kept in capitals and typed on a tablet keyboard, so any case
     * and spaces are accepted.
     */
    public static function normalizeCode(?string $code): string
    {
        return Str::upper((string) preg_replace('/\s+/', '', (string) $code));
    }

    /**
     * "Tab N" and "TABN" for the lowest number no station uses yet.
     *
     * @return array{name: string, code: string}
     */
    public static function nextSuggestion(): array
    {
        $taken = static::query()->pluck('code')->flip();
        $number = 1;

        while ($taken->has("TAB{$number}")) {
            $number++;
        }

        return ['name' => "Tab {$number}", 'code' => "TAB{$number}"];
    }

    public function isRevoked(): bool
    {
        return $this->revoked_at !== null;
    }

    public function isOnline(): bool
    {
        return $this->last_seen_at?->greaterThan(now()->subMinutes(self::ONLINE_MINUTES)) ?? false;
    }

    /**
     * Log out the tablet that logged in with the old token.
     */
    public function rotateLoginToken(): void
    {
        $this->forceFill(['login_token' => Str::random(64)])->save();
    }

    public function revoke(): void
    {
        $this->forceFill(['revoked_at' => now(), 'login_token' => Str::random(64)])->save();
    }

    public function restore(): void
    {
        $this->forceFill(['revoked_at' => null, 'login_token' => Str::random(64)])->save();
    }

    /**
     * Record that the tablet checked in, with how many scans it still has to send.
     */
    public function markSeen(?int $pendingScans = null): void
    {
        $this->forceFill(['last_seen_at' => now()]);

        if ($pendingScans !== null) {
            $this->forceFill(['pending_scans' => min(max($pendingScans, 0), 65535)]);
        }

        $this->saveQuietly();
    }
}
