<?php

namespace Dashed\DashedEcommerceCore\Classes;

use Illuminate\Http\Request;
use Dashed\DashedCore\Models\User;
use Dashed\DashedEcommerceCore\Models\Quote;

/**
 * Wie een offerte-PDF mag downloaden.
 *
 * Het bestand staat prive op de schijf, dus de downloadroute is de enige weg
 * ernaartoe. Standaard is de offertehash de sleutel: 32 willekeurige tekens,
 * niet te raden, en de route zit achter de verzoeklimiet dashed-order-pages.
 * Dat moet zo, want de meeste ontvangers van een offerte hebben geen account
 * en openen het document uit hun mail, ook maanden later.
 *
 * De links die het systeem zelf uitdeelt zijn daarnaast ondertekend. Met
 * dashed-ecommerce-core.quotes.require_signature op true eist de route die
 * handtekening ook, tenzij de ingelogde gebruiker de klant van de offerte of
 * een beheerder is.
 */
class QuoteAccess
{
    public static function requiresSignature(): bool
    {
        return filter_var(config('dashed-ecommerce-core.quotes.require_signature', false), FILTER_VALIDATE_BOOL);
    }

    public static function allows(Request $request, Quote $quote): bool
    {
        if (! self::requiresSignature()) {
            return true;
        }

        if ($request->hasValidSignature()) {
            return true;
        }

        $user = $request->user();

        if (! $user instanceof User) {
            return false;
        }

        if ($quote->user_id && (int) $quote->user_id === (int) $user->getKey()) {
            return true;
        }

        return self::isAdmin($user);
    }

    /**
     * Het voorbeeld dat een beheerder maakt is geen klantdocument: het kan
     * inhoud bevatten die nog niet verstuurd is. Daarvoor is de hash alleen dus
     * niet genoeg, ook niet als de handtekening voor de klantdocumenten
     * uitstaat.
     */
    public static function allowsPreview(Request $request): bool
    {
        return $request->hasValidSignature() || self::isAdmin($request->user());
    }

    private static function isAdmin(mixed $user): bool
    {
        if (! $user instanceof User) {
            return false;
        }

        return in_array($user->role, ['superadmin', 'admin'], true) || $user->roles()->exists();
    }
}
