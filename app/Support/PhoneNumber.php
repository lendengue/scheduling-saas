<?php

namespace App\Support;

/**
 * Normalizes phone numbers to E.164 ("+5511987654321").
 *
 * Numbers typed without a country code are taken as Brazilian: 10 digits
 * (area code + landline) or 11 digits (area code + mobile), with or without
 * the usual mask "(11) 98765-4321".
 */
final class PhoneNumber
{
    public const string E164_PATTERN = '/^\+[1-9]\d{7,14}$/';

    public static function toE164(string $input, string $defaultCountryCode = '55'): ?string
    {
        $input = trim($input);
        $international = str_starts_with($input, '+') || str_starts_with($input, '00');
        $digits = (string) preg_replace('/\D/', '', $input);

        if (str_starts_with($input, '00')) {
            $digits = substr($digits, 2);
        }

        $candidate = match (true) {
            $international => '+'.$digits,
            in_array(strlen($digits), [10, 11], true) => '+'.$defaultCountryCode.$digits,
            default => '+'.$digits,
        };

        return preg_match(self::E164_PATTERN, $candidate) === 1 ? $candidate : null;
    }
}
