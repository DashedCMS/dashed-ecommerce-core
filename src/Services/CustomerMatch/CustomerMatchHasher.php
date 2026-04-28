<?php

declare(strict_types=1);

namespace Dashed\DashedEcommerceCore\Services\CustomerMatch;

class CustomerMatchHasher
{
    private const COUNTRY_DIAL_CODES = [
        'NL' => '31',
        'BE' => '32',
        'DE' => '49',
        'FR' => '33',
        'LU' => '352',
        'AT' => '43',
        'CH' => '41',
        'GB' => '44',
        'IE' => '353',
        'ES' => '34',
        'IT' => '39',
        'PT' => '351',
        'DK' => '45',
        'SE' => '46',
        'NO' => '47',
        'FI' => '358',
        'PL' => '48',
        'US' => '1',
        'CA' => '1',
    ];

    private const COUNTRY_NAME_TO_ISO = [
        'netherlands' => 'NL',
        'nederland' => 'NL',
        'belgium' => 'BE',
        'belgië' => 'BE',
        'belgie' => 'BE',
        'germany' => 'DE',
        'deutschland' => 'DE',
        'duitsland' => 'DE',
    ];

    public function hashEmail(?string $email): string
    {
        $normalized = strtolower(trim((string) $email));

        if ($normalized === '') {
            return '';
        }

        return hash('sha256', $normalized);
    }

    public function hashPhone(?string $phone, ?string $country = null): string
    {
        $normalized = $this->normalizePhone($phone, $country);

        if ($normalized === '') {
            return '';
        }

        return hash('sha256', $normalized);
    }

    public function hashName(?string $name): string
    {
        $normalized = mb_strtolower(trim((string) $name));

        if ($normalized === '') {
            return '';
        }

        return hash('sha256', $normalized);
    }

    public function normalizeCountry(?string $country): string
    {
        $value = trim((string) $country);

        if ($value === '') {
            return '';
        }

        $lower = mb_strtolower($value);

        if (isset(self::COUNTRY_NAME_TO_ISO[$lower])) {
            return self::COUNTRY_NAME_TO_ISO[$lower];
        }

        if (strlen($value) === 2) {
            return strtoupper($value);
        }

        return '';
    }

    public function normalizeZip(?string $zip): string
    {
        return trim((string) $zip);
    }

    /**
     * @param array{
     *     email?: ?string,
     *     phone?: ?string,
     *     first_name?: ?string,
     *     last_name?: ?string,
     *     country?: ?string,
     *     zip?: ?string,
     * } $row
     * @return array<string, string>
     */
    public function formatRow(array $row): array
    {
        $country = $this->normalizeCountry($row['country'] ?? null);

        return [
            'Email' => $this->hashEmail($row['email'] ?? null),
            'Phone' => $this->hashPhone($row['phone'] ?? null, $country !== '' ? $country : null),
            'First Name' => $this->hashName($row['first_name'] ?? null),
            'Last Name' => $this->hashName($row['last_name'] ?? null),
            'Country' => $country,
            'Zip' => $this->normalizeZip($row['zip'] ?? null),
        ];
    }

    /**
     * @return list<string>
     */
    public function csvHeader(): array
    {
        return ['Email', 'Phone', 'First Name', 'Last Name', 'Country', 'Zip'];
    }

    private function normalizePhone(?string $phone, ?string $country): string
    {
        $raw = trim((string) $phone);

        if ($raw === '') {
            return '';
        }

        $hasPlus = str_starts_with($raw, '+');
        $digits = preg_replace('/\D+/', '', $raw) ?? '';

        if ($digits === '') {
            return '';
        }

        if ($hasPlus) {
            return '+'.$digits;
        }

        if (str_starts_with($digits, '00')) {
            return '+'.substr($digits, 2);
        }

        $countryIso = $this->normalizeCountry($country);
        $dialCode = self::COUNTRY_DIAL_CODES[$countryIso] ?? null;

        if ($dialCode === null) {
            return '';
        }

        if (str_starts_with($digits, '0')) {
            return '+'.$dialCode.substr($digits, 1);
        }

        if (str_starts_with($digits, $dialCode)) {
            return '+'.$digits;
        }

        return '';
    }
}
