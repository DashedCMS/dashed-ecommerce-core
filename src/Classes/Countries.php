<?php

namespace Dashed\DashedEcommerceCore\Classes;

use Illuminate\Support\Str;

class Countries
{
    public static function getCountryIsoCode($countryName)
    {
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
        $countryCodes = [];

        foreach (ShippingZones::getActiveRegions() as $region) {
            $countryCode = self::getCountryIsoCode($region['value']);
            if ($countryCode && ! in_array($countryCode, $countryCodes)) {
                $countryCodes[] = $countryCode;
            }
        }

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
