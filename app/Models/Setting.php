<?php

namespace App\Models;

use Illuminate\Contracts\Encryption\DecryptException;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Crypt;
use Throwable;

/**
 * School-wide key/value settings. Values are cached; saving through
 * Setting::put() clears the cache.
 *
 * Some settings replace a value from .env, so the admin can change them in
 * Pengaturan without touching the server: once saved, they overrule the
 * matching config key for the whole app (see applyToConfig()).
 */
#[Fillable(['key', 'value'])]
class Setting extends Model
{
    private const CACHE_KEY = 'app-settings';

    public const APP_NAME = 'app_name';

    public const SCHOOL_NAME = 'school_name';

    public const TIMEZONE = 'timezone';

    /**
     * Indonesian time zones the school can be in.
     */
    public const TIMEZONES = ['Asia/Jakarta' => 'WIB (Waktu Indonesia Barat)', 'Asia/Makassar' => 'WITA (Waktu Indonesia Tengah)', 'Asia/Jayapura' => 'WIT (Waktu Indonesia Timur)'];

    public const DEMO_MODE = 'demo_mode';

    public const FINALIZE_AT = 'finalize_at';

    public const FONNTE_TOKEN = 'fonnte_token';

    /**
     * Setting key => the config key it overrules.
     */
    private const CONFIG_OVERRIDES = [
        self::APP_NAME => 'app.name',
        self::SCHOOL_NAME => 'app.school_name',
        self::TIMEZONE => 'app.timezone',
        self::DEMO_MODE => 'attendance.enforce_window',
        self::FINALIZE_AT => 'attendance.finalize_at',
        self::FONNTE_TOKEN => 'services.fonnte.token',
    ];

    /**
     * Settings stored encrypted, because they are secrets.
     */
    private const ENCRYPTED = [self::FONNTE_TOKEN];

    protected $primaryKey = 'key';

    protected $keyType = 'string';

    public $incrementing = false;

    public static function value(string $key, ?string $default = null): ?string
    {
        $values = Cache::rememberForever(self::CACHE_KEY, fn (): array => static::query()->pluck('value', 'key')->all());

        return $values[$key] ?? $default;
    }

    /**
     * @param  array<string, string|null>  $values
     */
    public static function put(array $values): void
    {
        foreach ($values as $key => $value) {
            if (in_array($key, self::ENCRYPTED, true) && filled($value)) {
                $value = Crypt::encryptString($value);
            }

            static::query()->updateOrCreate(['key' => $key], ['value' => $value]);
        }

        Cache::forget(self::CACHE_KEY);
        static::applyToConfig();
    }

    /**
     * Overrule config values from .env with what the admin saved. Runs on
     * every boot; quietly does nothing before the settings table exists.
     */
    public static function applyToConfig(): void
    {
        try {
            foreach (self::CONFIG_OVERRIDES as $key => $configKey) {
                $value = static::value($key);

                if ($value === null) {
                    continue;
                }

                config([$configKey => match ($key) {
                    self::DEMO_MODE => $value !== '1',
                    self::FONNTE_TOKEN => static::decrypt($value),
                    default => $value,
                }]);

                if ($key === self::TIMEZONE && array_key_exists($value, self::TIMEZONES)) {
                    date_default_timezone_set($value);
                }
            }
        } catch (Throwable) {
            // Tabel pengaturan belum ada (sebelum migrate): pakai nilai .env.
        }
    }

    private static function decrypt(string $value): ?string
    {
        try {
            return $value === '' ? null : Crypt::decryptString($value);
        } catch (DecryptException) {
            return null;
        }
    }
}
