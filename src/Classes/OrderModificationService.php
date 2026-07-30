<?php

namespace Dashed\DashedEcommerceCore\Classes;

use Illuminate\Support\Facades\DB;
use Dashed\DashedEcommerceCore\Models\Order;
use Dashed\DashedEcommerceCore\Models\OrderLog;

/**
 * Wijzigt de inhoud van een bestelling. Onbetaalde orders zonder echte factuur
 * worden in plaats aangepast; voor de rest komt er een vervangende order waar
 * het al betaalde bedrag naartoe verrekend wordt.
 */
class OrderModificationService
{
    public static function canModifyInPlace(Order $order): bool
    {
        // Een order die al vervangen is, is afgesloten. Hem alsnog in plaats
        // aanpassen zou een achterhaalde bestelling weer tot leven wekken.
        return ! $order->replaced_by_order_id
            && ! $order->hasRealInvoice()
            && ! $order->isPaidFor()
            && ! $order->orderPayments()->where('status', 'paid')->exists();
    }

    public static function applyInPlace(Order $order, array $lines): Order
    {
        if (! self::canModifyInPlace($order)) {
            throw new \LogicException('Deze bestelling kan niet in plaats aangepast worden.');
        }

        return DB::transaction(function () use ($order, $lines) {
            self::writeLines($order, $lines);

            OrderTotalsCalculator::recalculate($order);

            // De bestaande PDF wordt niet overschreven door createInvoice(),
            // dus eerst weg met het oude bestand.
            $order->deleteInvoice();
            $order->createInvoice();

            OrderLog::createLog(orderId: $order->id, tag: 'order.modified.in-place');

            return $order->fresh();
        });
    }

    /**
     * Schrijft de regels van een order volledig opnieuw. Hard-delete van de
     * oude regels (inclusief soft-deleted resten) zodat er geen zwevende rijen
     * achterblijven, gelijk aan ConceptOrderService::saveAsConcept().
     */
    public static function writeLines(Order $order, array $lines): void
    {
        $order->orderProducts()->withTrashed()->forceDelete();

        foreach ($lines as $line) {
            $order->orderProducts()->create([
                'product_id' => $line['product_id'] ?? null,
                'name' => $line['name'] ?? 'Product',
                'quantity' => (int) ($line['quantity'] ?? 1),
                'price' => (float) ($line['price'] ?? 0),
                'vat_rate' => (float) ($line['vat_rate'] ?? 21),
                'product_extras' => $line['product_extras'] ?? [],
            ]);
        }

        $order->load('orderProducts');
    }
}
