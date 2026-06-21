<?php

namespace Dashed\DashedEcommerceCore\Models;

use Dashed\DashedCore\Classes\Sites;
use Illuminate\Database\Eloquent\Model;

/**
 * Legt vast waarom een klant in de checkout strandde vóórdat er een order werd
 * aangemaakt (bijv. geen verzendmethode, ongeldig btw-id, betaling starten mislukt).
 * Voedt het pre-order uitval-overzicht.
 */
class CheckoutAbandonment extends Model
{
    protected $table = 'dashed__checkout_abandonments';

    protected $fillable = [
        'site_id',
        'cart_id',
        'email',
        'reason',
        'context',
        'cart_total',
    ];

    protected $casts = [
        'context' => 'array',
        'cart_total' => 'decimal:2',
    ];

    /**
     * Aantal minuten waarbinnen dezelfde (cart, reden) niet opnieuw wordt gelogd,
     * zodat herhaald klikken op "Afrekenen" geen ruis oplevert.
     */
    public const DEDUPE_MINUTES = 30;

    /**
     * Registreer een uitval-event. Idempotent per (cart_id, reason) binnen
     * DEDUPE_MINUTES. Geeft null terug als het door dedupe is overgeslagen.
     */
    public static function record(
        string $reason,
        array $context = [],
        ?int $cartId = null,
        ?string $email = null,
        ?float $cartTotal = null,
        ?string $siteId = null,
    ): ?self {
        $siteId = $siteId ?: Sites::getActive();

        if ($cartId) {
            $recentlyLogged = static::query()
                ->where('cart_id', $cartId)
                ->where('reason', $reason)
                ->where('created_at', '>=', now()->subMinutes(self::DEDUPE_MINUTES))
                ->exists();

            if ($recentlyLogged) {
                return null;
            }
        }

        return static::create([
            'site_id' => $siteId,
            'cart_id' => $cartId,
            'email' => $email,
            'reason' => $reason,
            'context' => $context ?: null,
            'cart_total' => $cartTotal,
        ]);
    }
}
