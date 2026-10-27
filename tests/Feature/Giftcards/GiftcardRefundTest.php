<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Event;
use Dashed\DashedCore\Classes\Sites;
use Dashed\DashedCore\Models\Customsetting;
use Dashed\DashedEcommerceCore\Models\Order;
use Dashed\DashedEcommerceCore\Models\OrderReturn;
use Dashed\DashedEcommerceCore\Models\DiscountCode;
use Dashed\DashedEcommerceCore\Models\OrderProduct;
use Dashed\DashedEcommerceCore\Models\PaymentMethod;
use Dashed\DashedEcommerceCore\Models\OrderReturnLine;
use Dashed\DashedEcommerceCore\Classes\OrderModificationService;
use Dashed\DashedEcommerceCore\Services\Giftcards\GiftcardLedger;
use Dashed\DashedEcommerceCore\Events\Orders\OrderCancelledEvent;
use Dashed\DashedEcommerceCore\Events\Orders\OrderModifiedEvent;
use Dashed\DashedEcommerceCore\Services\OrderReturn\RefundRegistrar;
use Dashed\DashedEcommerceCore\Services\OrderReturn\ReturnProcessor;
use Dashed\DashedEcommerceCore\Events\Orders\OrderMarkedAsPaidEvent;
use Dashed\DashedEcommerceCore\Events\Orders\OrderReturnProcessedEvent;
use Dashed\DashedEcommerceCore\Events\Orders\OrderFulfillmentStatusChangedEvent;

require_once __DIR__ . '/../../Support/InvoiceHarness.php';

/**
 * Een betaalde order die (deels) met een verkochte cadeaubon betaald is en
 * gecrediteerd wordt: het bonbedrag gaat terug op de bon, nooit meer dan er
 * van de bon betaald is, en alleen de rest wordt als geld terugbetaald.
 */
beforeEach(function () {
    bootInvoiceHarness();
    Customsetting::set('taxes_prices_include_taxes', 1);
    Bus::fake();
    Mail::fake();
    Event::fake([OrderMarkedAsPaidEvent::class, OrderCancelledEvent::class, OrderFulfillmentStatusChangedEvent::class, OrderModifiedEvent::class, OrderReturnProcessedEvent::class]);
});

function retourBon(float $saldo): DiscountCode
{
    $code = new DiscountCode();
    $code->forceFill([
        'site_ids' => [Sites::getActive()],
        'name' => 'Cadeaubon',
        'code' => 'LOV-' . strtoupper(uniqid()),
        'type' => 'amount',
        'discount_amount' => $saldo,
        'initial_amount' => $saldo,
        'is_giftcard' => 1,
        'giftcard_type' => 'verkocht',
        'valid_for' => 'all',
    ]);
    DiscountCode::withoutEvents(fn () => $code->save());

    return $code->fresh();
}

/**
 * Betaalde order met één regel ($aantal stuks, regeltotaal $totaal), waarvan
 * $bonBedrag met de bon en de rest via iDEAL betaald is.
 */
function retourOrder(DiscountCode $bon, float $totaal, float $bonBedrag, int $aantal = 1): array
{
    $order = Order::create([
        'email' => 'klant@example.com',
        'status' => 'pending',
        'invoice_id' => 'INV-BON-' . strtoupper(uniqid()),
        'total' => $totaal,
        'subtotal' => $totaal,
        'btw' => round($totaal / 121 * 21, 2),
        'fulfillment_status' => 'handled',
    ]);
    $regel = OrderProduct::withoutEvents(fn () => OrderProduct::create([
        'order_id' => $order->id, 'name' => 'Vaas', 'quantity' => $aantal, 'price' => $totaal, 'vat_rate' => 21, 'sku' => 'VAAS',
    ]));

    GiftcardLedger::reserve($order, [['code' => $bon->code, 'discount_code_id' => $bon->id, 'redeemed' => $bonBedrag]]);
    GiftcardLedger::commit($order->fresh());
    $order->orderPayments()->create(['status' => 'paid', 'amount' => $bonBedrag, 'psp' => 'giftcard', 'payment_method' => 'Cadeaubon ' . $bon->code]);
    if ($totaal - $bonBedrag > 0) {
        $order->orderPayments()->create(['status' => 'paid', 'amount' => round($totaal - $bonBedrag, 2), 'psp' => 'own', 'payment_method' => 'iDEAL']);
    }
    Order::withoutEvents(fn () => $order->forceFill(['status' => 'paid'])->save());

    return ['order' => $order->fresh(), 'regel' => $regel];
}

