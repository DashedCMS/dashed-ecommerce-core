<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Event;
use Dashed\DashedCore\Classes\Sites;
use Dashed\DashedCore\Models\Customsetting;
use Dashed\DashedEcommerceCore\Models\Order;
use Dashed\DashedEcommerceCore\Models\DiscountCode;
use Dashed\DashedEcommerceCore\Models\OrderProduct;
use Dashed\DashedEcommerceCore\Classes\OrderModificationService;
use Dashed\DashedEcommerceCore\Services\Giftcards\GiftcardLedger;
use Dashed\DashedEcommerceCore\Events\Orders\OrderCancelledEvent;
use Dashed\DashedEcommerceCore\Events\Orders\OrderModifiedEvent;
use Dashed\DashedEcommerceCore\Events\Orders\OrderMarkedAsPaidEvent;
use Dashed\DashedEcommerceCore\Events\Orders\OrderFulfillmentStatusChangedEvent;

require_once __DIR__ . '/../../Support/InvoiceHarness.php';

/**
 * Een order met een verkochte cadeaubon die gewijzigd wordt: de vervanger
 * neemt de bonbetaling en de reservering over en boekt hem af zodra hij
 * betaald is; de oude order geeft het saldo niet terug.
 */
beforeEach(function () {
    bootInvoiceHarness();
    Customsetting::set('taxes_prices_include_taxes', 1);
    Bus::fake();
    Mail::fake();
    Event::fake([OrderMarkedAsPaidEvent::class, OrderCancelledEvent::class, OrderFulfillmentStatusChangedEvent::class, OrderModifiedEvent::class]);
});

function wijzigBon(float $saldo): DiscountCode
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

/** Openstaande order van € 50 waarvan € 20 met de bon betaald is. */
function wijzigOrder(DiscountCode $bon): Order
{
    $order = Order::create([
        'email' => 'klant@example.com',
        'status' => 'pending',
        'invoice_id' => 'PROFORMA',
        'total' => 50,
        'subtotal' => 50,
    ]);
    OrderProduct::create(['order_id' => $order->id, 'name' => 'Vaas', 'quantity' => 1, 'price' => 50, 'vat_rate' => 21]);

    GiftcardLedger::reserve($order, [['code' => $bon->code, 'discount_code_id' => $bon->id, 'redeemed' => 20]]);
    $order->orderPayments()->create(['status' => 'paid', 'amount' => 20, 'psp' => 'giftcard', 'payment_method' => 'Cadeaubon ' . $bon->code]);
    $order->orderPayments()->create(['status' => 'pending', 'amount' => 30, 'psp' => 'paynl']);

    return $order->fresh();
}

function wijzigRegel(float $price): array
{
    return ['order_product_id' => null, 'product_id' => null, 'name' => 'Vaas', 'quantity' => 1, 'price' => $price, 'vat_rate' => 21, 'product_extras' => []];
}

it('verhuist de bonbetaling en boekt de bon af als de vervanger betaald wordt', function () {
    $bon = wijzigBon(50);
    $order = wijzigOrder($bon);

    $newOrder = OrderModificationService::replaceWithNewOrder($order, [wijzigRegel(50)], ['credit_old_order' => false]);

    expect($newOrder->orderPayments()->where('psp', 'giftcard')->where('status', 'paid')->sum('amount'))->toEqual(20)
        ->and($newOrder->status)->toBe('partially_paid')
        ->and($newOrder->applied_gift_cards[0]['status'])->toBe('reserved')
        ->and($order->fresh()->applied_gift_cards[0]['status'])->toBe('transferred')
        ->and((float) $bon->fresh()->reserved_amount)->toBe(20.0)
        ->and((float) $bon->fresh()->discount_amount)->toBe(30.0);

    $newOrder->orderPayments()->create(['status' => 'paid', 'amount' => 30, 'psp' => 'paynl']);
    $newOrder->changeStatus('paid');

    expect((float) $bon->fresh()->used_amount)->toBe(20.0)
        ->and((float) $bon->fresh()->reserved_amount)->toBe(0.0)
        ->and($newOrder->fresh()->applied_gift_cards[0]['status'])->toBe('used');
});

it('boekt de bon meteen af als de bon de goedkopere vervanger helemaal betaalt', function () {
    $bon = wijzigBon(50);
    $order = wijzigOrder($bon);

    $newOrder = OrderModificationService::replaceWithNewOrder($order, [wijzigRegel(20)], ['credit_old_order' => false]);

    expect($newOrder->status)->toBe('paid')
        ->and((float) $bon->fresh()->used_amount)->toBe(20.0)
        ->and((float) $bon->fresh()->reserved_amount)->toBe(0.0)
        ->and((float) $bon->fresh()->discount_amount)->toBe(30.0);
});
