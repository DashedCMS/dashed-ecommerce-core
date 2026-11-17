<?php

namespace Dashed\DashedEcommerceCore\Services\Shipping\LabelProblems;

/**
 * Haalt uit een ruwe carrier-foutmelding (Veloyd/PostNL/MyParcel) de kern
 * en het adresveld waar het om gaat. De Nederlandse zin bouwt de detector.
 */
class CarrierErrorParser
{
    public static function parse(string $raw): ParsedCarrierError
    {
        $description = self::extractDescription($raw);

        return new ParsedCarrierError($description, self::field($description), $raw);
    }

    private static function field(string $description): ?string
    {
        return match (true) {
            (bool) preg_match('/house ?number|housenumber|huisnummer/i', $description) => 'house_nr',
            (bool) preg_match('/postcode|postal ?code|\bzip/i', $description) => 'zip_code',
            (bool) preg_match('/street|straat/i', $description) => 'street',
            (bool) preg_match('/\bcity\b|woonplaats|plaats/i', $description) => 'city',
            default => null,
        };
    }

    private static function extractDescription(string $raw): string
    {
        $raw = trim($raw);
        // Veloyd-excepties plakken een voorvoegsel voor de body: pak vanaf de eerste accolade.
        $found = self::fromJson($raw);

        if ($found === null && ($pos = strpos($raw, '{')) !== false) {
            $found = self::fromJson(substr($raw, $pos));
        }

        return self::clean($found ?? $raw);
    }

    private static function fromJson(string $text): ?string
    {
        $data = json_decode($text, true);

        if (! is_array($data)) {
            return null;
        }

        // Veloyd: {"error":..,"description":"PostNL foutmelding: 400 {...}"}
        if (isset($data['description']) && is_string($data['description'])) {
            $inner = $data['description'];
            if (($pos = strpos($inner, '{')) !== false && ($nested = self::fromJson(substr($inner, $pos))) !== null) {
                return $nested;
            }

            return $inner;
        }

        // PostNL: {"Errors":[{"Description":..}]}
        foreach (['Errors', 'errors'] as $key) {
            foreach ((array) ($data[$key] ?? []) as $error) {
                if (! is_array($error)) {
                    continue;
                }
                foreach (['Description', 'human', 'message', 'description'] as $field) {
                    if (! empty($error[$field]) && is_string($error[$field])) {
                        return $error[$field];
                    }
                }
            }
        }

        foreach (['message', 'error'] as $key) {
            if (! empty($data[$key]) && is_string($data[$key])) {
                return $data[$key];
            }
        }

        return null;
    }

    private static function clean(string $text): string
    {
        $text = preg_replace('/Validation failed for shipment:?\s*\S*/i', '', $text);
        $text = trim(preg_replace('/\s+/', ' ', $text), " \t\n\r:;,-");

        return $text;
    }
}
