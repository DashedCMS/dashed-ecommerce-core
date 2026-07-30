<?php

namespace Dashed\DashedEcommerceCore\Classes;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Dashed\DashedEcommerceCore\Models\Order;
use Dashed\DashedEcommerceCore\Models\OrderLog;
use Dashed\DashedEcommerceCore\Models\OrderProduct;
use Dashed\DashedEcommerceCore\Mail\OrderModifiedMail;
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
        // isModifiable() eerst: het wijzigscherm bewaakt dat al, maar deze
        // methode is publiek en statisch en wordt ook rechtstreeks aangeroepen.
        // Zonder deze check zou applyInPlace() een geannuleerde, geretourneerde
        // of credit-order alsnog herschrijven.
        //
        // Een order die al vervangen is, is afgesloten. Hem alsnog in plaats
        // aanpassen zou een achterhaalde bestelling weer tot leven wekken
        // (zit ook in isModifiable(), hier expliciet gelaten omdat het de kern
        // van deze guard is).
        return $order->isModifiable()
            && ! $order->replaced_by_order_id
            && ! $order->hasRealInvoice()
            && ! $order->isPaidFor()
            && ! $order->orderPayments()->where('status', 'paid')->exists();
    }

    public static function applyInPlace(Order $order, array $lines, array $options = []): Order
    {
        if (! self::canModifyInPlace($order)) {
            throw new \LogicException('Deze bestelling kan niet in plaats aangepast worden.');
        }

        return DB::transaction(function () use ($order, $lines, $options) {
            self::writeLines($order, $lines);

            OrderTotalsCalculator::recalculate($order);

            // De bestaande PDF wordt niet overschreven door createInvoice(),
            // dus eerst weg met het oude bestand. Maar alleen wanneer
            // createInvoice() ook echt iets terugbouwt: die maakt enkel een
            // document voor concepten (createConceptConfirmation) en voor
            // paid/waiting_for_confirmation/partially_paid. Een 'pending' of
            // 'expired' order — allebei toegestaan door canModifyInPlace() —
            // zou anders zijn PDF kwijtraken zonder er een terug te krijgen.
            if ($order->isConcept() || in_array($order->status, ['paid', 'waiting_for_confirmation', 'partially_paid'], true)) {
                $order->deleteInvoice();
                $order->createInvoice();
            }

            OrderLog::createLog(orderId: $order->id, tag: 'order.modified.in-place');

            self::sendCustomerMail($order->fresh(), $options);

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

        return DB::transaction(function () use ($order, $lines, $options, $alreadyShipped, $productsMustBeReturned, $creditOldOrder, $deductNewStock) {
            // 1. Nieuwe order. invoice_id expliciet op PROFORMA, want
            // generateInvoiceId() deelt alleen een nieuw nummer uit aan orders
            // met PROFORMA of RETURN. Zonder dit erft de kopie het oude nummer.
            // Niet meekopiëren naar de vervanger: packed_at (deze order is nog
            // niet ingepakt), invoice_send_to_customer (de nieuwe factuur is nog
            // niet verstuurd), is_proforma/proforma_sent_at (de vervanger wordt
            // direct als gewone factuur uitgegeven, niet als proforma-offerte, en
            // mag dus ook niet via de proforma-checkout-URL benaderbaar zijn) en
            // cart_id (die winkelwagen hoort bij de oorspronkelijke checkout).
            // ga_commerce_hit_send blijft bewust wél staan: die vlag voorkomt dat
            // er een tweede GA-omzethit voor dezelfde omzet uitgaat.
            $newOrder = $order->replicate([
                'credit_for_order_id',
                'replaced_by_order_id',
                'packed_at',
                'invoice_send_to_customer',
                'is_proforma',
                'proforma_sent_at',
                'cart_id',
            ]);
            $newOrder->invoice_id = 'PROFORMA';
            $newOrder->status = 'pending';
            $newOrder->fulfillment_status = 'unhandled';
            $newOrder->retour_status = null;

            // De korting hier tijdelijk op 0. De created-hook op Order
            // reserveert bij een cadeaubon opnieuw zodra een order met een
            // korting groter dan nul wordt aangemaakt, en dat is precies wat
            // hier niet mag: de klant heeft die cadeaubon één keer uitgegeven
            // en de vervanger neemt diezelfde uitgave over, hij doet er geen
            // tweede. Samen met de refillDiscount/refillGiftcard-vlaggen bij
            // het afsluiten van de oude order blijft de hele kortingsboekhouding
            // (discount_amount, used_amount, reserved_amount, stock_used) van
            // een wijziging onaangeraakt. De echte korting komt hieronder
            // terug, in een update waar de hook niet meer op afgaat.
            $newOrder->discount = 0;
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
            // Moet vóór recalculate(): die leest $order->discount als de
            // gekoppelde kortingscode een vast bedrag heeft.
            $newOrder->discount = $order->discount;
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
                // refillDiscount: false — de vervangende order erft
                // discount_code_id en discount via replicate(), dus de
                // kortingscode blijft gewoon verbruikt. Zou de oude order hem
                // teruggeven, dan zouden stock/stock_used en (bij een
                // cadeaubon) discount_amount/used_amount/reserved_amount uit
                // de pas lopen met de werkelijkheid. Hier niet teruggeven en
                // op de vervanger niet opnieuw afboeken houdt alle tellers
                // per saldo gelijk, voor élke status die deze tak haalt
                // (paid, partially_paid én waiting_for_confirmation).
                $order->markAsCancelled(sendMail: false, refillStock: ! $alreadyShipped, refillDiscount: false);
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

            // Wat markAsPaid() als eerste doet en hier dus ook moet gebeuren:
            // de omzet hoort te tellen op de eerste betaaldatum. De betalingen
            // zijn hierboven al verhuisd (annuleertak) of aangemaakt
            // (credittak), dus de oorspronkelijke betaaldatums staan er nu op.
            // Zonder dit zou een in mei betaalde en in juli gewijzigde order
            // zijn omzet naar juli verplaatsen terwijl de oude order als
            // 'cancelled' uit de statistieken valt. force: true omdat de
            // vervanger nooit de status 'concept' heeft gehad.
            $newOrder->alignCreatedAtToFirstPayment(force: true);
            $newOrder->save();
            $newOrder->createInvoice();

            if ($deductNewStock) {
                $newOrder->deductStock();
            }

            // Bewust géén deductDiscount() hier: geen van beide takken geeft de
            // kortingscode terug (zie de refillDiscount/refillGiftcard-vlaggen
            // hierboven), dus er valt ook niets opnieuw af te boeken. De code
            // blijft precies één keer verbruikt, door de order die hem draagt.

            $newOrder->refresh();

            OrderModifiedEvent::dispatch($newOrder, $order->fresh());

            if ($newOrder->status === 'paid') {
                OrderMarkedAsPaidEvent::dispatch($newOrder);
            }

            self::sendCustomerMail($newOrder, $options);

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
            // Bewust de huidige status en niet 'handled'. changeFulfillmentStatus()
            // stopt bij gelijke oude en nieuwe status, dus hiermee blijft er per
            // constructie zowel de FulfillmentStatusHandledMail naar de klant weg
            // (die zou zeggen dat de bestelling klaar is terwijl er een vervanger
            // openstaat die mogelijk nog betaald moet worden) als de
            // OrderFulfillmentStatusChangedEvent die de klant via
            // QueueOrderFlowEmailsListener in de na-aankoop-flows zou zetten voor
            // een order die net weggecrediteerd is.
            fulfillmentStatus: $order->fulfillment_status,
            paymentMethodId: null,
            // Geen annuleringsmail naar de beheerders: dit is een wijziging, geen
            // annulering.
            sendAdminEmail: false,
            // De vervangende order erft dezelfde kortingscode, dus een cadeaubon
            // is nog steeds verbruikt en mag niet teruggestort worden.
            refillGiftcard: false,
        );

        if ($paidAmount <= 0) {
            return;
        }

        // De tegenboeking staat voor geld dat verhuist, niet voor de waarde van
        // de creditregels. Bij een deels betaalde factuur is dat minder dan het
        // negatieve totaal van de creditorder; zou je dat totaal boeken, dan
        // telt de som over alle orders niet meer op tot wat de klant betaald
        // heeft en claimt de creditorder een teruggave die nooit betaald is.
        $creditOrder->orderPayments()->create([
            'status' => 'paid',
            'amount' => round(0 - $paidAmount, 2),
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
        // Velden die het wijzigformulier niet toont maar die wel bepalend zijn
        // voor de rest van het systeem, worden van de bronregel overgenomen via
        // order_product_id. Vooral sku: verzend- en betaalkosten zijn gewone
        // orderregels die alleen aan sku = 'shipping_costs' / 'payment_costs'
        // te herkennen zijn. Raakt die kwijt en de omzetstatistieken tellen de
        // verzendomzet van deze order als nul, terwijl `sku NOT IN (...)` bij
        // een NULL nooit waar is en dus élke regel van deze order uit de
        // verkochte-aantallen valt.
        //
        // De bronregels moeten opgehaald worden vóór de forceDelete hieronder:
        // bij applyInPlace() zijn dat de regels van deze order zelf.
        $sourceIds = collect($lines)
            ->pluck('order_product_id')
            ->filter()
            ->all();

        $sources = $sourceIds
            ? OrderProduct::withTrashed()->whereIn('id', $sourceIds)->get()->keyBy('id')
            : collect();

        $order->orderProducts()->withTrashed()->forceDelete();

        foreach ($lines as $line) {
            $source = $sources->get($line['order_product_id'] ?? null);

            $order->orderProducts()->create([
                'product_id' => $line['product_id'] ?? null,
                'name' => $line['name'] ?? 'Product',
                'quantity' => (int) ($line['quantity'] ?? 1),
                'price' => (float) ($line['price'] ?? 0),
                'vat_rate' => (float) ($line['vat_rate'] ?? 21),
                'product_extras' => $line['product_extras'] ?? [],
                'sku' => $line['sku'] ?? $source?->sku,
                'discount' => $line['discount'] ?? $source?->discount ?? 0,
                'is_pre_order' => $line['is_pre_order'] ?? $source?->is_pre_order ?? 0,
            ]);
        }

        $order->load('orderProducts');
    }

    protected static function sendCustomerMail(Order $order, array $options): void
    {
        if (! ($options['send_customer_email'] ?? true) || blank($order->email)) {
            return;
        }

        try {
            Mail::to($order->email)->send(new OrderModifiedMail($order, $options['customer_note'] ?? null));
        } catch (\Throwable $e) {
            OrderLog::createLog(orderId: $order->id, tag: 'order.modified.mail.send.failed', note: 'Error: ' . $e->getMessage());
        }
    }
}
