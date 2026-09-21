<?php

namespace Dashed\DashedEcommerceCore\Classes;

use Illuminate\Support\Str;
use Illuminate\Support\Facades\Cache;

class Countries
{
    /**
     * Nederlandse en gangbare Engelse namen die countries.json niet kent. Het
     * factuurland in de checkout is een vrij tekstveld, en zonder deze lijst
     * werd bijvoorbeeld "Duitsland" geen landcode. Pay.nl laat de landcode
     * dan weg en Riverty weigert de betaling.
     */
    public const NAMES = [
        'the netherlands' => 'NL',
        'holland' => 'NL',
        'duitsland' => 'DE',
        'oostenrijk' => 'AT',
        'zwitserland' => 'CH',
        'frankrijk' => 'FR',
        'spanje' => 'ES',
        'italië' => 'IT',
        'italie' => 'IT',
        'engeland' => 'GB',
        'verenigd koninkrijk' => 'GB',
        'groot-brittannië' => 'GB',
        'groot-brittannie' => 'GB',
        'ierland' => 'IE',
        'denemarken' => 'DK',
        'zweden' => 'SE',
        'noorwegen' => 'NO',
        'polen' => 'PL',
        'tsjechië' => 'CZ',
        'tsjechie' => 'CZ',
        'slowakije' => 'SK',
        'hongarije' => 'HU',
        'roemenië' => 'RO',
        'roemenie' => 'RO',
        'bulgarije' => 'BG',
        'griekenland' => 'GR',
        'kroatië' => 'HR',
        'kroatie' => 'HR',
        'slovenië' => 'SI',
        'slovenie' => 'SI',
        'litouwen' => 'LT',
        'letland' => 'LV',
        'estland' => 'EE',
        'luxemburg' => 'LU',
        'verenigde staten' => 'US',
        'amerika' => 'US',
    ];

    public static function getCountryIsoCode($countryName)
    {
        $countryName = rtrim(trim((string) $countryName), '.');

        if ($countryName === '') {
            return null;
        }

        if (isset(self::NAMES[mb_strtolower($countryName)])) {
            return self::NAMES[mb_strtolower($countryName)];
        }

        $activeCountry = false;
        foreach (self::getCountries() as $country) {
            if (strtolower($country['name']) == strtolower($countryName)) {
                $activeCountry = true;
            }
            if (strtolower($country['alpha2Code']) == strtolower($countryName)) {
                $activeCountry = true;
            }
            if (strtolower($country['alpha3Code']) == strtolower($countryName)) {
                $activeCountry = true;
            }
            if (strtolower($country['demonym']) == strtolower($countryName)) {
                $activeCountry = true;
            }
            foreach ($country['altSpellings'] as $altSpelling) {
                if (strlen($countryName) > 5) {
                    if (Str::contains(strtolower($altSpelling), strtolower($countryName))) {
                        $activeCountry = true;
                    }
                } else {
                    if (strtolower($altSpelling) == strtolower($countryName)) {
                        $activeCountry = true;
                    }
                }
            }
            if ($activeCountry) {
                return $country['alpha2Code'];
            }
        }
    }

    public static function getCountries()
    {
        return json_decode(file_get_contents(__DIR__ . '/countries.json'), true);
    }

    public static function getAllSelectedCountryCodes(): array
    {
        $countryCodes = Cache::rememberForever('all-selected-country-codes', function () {
            $countryCodes = [];

            foreach (ShippingZones::getActiveRegions() as $region) {
                $countryCode = self::getCountryIsoCode($region['value']);
                if ($countryCode && ! in_array($countryCode, $countryCodes)) {
                    $countryCodes[] = $countryCode;
                }
            }

            return $countryCodes;
        });

        return $countryCodes;
    }

    public static function getAllSelectedCountries(): array
    {
        $countries = [];

        $allCountries = collect(self::getCountries());

        foreach (ShippingZones::getActiveRegions() as $region) {
            $countries[] = $allCountries->where('name', $region['value'])
                ->first()['nativeName'] ?? 'Onbekend';
        }

        return $countries;
    }
}
