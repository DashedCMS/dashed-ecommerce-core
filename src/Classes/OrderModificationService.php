<?php

namespace Dashed\DashedEcommerceCore\Classes;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Dashed\DashedEcommerceCore\Models\Order;
use Dashed\DashedEcommerceCore\Models\Product;
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
        // voor de rest van het systeem (sku, discount, is_pre_order) worden van
        // de bronregel overgenomen via order_product_id — maar alleen zolang de
        // regel nog over hetzelfde product gaat, zie skuForLine().
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
            // is_pre_order hoort bij de bronregel: de nalevering van dít
            // product. Op een ander product heeft dat geen betekenis meer, dus
            // het volgt dezelfde overname-regel als de sku.
            $inherited = self::lineFollowsSource($line, $source) ? $source : null;

            $order->orderProducts()->create([
                'product_id' => $line['product_id'] ?? null,
                'name' => $line['name'] ?? 'Product',
                'quantity' => (int) ($line['quantity'] ?? 1),
                'price' => (float) ($line['price'] ?? 0),
                'vat_rate' => (float) ($line['vat_rate'] ?? 21),
                'product_extras' => $line['product_extras'] ?? [],
                'sku' => self::skuForLine($line, $source),
                'discount' => self::discountForLine($line, $source),
                'is_pre_order' => $line['is_pre_order'] ?? $inherited?->is_pre_order ?? 0,
            ]);
        }

        $order->load('orderProducts');
    }

    /**
     * De korting die op deze regel is afgegaan, zoals hij weggeschreven wordt.
     *
     * De regelprijs is de prijs ná korting (zie OrderTotalsCalculator), dus deze
     * waarde bepaalt niet wat de klant betaalt maar alleen hoe het subtotaal en
     * de kortingsregel op de factuur zijn opgebouwd. Drie gevallen:
     *
     * 1. De aanroeper geeft zelf een korting mee. Die wint altijd.
     * 2. De regel gaat nog over hetzelfde product als de bronregel: de korting
     *    schaalt mee met de prijs. Halveert de beheerder het regeltotaal, of
     *    verdubbelt hij het aantal, dan hoort de korting die in dat regeltotaal
     *    verwerkt zit dezelfde kant op te bewegen. Bij een procentuele code is
     *    die verhouding precies het percentage van de code.
     * 3. De regel hangt aan een ánder product, of is nieuw. Dan is er geen
     *    basis: de korting hoorde bij het oude product. De beheerder heeft de
     *    prijs van deze regel zelf bepaald, dus daar wordt geen korting bij
     *    verzonnen. Een afgeleid bedrag zou hier juist mis kunnen gaan: een
     *    zelf toegevoegde verzendkostenregel valt in de winkelwagen buiten een
     *    procentuele code, maar is als nieuwe regel niet als kostenregel te
     *    herkennen.
     *
     * @param  array<string, mixed>  $line
     */
    public static function discountForLine(array $line, ?OrderProduct $source): float
    {
        if (($line['discount'] ?? null) !== null) {
            return round((float) $line['discount'], 2);
        }

        if (! self::lineFollowsSource($line, $source)) {
            return 0.0;
        }

        $sourceDiscount = (float) ($source->discount ?? 0);
        $sourcePrice = (float) ($source->price ?? 0);

        if ($sourceDiscount <= 0 || $sourcePrice <= 0) {
            return 0.0;
        }

        return round($sourceDiscount * ((float) ($line['price'] ?? 0)) / $sourcePrice, 2);
    }

    /**
     * De sku van een regel zoals hij weggeschreven wordt.
     *
     * Drie gevallen, in deze volgorde:
     *
     * 1. De aanroeper geeft zelf een sku mee. Die wint altijd.
     * 2. De regel gaat nog over hetzelfde als de bronregel (zie
     *    lineFollowsSource()): de sku van de bronregel blijft staan. Dit is wat
     *    verzend- en betaalkostenregels intact houdt: dat zijn gewone
     *    orderregels zonder product, alleen herkenbaar aan
     *    sku = 'shipping_costs' / 'payment_costs'. Raken ze die kwijt, dan
     *    telt de omzetstatistiek hun verzendomzet als nul en vallen ze niet
     *    meer buiten de procentuele korting (OrderTotalsCalculator::COST_SKUS).
     * 3. De regel hangt aan een ánder product dan de bronregel, of aan een
     *    product zonder bronregel (nieuw toegevoegde regel). Dan komt de sku
     *    van dát product, zoals de winkelwagen dat ook doet
     *    (Checkout::createOrder(): `$orderProduct->sku = $cartItem->model->sku`).
     *    De sku van de bronregel overnemen zou hier de zwaarste fout zijn: een
     *    regel waarop de beheerder het product omzet naar iets anders zou de
     *    oude sku houden, en stond die bronregel op 'shipping_costs', dan
     *    boekt een echt product zich als verzendomzet.
     *
     * Blijft er in geval 3 geen product over (een los, zelf getypt item), dan
     * is er niets om een sku uit af te leiden en blijft hij leeg — net als bij
     * de custom producten die de kassa aanmaakt.
     *
     * @param  array<string, mixed>  $line
     */
    public static function skuForLine(array $line, ?OrderProduct $source): ?string
    {
        if (($line['sku'] ?? null) !== null) {
            return $line['sku'];
        }

        if (self::lineFollowsSource($line, $source)) {
            return $source->sku;
        }

        $productId = $line['product_id'] ?? null;

        return $productId ? Product::find($productId)?->sku : null;
    }

    /**
     * Mag deze regel de niet-bewerkbare velden van zijn bronregel overnemen?
     *
     * Het wijzigformulier vult order_product_id bij het laden en houdt die
     * waarde vast, ook wanneer de beheerder in dezelfde regel een ander product
     * kiest: het product-select zet name, price en product_id om, maar kan
     * order_product_id niet leegmaken. Alleen op order_product_id afgaan
     * betekent dus dat een omgezette regel de sku van zijn voorganger houdt.
     *
     * Daarom de vergelijking op product_id:
     * - gelijk (inclusief allebei leeg, wat de kostenregels zijn): dezelfde
     *   regel, overnemen;
     * - de regel heeft zelf geen product meer: er valt niets uit een product af
     *   te leiden, dus de bronregel blijft de enige bron;
     * - een ánder product: een andere regel, niets overnemen.
     *
     * @param  array<string, mixed>  $line
     */
    protected static function lineFollowsSource(array $line, ?OrderProduct $source): bool
    {
        if (! $source) {
            return false;
        }

        $lineProductId = ($line['product_id'] ?? null) !== null ? (int) $line['product_id'] : null;

        if ($lineProductId === null) {
            return true;
        }

        return $lineProductId === ($source->product_id !== null ? (int) $source->product_id : null);
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
