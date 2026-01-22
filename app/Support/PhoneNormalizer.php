<?php

namespace App\Support;

class PhoneNormalizer
{
    public static function toE164(string $phone, string $defaultCountryCode = '+62'): string
    {
        $phone = preg_replace('/[^0-9+]/', '', $phone);

        if (str_starts_with($phone, '+')) {
            return $phone;
        }

        if (str_starts_with($phone, '0')) {
            $phone = substr($phone, 1);
        }

        $countryCodeDigits = ltrim($defaultCountryCode, '+');

        if (str_starts_with($phone, $countryCodeDigits)) {
            return '+'.$phone;
        }

        return $defaultCountryCode.$phone;
    }

    public static function toJid(string $e164Phone): string
    {
        $digits = preg_replace('/[^0-9]/', '', $e164Phone);

        return $digits.'@s.whatsapp.net';
    }

    public static function fromJid(string $jid): string
    {
        // Extract phone number from JID (remove @s.whatsapp.net suffix)
        $phone = str_replace(['@s.whatsapp.net', '@c.us'], '', $jid);

        // Add + prefix if it doesn't exist
        if (! str_starts_with($phone, '+')) {
            $phone = '+'.$phone;
        }

        return $phone;
    }

    public static function formatForDisplay(string $e164Phone): string
    {
        if (str_starts_with($e164Phone, '+62')) {
            $number = substr($e164Phone, 3);

            return '0'.$number;
        }

        return $e164Phone;
    }
}