function retourCrediteer(Order $order, OrderProduct $regel, int $aantal, ?int $paymentMethodId): Order
{
    $regel = $regel->fresh();
    $regel->refundQuantity = $aantal;

    return $order->fresh()->markAsCancelledWithCredit(
        sendCustomerEmail: false,
        productsMustBeReturned: false,
        restock: false,
        refundDiscountCosts: false,
        extraOrderLineName: null,
        extraOrderLinePrice: 0,
        chosenOrderProducts: [$regel],
        fulfillmentStatus: 'handled',
        paymentMethodId: $paymentMethodId,
    );
}

function retourBetaalmethode(): PaymentMethod
{
    return PaymentMethod::create([
        'site_id' => Sites::getActive(),
        'name' => ['nl' => 'Terugbetaling'],
        'type' => 'online',
        'active' => 1,
        'psp' => 'own',
    ]);
}

it('full cancel returns the giftcard part to the card', function () {
    $bon = retourBon(50);
    ['order' => $order, 'regel' => $regel] = retourOrder($bon, 52.95, 50);
    expect((float) $bon->fresh()->discount_amount)->toBe(0.0);

    $credit = retourCrediteer($order, $regel, 1, retourBetaalmethode()->id);

    $bonBetaling = $credit->orderPayments()->where('psp', 'giftcard')->get();
    $geld = $credit->orderPayments()->where('psp', 'own')->get();

    expect((float) $bon->fresh()->discount_amount)->toBe(50.0)
        ->and((float) $bon->fresh()->used_amount)->toBe(0.0)
        ->and($bonBetaling)->toHaveCount(1)
        ->and((float) $bonBetaling->first()->amount)->toBe(-50.0)
        ->and($bonBetaling->first()->status)->toBe('paid')
        ->and($geld)->toHaveCount(1)
        ->and((float) $geld->first()->amount)->toBe(-2.95)
        ->and((float) $order->fresh()->applied_gift_cards[0]['refunded'])->toBe(50.0);
});

it('two partial returns never over-credit the card', function () {
    $bon = retourBon(50);
    ['order' => $order, 'regel' => $regel] = retourOrder($bon, 60, 50, 2);

    $eerste = OrderReturn::create(['order_id' => $order->id, 'email' => 'klant@example.com', 'status' => OrderReturn::STATUS_APPROVED, 'approved_at' => now()]);
    $eersteRegel = OrderReturnLine::create(['order_return_id' => $eerste->id, 'order_product_id' => $regel->id, 'quantity' => 1]);
    $credit1 = app(ReturnProcessor::class)->process($eerste->fresh(), [['order_return_line_id' => $eersteRegel->id, 'quantity' => 1]], ['restock' => false]);

    expect((float) $bon->fresh()->discount_amount)->toBe(30.0)
        ->and((float) $credit1->orderPayments()->where('psp', 'giftcard')->sum('amount'))->toEqual(-30)
        ->and($eerste->fresh()->refundableAmount())->toBe(0.0)
        ->and($eerste->fresh()->isRefunded())->toBeTrue();

    $tweede = OrderReturn::create(['order_id' => $order->id, 'email' => 'klant@example.com', 'status' => OrderReturn::STATUS_APPROVED, 'approved_at' => now()]);
    $tweedeRegel = OrderReturnLine::create(['order_return_id' => $tweede->id, 'order_product_id' => $regel->id, 'quantity' => 1]);
    $credit2 = app(ReturnProcessor::class)->process($tweede->fresh(), [['order_return_line_id' => $tweedeRegel->id, 'quantity' => 1]], ['restock' => false, 'confirm_existing_credit' => true]);

    expect((float) $bon->fresh()->discount_amount)->toBe(50.0)
        ->and((float) $credit2->orderPayments()->where('psp', 'giftcard')->sum('amount'))->toEqual(-20)
        ->and($tweede->fresh()->refundableAmount())->toBe(10.0)
        ->and($tweede->fresh()->isRefunded())->toBeFalse();

    $betaling = app(RefundRegistrar::class)->register($tweede->fresh(), 10, 'iDEAL');

    expect((float) $betaling->amount)->toBe(-10.0)
        ->and($tweede->fresh()->isRefunded())->toBeTrue();
});

