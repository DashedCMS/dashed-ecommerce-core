<?php

namespace Dashed\DashedEcommerceCore\Services\Shipping\LabelProblems;

use Throwable;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Cache;

/**
 * Stelt een gecorrigeerd Nederlands adres voor op basis van de PDOK Locatieserver.
 */
class AddressSuggester
{
    public const URL = 'https://api.pdok.nl/bzk/locatieserver/search/v3_1/free';

    public static function suggest(string $country, ?string $street, ?string $houseNr, ?string $zip, ?string $city): ?AddressSuggestion
    {
        if (! self::isNetherlands($country)) {
            return null;
        }

        $street = trim((string) $street);
        $houseNr = trim((string) $houseNr);
        $zip = trim((string) $zip);
        $city = trim((string) $city);

        if ($street === '' || $houseNr === '' || ($city === '' && $zip === '')) {
            return null;
        }

        $hash = md5(mb_strtolower("$street|$houseNr|$zip|$city"));
        $key = 'label-problem-pdok:'.$hash;
        $failedKey = 'label-problem-pdok-failed:'.$hash;

        try {
            // Gevonden voorstel = array, geslaagde zoektocht zonder match = false
            // (sentinel, zodat een misser niet elke keer opnieuw PDOK raakt).
            $result = Cache::get($key);
            if ($result === null) {
                // Een recente HTTP-fout of uitzondering: PDOK even niet opnieuw
                // proberen, anders kost een storing 5s per banner of detail.
                if (Cache::has($failedKey)) {
                    return null;
                }

                $result = self::lookup($street, $houseNr, $zip, $city);
                if ($result === null) {
                    Cache::put($failedKey, true, now()->addMinutes(5));
                } else {
                    Cache::put($key, $result, now()->addDay());
                }
            }
        } catch (Throwable $e) {
            report($e);
            rescue(fn () => Cache::put($failedKey, true, now()->addMinutes(5)), null, false);

            return null;
        }

        return is_array($result) ? new AddressSuggestion($result['changes'], $result['label'], $result['source']) : null;
    }

    /**
     * @return array|false|null  voorstel, false bij geen match, null bij een HTTP-fout
     */
    private static function lookup(string $street, string $houseNr, string $zip, string $city): array|false|null
    {
        $response = Http::timeout(5)->get(self::URL, [
            'q' => trim("$street $houseNr ".($city !== '' ? $city : $zip)),
            'fq' => 'type:adres',
            'rows' => 10,
            'fl' => 'weergavenaam,postcode,huisnummer,huisletter,toevoeging,straatnaam,woonplaatsnaam',
        ]);

        if (! $response->successful()) {
            return null;
        }

        $suggestion = self::pick((array) $response->json('response.docs', []), $street, $houseNr, $zip, $city);

        return $suggestion?->toArray() ?? false;
    }

    private static function pick(array $docs, string $street, string $houseNr, string $zip, string $city): ?AddressSuggestion
    {
        preg_match('/^\d+/', $houseNr, $m);
        $number = $m[0] ?? null;
        $zipDigits = preg_replace('/\D/', '', $zip);

        $matches = array_values(array_filter($docs, function ($doc) use ($street, $number, $zipDigits, $city) {
            if (! is_array($doc) || $number === null || (string) ($doc['huisnummer'] ?? '') !== $number) {
                return false;
            }
            if (self::norm($doc['straatnaam'] ?? '') !== self::norm($street)) {
                return false;
            }

            $cityOk = $city !== '' && self::norm($doc['woonplaatsnaam'] ?? '') === self::norm($city);
            $zipOk = $zipDigits !== '' && str_starts_with((string) ($doc['postcode'] ?? ''), $zipDigits);

            return $cityOk || $zipOk;
        }));

        $postcodes = array_unique(array_map(fn ($d) => (string) ($d['postcode'] ?? ''), $matches));
        if (count($postcodes) !== 1 || reset($postcodes) === '') {
            return null;
        }

        $doc = $matches[0];
        $newZip = self::formatZip($doc['postcode']);
        $newStreet = (string) ($doc['straatnaam'] ?? $street);
        $newCity = (string) ($doc['woonplaatsnaam'] ?? $city);

        $changes = [];
        if (self::norm($zip) !== self::norm($newZip)) {
            $changes['zip_code'] = $newZip;
        }
        if ($street !== $newStreet) {
            $changes['street'] = $newStreet;
        }
        if ($city !== $newCity) {
            $changes['city'] = $newCity;
        }

        if ($changes === []) {
            return null;
        }

        return new AddressSuggestion($changes, "$newStreet $houseNr, $newZip $newCity", 'PDOK');
    }

    private static function formatZip(string $zip): string
    {
        return preg_replace('/^(\d{4})\s*([A-Za-z]{2})$/', '$1 '.'$2', strtoupper($zip)) ?: $zip;
    }

    private static function norm(string $value): string
    {
        return mb_strtolower(preg_replace('/\s+/', '', $value));
    }

    private static function isNetherlands(string $country): bool
    {
        return in_array(mb_strtolower(trim($country)), ['nl', 'nederland', 'netherlands', 'the netherlands'], true);
    }
}
