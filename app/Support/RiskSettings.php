<?php

namespace App\Support;

use App\Models\Setting;

/**
 * Thresholds of the early-warning rules, adjustable by the admin. Most rules
 * look at the last few weeks only, so students drop off the list once their
 * attendance recovers instead of staying listed for the whole semester.
 */
class RiskSettings
{
    /** @var array<string, int> setting key => default */
    public const DEFAULTS = [
        'risk_window_days' => 30,
        'risk_absent_watch' => 4,
        'risk_absent_high' => 6,
        'risk_rate_watch' => 85,
        'risk_rate_high' => 75,
        'risk_semester_rate_high' => 75,
        'risk_late_watch' => 5,
        'risk_excused_watch' => 4,
    ];

    public function get(string $key): int
    {
        return (int) (Setting::value($key) ?? self::DEFAULTS[$key]);
    }

    public function windowDays(): int
    {
        return $this->get('risk_window_days');
    }

    /**
     * @return array<string, int> without the "risk_" prefix, for the settings form
     */
    public function toArray(): array
    {
        $values = [];

        foreach (array_keys(self::DEFAULTS) as $key) {
            $values[substr($key, 5)] = $this->get($key);
        }

        return $values;
    }
}
