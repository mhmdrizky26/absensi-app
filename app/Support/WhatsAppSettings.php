<?php

namespace App\Support;

use App\Models\Setting;
use Carbon\CarbonInterface;

/**
 * Admin settings for the parent WhatsApp alerts, stored in the settings table.
 * Test mode is on until the admin turns it off: messages are only recorded.
 */
class WhatsAppSettings
{
    public const ENABLED = 'wa_enabled';

    public const TEST_MODE = 'wa_test_mode';

    public const SEND_AT = 'wa_send_at';

    public const DAILY_LIMIT = 'wa_daily_limit';

    public const ON_ESCALATION = 'wa_on_escalation';

    public const ON_NEW_STREAK = 'wa_on_new_streak';

    public const LAST_RUN_ON = 'wa_last_run_on';

    public const TEXT_HIGH = 'wa_text_high';

    public const TEXT_WATCH = 'wa_text_watch';

    public const TEXT_TEST = 'wa_text_test';

    public function __construct(private AttendanceWindow $window) {}

    public function enabled(): bool
    {
        return Setting::value(self::ENABLED, '0') === '1';
    }

    public function testMode(): bool
    {
        return Setting::value(self::TEST_MODE, '1') === '1';
    }

    public function sendAt(): string
    {
        return Setting::value(self::SEND_AT, '15:00');
    }

    public function dailyLimit(): int
    {
        return (int) Setting::value(self::DAILY_LIMIT, '50');
    }

    public function notifyOnEscalation(): bool
    {
        return Setting::value(self::ON_ESCALATION, '1') === '1';
    }

    public function notifyOnNewStreak(): bool
    {
        return Setting::value(self::ON_NEW_STREAK, '1') === '1';
    }

    public function textHigh(): string
    {
        return Setting::value(self::TEXT_HIGH) ?: WhatsAppClient::DEFAULT_TEXT_HIGH;
    }

    public function textWatch(): string
    {
        return Setting::value(self::TEXT_WATCH) ?: WhatsAppClient::DEFAULT_TEXT_WATCH;
    }

    public function textTest(): string
    {
        return Setting::value(self::TEXT_TEST) ?: WhatsAppClient::DEFAULT_TEXT_TEST;
    }

    /**
     * @return array{high: string, watch: string, test: string}
     */
    public function texts(): array
    {
        return ['high' => $this->textHigh(), 'watch' => $this->textWatch(), 'test' => $this->textTest()];
    }

    public function lastRunOn(): ?string
    {
        return Setting::value(self::LAST_RUN_ON);
    }

    public function markRun(CarbonInterface $day): void
    {
        Setting::put([self::LAST_RUN_ON => $day->toDateString()]);
    }

    /**
     * The daily check runs once per school day, at or after the send time,
     * even with WhatsApp off: it also keeps the risk list and its data tidy.
     */
    public function isDue(CarbonInterface $now): bool
    {
        return $now->format('H:i') >= $this->sendAt()
            && $this->lastRunOn() !== $now->toDateString()
            && $this->window->isSchoolDay($now);
    }

    /**
     * @return array{enabled: bool, test_mode: bool, send_at: string, daily_limit: int, on_escalation: bool, on_new_streak: bool}
     */
    public function toArray(): array
    {
        return [
            'enabled' => $this->enabled(),
            'test_mode' => $this->testMode(),
            'send_at' => $this->sendAt(),
            'daily_limit' => $this->dailyLimit(),
            'on_escalation' => $this->notifyOnEscalation(),
            'on_new_streak' => $this->notifyOnNewStreak(),
        ];
    }
}
