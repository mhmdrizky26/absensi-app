<?php

namespace App\Support;

class RandomCode
{
    /**
     * Upper-case letters and digits without look-alikes (0/O, 1/I/L). Every
     * character is valid in a QR code's compact alphanumeric mode.
     */
    public const ALPHABET = 'ABCDEFGHJKMNPQRSTUVWXYZ23456789';

    /**
     * Generate a cryptographically random code from the alphabet.
     */
    public static function generate(int $length): string
    {
        $code = '';
        $lastIndex = strlen(self::ALPHABET) - 1;

        for ($i = 0; $i < $length; $i++) {
            $code .= self::ALPHABET[random_int(0, $lastIndex)];
        }

        return $code;
    }
}
