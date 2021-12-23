<?php

namespace Qubiqx\QcommerceEcommerceCore\Classes;

use Illuminate\Support\Str;

class Countries
{
    public static function getCountries()
    {
        return json_decode(file_get_contents('../vendor/qubiqx/qcommerce-ecommerce-core/src/Classes/countries.json'), true);
    }

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
}
