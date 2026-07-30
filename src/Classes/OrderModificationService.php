<?php

namespace Dashed\DashedEcommerceCore\Classes;

use Illuminate\Support\Facades\DB;
use Dashed\DashedEcommerceCore\Models\Order;
use Dashed\DashedEcommerceCore\Models\OrderLog;
use Dashed\DashedEcommerceCore\Events\Orders\OrderModifiedEvent;
use Dashed\DashedEcommerceCore\Events\Orders\OrderMarkedAsPaidEvent;

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

    public static function replaceWithNewOrder(Order $order, array $lines, array $options = []): Order
    {
        if ($order->replaced_by_order_id) {
            throw new \LogicException('Deze bestelling is al vervangen door een andere bestelling.');
        }

        $alreadyShipped = (bool) ($options['already_shipped'] ?? false);
        $productsMustBeReturned = (bool) ($options['products_must_be_returned'] ?? false);
        $creditOldOrder = $options['credit_old_order'] ?? $order->hasRealInvoice();
        $deductNewStock = (bool) ($options['deduct_new_stock'] ?? true);

        return DB::transaction(function () use ($order, $lines, $alreadyShipped, $productsMustBeReturned, $creditOldOrder, $deductNewStock) {
            // 1. Nieuwe order. invoice_id expliciet op PROFORMA, want
            // generateInvoiceId() deelt alleen een nieuw nummer uit aan orders
            // met PROFORMA of RETURN. Zonder dit erft de kopie het oude nummer.
            $newOrder = $order->replicate(['credit_for_order_id', 'replaced_by_order_id']);
            $newOrder->invoice_id = 'PROFORMA';
            $newOrder->status = 'pending';
            $newOrder->fulfillment_status = 'unhandled';
            $newOrder->retour_status = null;
            $newOrder->save();

            // De creating-hook op Order overschrijft site_id, locale en ip
            // onvoorwaardelijk met de actieve site, de huidige app-locale en
            // het IP van de inloggende beheerder. Die overschrijving gebeurde
            // dus net in de save() hierboven; nu pas de waarden van de
            // oorspronkelijke klant/order terugzetten en opnieuw opslaan (een
            // update, geen insert, dus de hook grijpt niet nogmaals in).
            $newOrder->site_id = $order->site_id;
            $newOrder->locale = $order->locale;
            $newOrder->ip = $order->ip;
            $newOrder->save();

            self::writeLines($newOrder, $lines);
            OrderTotalsCalculator::recalculate($newOrder);

            $order->replaced_by_order_id = $newOrder->id;
            $order->save();

            OrderLog::createLog(orderId: $order->id, tag: 'order.modified', note: 'Vervangen door bestelling '.$newOrder->id);
            OrderLog::createLog(orderId: $newOrder->id, tag: 'order.modified.replacement', note: 'Vervangt bestelling '.$order->id);

            // 2. Oude order afsluiten. Dit moet VOOR het verrekenen: beide
            // methodes vuren een OrderCancelledEvent en de abandoned-cart
            // listener haakt alleen af zolang de order nog betaalde
            // betalingen heeft.
            if ($creditOldOrder) {
                self::creditOldOrder($order, $newOrder, $alreadyShipped, $productsMustBeReturned);
            } else {
                $order->markAsCancelled(sendMail: false, refillStock: ! $alreadyShipped);
                $order->orderPayments()->where('status', 'paid')->update(['order_id' => $newOrder->id]);
            }

            if ($productsMustBeReturned) {
                $order->retour_status = 'waiting_for_return';
                $order->save();
            }

            // 3. Status, factuur en voorraad van de nieuwe order. Bewust niet
            // via markAsPaid(): die verstuurt een factuurmail, leegt
            // winkelwagens en stuurt een GA-omzethit die dubbel zou tellen.
            $newOrder->refresh();
            $newOrder->status = $newOrder->outstandingAmount() <= 0.001 ? 'paid' : 'partially_paid';
            $newOrder->save();
            $newOrder->createInvoice();

            if ($deductNewStock) {
                $newOrder->deductStock();
            }

            if (! $creditOldOrder) {
                // markAsCancelled() heeft refillDiscount() gedraaid, dus de
                // teller moet er hier weer af. markAsCancelledWithCredit()
                // raakt de kortingstellers niet aan, daar dus niet.
                $newOrder->deductDiscount();
            }

            $newOrder->refresh();

            OrderModifiedEvent::dispatch($newOrder, $order->fresh());

            if ($newOrder->status === 'paid') {
                OrderMarkedAsPaidEvent::dispatch($newOrder);
            }

            return $newOrder;
        });
    }

    /**
     * Sluit de oude order af met een creditorder. De oude order blijft bewust
     * op 'paid' staan met zijn eigen betalingen; markAsCancelledWithCredit()
     * zet hem netto op nul met een negatieve creditorder. Het al betaalde
     * bedrag wordt daarom niet verplaatst maar verrekend met twee
     * tegenboekingen, zodat de som over alle orders blijft kloppen:
     * oude order plus, creditorder min, nieuwe order plus.
     */
    protected static function creditOldOrder(Order $order, Order $newOrder, bool $alreadyShipped, bool $productsMustBeReturned): void
    {
        $paidAmount = (float) $order->orderPayments()->where('status', 'paid')->sum('amount');

        $chosenOrderProducts = $order->orderProducts()->get();
        foreach ($chosenOrderProducts as $orderProduct) {
            $orderProduct->refundQuantity = $orderProduct->quantity;
        }

        $creditOrder = $order->markAsCancelledWithCredit(
            sendCustomerEmail: false,
            productsMustBeReturned: $productsMustBeReturned,
            restock: ! $alreadyShipped,
            refundDiscountCosts: false,
            extraOrderLineName: null,
            extraOrderLinePrice: 0,
            chosenOrderProducts: $chosenOrderProducts,
            fulfillmentStatus: 'handled',
            paymentMethodId: null,
        );

        if ($paidAmount <= 0) {
            return;
        }

        $creditOrder->orderPayments()->create([
            'status' => 'paid',
            'amount' => round((float) $creditOrder->total, 2),
            'psp' => 'own',
            'payment_method' => 'verrekening',
            'attributes' => [
                'verrekend_met_order_id' => $newOrder->id,
            ],
        ]);

        $newOrder->orderPayments()->create([
            'status' => 'paid',
            'amount' => round($paidAmount, 2),
            'psp' => 'own',
            'payment_method' => 'verrekening',
            'attributes' => [
                'verrekend_vanuit_order_id' => $order->id,
                'creditorder_id' => $creditOrder->id,
            ],
        ]);
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
