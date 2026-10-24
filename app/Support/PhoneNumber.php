<?php

namespace App\Support;

/**
 * Phone numbers follow the Burkina Faso numbering plan: country code +226,
 * 8 digit national numbers without leading 0 (e.g. 70 12 34 56).
 * The mobile app applies the same rules (lib/phone.ts).
 */
class PhoneNumber
{
    public const DIAL_CODE = '226';

    public const NATIONAL_LENGTH = 8;

    public const EXAMPLE = '70 12 34 56';

    /**
     * Normalized numbers are international: "+" then 8 to 15 digits.
     */
    public const VALIDATION_REGEX = '/^\+[1-9]\d{7,14}$/';

    /**
     * Normalize to the international format (+22670123456).
     *
     * Accepts the national number (70123456), the old habit of a leading 0
     * (070123456), the country code with or without "+" or "00", and keeps
     * numbers from other countries written with their own country code.
     */
    public static function normalize(string $phoneNumber): string
    {
        $normalized = preg_replace('/[^\d+]/', '', $phoneNumber) ?? '';

        if (str_starts_with($normalized, '00')) {
            $normalized = '+'.substr($normalized, 2);
        }

        if (str_starts_with($normalized, '+')) {
            return $normalized;
        }

        if (str_starts_with($normalized, self::DIAL_CODE) && strlen($normalized) === strlen(self::DIAL_CODE) + self::NATIONAL_LENGTH) {
            return '+'.$normalized;
        }

        if (str_starts_with($normalized, '0') && strlen($normalized) === self::NATIONAL_LENGTH + 1) {
            $normalized = substr($normalized, 1);
        }

        if (strlen($normalized) === self::NATIONAL_LENGTH) {
            return '+'.self::DIAL_CODE.$normalized;
        }

        return $normalized;
    }

    public static function invalidMessage(): string
    {
        return 'Numéro de téléphone invalide. Exemple : '.self::EXAMPLE.'.';
    }
}
