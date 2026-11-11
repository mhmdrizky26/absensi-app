<?php

namespace App\Support;

use App\Models\Setting;
use Symfony\Component\HttpFoundation\IpUtils;

/**
 * Optional lock that lets gate tablets log in and scan only from the school's
 * network. The admin lists the school's IP addresses (or ranges such as
 * 10.0.0.0/24); an empty list switches the lock off.
 */
class GateNetwork
{
    public const ALLOWED_IPS = 'gate_allowed_ips';

    /**
     * @return list<string>
     */
    public function allowedIps(): array
    {
        return self::parse((string) Setting::value(self::ALLOWED_IPS, ''));
    }

    public function isRestricted(): bool
    {
        return $this->allowedIps() !== [];
    }

    public function allows(?string $ip): bool
    {
        return ! $this->isRestricted() || ($ip !== null && IpUtils::checkIp($ip, $this->allowedIps()));
    }

    /**
     * @param  list<string>  $ips
     */
    public function save(array $ips): void
    {
        Setting::put([self::ALLOWED_IPS => implode(',', $ips)]);
    }

    /**
     * Split the admin's text (commas, spaces or new lines) into addresses.
     *
     * @return list<string>
     */
    public static function parse(string $text): array
    {
        return array_values(array_unique(array_filter(preg_split('/[\s,]+/', $text) ?: [], fn (string $ip): bool => $ip !== '')));
    }
}