it('leaves the promo giftcard refund unchanged', function () {
    $promo = DiscountCode::create([
        'site_ids' => [Sites::getActive()],
        'name' => 'Promobon',
        'code' => 'PROMO-' . strtoupper(uniqid()),
        'is_giftcard' => 1,
        'discount_amount' => 30,
        'use_stock' => 0,
    ]);

    $order = Order::create([
        'email' => 'klant@example.com',
        'status' => 'paid',
        'invoice_id' => 'INV-PROMO-' . strtoupper(uniqid()),
        'total' => 30,
        'subtotal' => 50,
        'discount' => 20,
        'discount_code_id' => $promo->id,
        'fulfillment_status' => 'handled',
    ]);
    $regel = OrderProduct::withoutEvents(fn () => OrderProduct::create([
        'order_id' => $order->id, 'name' => 'Vaas', 'quantity' => 1, 'price' => 30, 'discount' => 20, 'vat_rate' => 21, 'sku' => 'VAAS',
    ]));
    $order->orderPayments()->create(['status' => 'paid', 'amount' => 30, 'psp' => 'own', 'payment_method' => 'iDEAL']);
    $saldoVoor = (float) $promo->fresh()->discount_amount;

    $credit = retourCrediteer($order, $regel, 1, retourBetaalmethode()->id);

    expect((float) $promo->fresh()->discount_amount)->toBe(round($saldoVoor + 20, 2))
        ->and($credit->orderPayments()->where('psp', 'giftcard')->exists())->toBeFalse()
        ->and((float) $credit->orderPayments()->where('psp', 'own')->sum('amount'))->toEqual(-30);
});

it('refunds the card only on the order that holds the giftcard payments after a modification', function () {
    $bon = retourBon(50);
    ['order' => $order] = retourOrder($bon, 52.95, 50);

    $nieuw = OrderModificationService::replaceWithNewOrder($order->fresh(), [
        ['order_product_id' => null, 'product_id' => null, 'name' => 'Vaas', 'quantity' => 1, 'price' => 52.95, 'vat_rate' => 21, 'product_extras' => []],
    ], ['credit_old_order' => false]);

    // Beide orders dragen een 'used'-regel voor dezelfde bon, maar de
    // bonbetaling is naar de vervanger verhuisd.
    expect($order->fresh()->applied_gift_cards[0]['status'])->toBe('used')
        ->and($nieuw->fresh()->applied_gift_cards[0]['status'])->toBe('used')
        ->and(GiftcardLedger::refund($order->fresh(), 50))->toBe(0.0)
        ->and((float) $bon->fresh()->discount_amount)->toBe(0.0)
        ->and(GiftcardLedger::refund($nieuw->fresh(), 50))->toBe(50.0)
        ->and((float) $bon->fresh()->discount_amount)->toBe(50.0);
});

it('does not return the card when a modification credits the old order', function () {
    $bon = retourBon(50);
    ['order' => $order] = retourOrder($bon, 52.95, 50);

    OrderModificationService::replaceWithNewOrder($order->fresh(), [
        ['order_product_id' => null, 'product_id' => null, 'name' => 'Vaas', 'quantity' => 1, 'price' => 52.95, 'vat_rate' => 21, 'product_extras' => []],
    ], ['credit_old_order' => true]);

    expect((float) $bon->fresh()->discount_amount)->toBe(0.0)
        ->and((float) $bon->fresh()->used_amount)->toBe(50.0);
});
