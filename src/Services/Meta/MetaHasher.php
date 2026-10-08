<?php

namespace Dashed\DashedEcommerceCore\Services\Meta;

use Illuminate\Support\Str;
use Dashed\DashedEcommerceCore\Classes\PhoneNormalizer;

/**
 * Normalisatie en SHA-256 volgens de klantparameters van Meta's Conversions
 * API. Een lege genormaliseerde waarde geeft null: een hash van een lege
 * string verlaagt de matchkwaliteit en mag nooit verstuurd worden.
 */
class MetaHasher
{
    /** Landcode plus abonneenummer: korter is geen bruikbaar telefoonnummer. */
    protected const MIN_PHONE_DIGITS = 8;

    public static function hash(?string $normalized): ?string
    {
        $normalized = trim((string) $normalized);

        return $normalized === '' ? null : hash('sha256', $normalized);
    }

    public static function email(?string $email): ?string
    {
        return static::hash(mb_strtolower(trim((string) $email)));
    }

    /**
     * Alleen cijfers, met landcode, zonder voorloopnullen. Zonder landcode
     * (onbekend land en een nationaal nummer) of met minder dan acht cijfers
     * geeft dit null: zo'n hash matcht bij Meta nooit.
     */
    public static function phone(?string $phone, ?string $countryCode): ?string
    {
        $e164 = PhoneNormalizer::toE164($phone, $countryCode);
        $digits = (string) preg_replace('/\D/', '', $e164);

        if (! str_starts_with($e164, '+') || strlen($digits) < self::MIN_PHONE_DIGITS) {
            return null;
        }

        return static::hash(ltrim($digits, '0'));
    }

    /** Voor- of achternaam: kleine letters, accenten naar ASCII, geen leestekens. */
    public static function name(?string $name): ?string
    {
        $name = mb_strtolower(Str::ascii(trim((string) $name)));
        $name = preg_replace('/[^a-z\s]/', '', $name);

        return static::hash(preg_replace('/\s+/', ' ', (string) $name));
    }

    /** Plaats: als naam, maar ook zonder spaties. */
    public static function city(?string $city): ?string
    {
        $city = mb_strtolower(Str::ascii(trim((string) $city)));

        return static::hash(preg_replace('/[^a-z]/', '', $city));
    }

    public static function zip(?string $zip): ?string
    {
        return static::hash(preg_replace('/[\s\-]/', '', mb_strtolower(trim((string) $zip))));
    }

    public static function country(?string $isoCode): ?string
    {
        return static::hash(mb_strtolower(trim((string) $isoCode)));
    }

    public static function externalId(int|string|null $id): ?string
    {
        return static::hash(mb_strtolower(trim((string) $id)));
    }
}
