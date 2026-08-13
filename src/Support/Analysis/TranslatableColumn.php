<?php

namespace Dashed\DashedEcommerceCore\Support\Analysis;

/**
 * Leesbare tekst uit een vertaalbare JSON-kolom (spatie/laravel-translatable).
 *
 * Geen van de analyses is eigenaar van "decodeer een vertaalbare kolom", dus
 * staat die logica hier los, in plaats van dat de ene analyse een methode
 * van de andere aanroept.
 */
class TranslatableColumn
{
    /**
     * Decodeert de kolomwaarde en pakt de huidige app-locale. Valt terug op
     * de ruwe waarde wanneer het geen JSON is, want oudere rijen bevatten
     * gewone tekst in plaats van een vertaalbare kolom. Valt daarna terug op
     * de eerste waarde in de JSON wanneer de huidige locale er niet in zit,
     * voor een groep die nog niet in die taal vertaald is.
     */
    public static function value(string $raw): string
    {
        $decoded = json_decode($raw, true);

        if (! is_array($decoded) || $decoded === []) {
            return $raw;
        }

        return (string) ($decoded[app()->getLocale()] ?? reset($decoded));
    }
}
