<?php

namespace Dashed\DashedEcommerceCore\Classes;

use Illuminate\Http\Request;
use Dashed\DashedCore\Models\User;
use Dashed\DashedEcommerceCore\Models\Order;

/**
 * Wie een factuur of pakbon van een bestelling mag downloaden.
 *
 * De bestanden staan prive op de schijf, dus de downloadroute is de enige weg
 * ernaartoe. Standaard is de orderhash de sleutel: 32 willekeurige tekens,
 * niet te raden, en de route zit achter de verzoeklimiet dashed-order-pages.
 * Dat moet zo, want de meeste klanten hebben geen account en openen de
 * factuur uit hun bevestigingsmail, ook maanden later en ook uit mails van
 * voor deze versie.
 *
 * De links die het systeem zelf uitdeelt zijn daarnaast ondertekend. Met
 * dashed-ecommerce-core.invoices.require_signature op true eist de route die
 * handtekening ook, tenzij de ingelogde gebruiker de klant van de bestelling
 * of een beheerder is. Dan werken oude, onondertekende links uit eerder
 * verstuurde mails niet meer voor gasten; dat is de ruil.
 */
class InvoiceAccess
{
    public static function requiresSignature(): bool
    {
        return filter_var(config('dashed-ecommerce-core.invoices.require_signature', false), FILTER_VALIDATE_BOOL);
    }

    public static function allows(Request $request, Order $order): bool
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

        if ($order->user_id && (int) $order->user_id === (int) $user->getKey()) {
            return true;
        }

        return in_array($user->role, ['superadmin', 'admin'], true) || $user->roles()->exists();
    }
}
