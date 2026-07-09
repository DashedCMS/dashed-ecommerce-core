<?php

declare(strict_types=1);

namespace Dashed\DashedEcommerceCore\Support;

use Throwable;
use Dashed\DashedEcommerceCore\Models\OrderReturn;

/**
 * Best-effort app-push bij retour-gebeurtenissen. Doet niets als de
 * mobile-api (NotificationCenter) niet is geïnstalleerd; fouten worden
 * gerapporteerd maar nooit doorgegooid (mag de retour-flow niet breken).
 */
class ReturnNotifier
{
    private const CENTER = \Dashed\DashedMobileApi\Support\NotificationCenter::class;

    public static function requested(OrderReturn $return): void
    {
        self::push($return, 'return.requested', 'Nieuw retourverzoek', self::body($return));
    }

    public static function autoApproved(OrderReturn $return): void
    {
        self::push($return, 'return.auto_approved', 'Retour automatisch goedgekeurd', self::body($return));
    }

    public static function labelFailed(OrderReturn $return): void
    {
        self::push($return, 'return.label_failed', 'Retourlabel mislukt', 'Het retourlabel kon niet worden aangemaakt — handmatig oppakken.');
    }

    private static function body(OrderReturn $return): string
    {
        $ref = $return->order?->invoice_id ?: ('#' . $return->order_id);

        return "Bestelling {$ref} — {$return->email}";
    }

    private static function push(OrderReturn $return, string $type, string $title, string $body): void
    {
        if (! class_exists(self::CENTER)) {
            return;
        }
        try {
            app(self::CENTER)->push()
                ->type($type)
                ->site($return->site_id)
                ->title($title)
                ->body($body)
                ->route("/return/{$return->id}")
                ->data(['type' => 'return', 'id' => $return->id])
                ->toAbility('orders.read')
                ->send();
        } catch (Throwable $e) {
            report($e);
        }
    }
}
