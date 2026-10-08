<?php

namespace Dashed\DashedEcommerceCore\Classes;

class PhoneNormalizer
{
    /**
     * Best-effort normalisatie naar E.164 (+landcode...). Zonder
     * libphonenumber dekken we de gangbare NL/BE-invoer af; onbekende
     * landen vallen terug op de opgeschoonde invoer.
     */
    public static function toE164(?string $phone, ?string $countryCode): string
    {
        $phone = trim((string) $phone);
        if ($phone === '') {
            return '';
        }

        // Internationale prefix 00 -> +
        if (str_starts_with($phone, '00')) {
            $phone = '+' . substr($phone, 2);
        }

        // Al in E.164: alleen cijfers achter de + behouden.
        if (str_starts_with($phone, '+')) {
            // Een als (0) geschreven nul direct na de landcode is de nationale nul: weglaten.
            $phone = preg_replace('/^(\+[\d\s.\-]{1,6}?)\s*\(0\)/', '$1', $phone);

            return '+' . preg_replace('/\D/', '', substr($phone, 1));
        }

        $digits = preg_replace('/\D/', '', $phone);
        if ($digits === '') {
            return '';
        }

        $callingCodes = [
            'NL' => '31',
            'BE' => '32',
            'DE' => '49',
            'FR' => '33',
            'LU' => '352',
        ];
        $callingCode = $callingCodes[strtoupper((string) $countryCode)] ?? null;

        if ($callingCode === null) {
            // Onbekend land: geef de cijfers terug zonder te gokken op een landcode.
            return $digits;
        }

        // Nationale notatie met voorloop-0 -> landcode ervoor.
        if (str_starts_with($digits, '0')) {
            return '+' . $callingCode . substr($digits, 1);
        }

        // Begint al met de landcode.
        if (str_starts_with($digits, $callingCode)) {
            return '+' . $digits;
        }

        return '+' . $callingCode . $digits;
    }
}
