<?php

namespace App\Support;

class PhoneNormalizer
{
    public static function toCanonicalDigits(string $jidOrPhone): string
    {
        $value = trim($jidOrPhone);

        if (str_contains($value, '@')) {
            $value = explode('@', $value, 2)[0];
        }

        $digits = preg_replace('/[^0-9]/', '', $value) ?? '';

        if ($digits === '') {
            return '';
        }

        if (str_starts_with($digits, '08')) {
            return '62'.substr($digits, 1);
        }

        if (str_starts_with($digits, '8')) {
            return '62'.$digits;
        }

        if (str_starts_with($digits, '620')) {
            return '62'.substr($digits, 3);
        }

        return $digits;
    }

    public static function toE164(string $phone, string $defaultCountryCode = '+62'): string
    {
        $digits = self::toCanonicalDigits($phone);

        if ($digits === '') {
            return $defaultCountryCode;
        }

        $countryCodeDigits = ltrim($defaultCountryCode, '+');
        if (! str_starts_with($digits, $countryCodeDigits)) {
            $digits = $countryCodeDigits.$digits;
        }

        return '+'.$digits;
    }

    public static function toJid(string $e164Phone): string
    {
        $digits = self::toCanonicalDigits($e164Phone);

        return $digits.'@s.whatsapp.net';
    }

    public static function fromJid(string $jid): string
    {
        $digits = self::toCanonicalDigits($jid);

        return '+'.$digits;
    }

    public static function formatForDisplay(string $e164Phone): string
    {
        $normalized = self::toE164($e164Phone);

        if (str_starts_with($normalized, '+62')) {
            $number = substr($normalized, 3);

            return '0'.$number;
        }

        return $normalized;
    }

    public static function isSameIdentity(string $left, string $right): bool
    {
        $leftDigits = self::toCanonicalDigits($left);
        $rightDigits = self::toCanonicalDigits($right);

        return $leftDigits !== '' && $leftDigits === $rightDigits;
    }
}
