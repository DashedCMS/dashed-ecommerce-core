<?php

declare(strict_types=1);

// tests/Feature/Proforma/ProformaCheckoutIdempotencyTest.php
//
// Locks Fix 1 (idempotency guard) and Fix 2 (shipping dead-end) of the
// ProformaCheckout component. These tests prove that a second submit on an
// already-pending proforma does not compound the total or add duplicate
// shipping lines, and that shipping being enabled without available methods
// does not block payment.

use Livewire\Livewire;
use Illuminate\Support\Facades\Mail;
use Dashed\DashedCore\Classes\Sites;
use Dashed\DashedEcommerceCore\Models\Order;
use Dashed\DashedEcommerceCore\Models\ShippingZone;
use Dashed\DashedEcommerceCore\Models\ShippingMethod;
use Dashed\DashedEcommerceCore\Livewire\Frontend\Checkout\ProformaCheckout;

// Helpers with distinct names to avoid conflicts with other test files.

function makeIdempotencyShippingProforma(float $total): Order
{
    $order = Order::create([
        'email' => 'idempotency@example.com',
        'status' => Order::STATUS_CONCEPT,
        'is_proforma' => true,
        'proforma_allow_shipping' => true,
        'country' => 'NL',
        'total' => $total,
        'subtotal' => $total,
        'btw' => round($total - ($total / 1.21), 2),
    ]);
    $order->orderProducts()->create([
        'product_id' => null, 'name' => 'Maatwerk', 'quantity' => 1, 'price' => $total, 'vat_rate' => 21,
    ]);

    return $order;
}

function makeIdempotencyNlShippingMethod(float $costs): ShippingMethod
{
    $zone = ShippingZone::create([
        'site_id' => Sites::getActive(),
        'name' => ['nl' => 'Nederland'],
        'zones' => ['Netherlands'],
        'search_fields' => 'Nederland,NL',
    ]);

    return ShippingMethod::create([
        'shipping_zone_id' => $zone->id,
        'name' => ['nl' => 'PostNL'],
        'costs' => $costs,
        'sort' => 'static_amount',
        'minimum_order_value' => 100,
        'maximum_order_value' => 500,
        'order' => 1,
    ]);
}

beforeEach(function () {
    Mail::fake();
});

// Fix 1: idempotency guard - second submit on pending order must not compound
// the total or add a duplicate shipping_costs line.
it('second submit on a pending proforma does not compound the total or add a duplicate shipping line', function () {
    $order = makeIdempotencyShippingProforma(121.0);
    $method = makeIdempotencyNlShippingMethod(6.95);

    $component = Livewire::test(ProformaCheckout::class, ['orderHash' => $order->hash])
        ->set('firstName', 'Jan')->set('lastName', 'Jansen')
        ->set('street', 'Straat')->set('houseNr', '1')
        ->set('zipCode', '1234AB')->set('city', 'Stad')->set('country', 'NL')
        ->set('shippingMethod', (string) $method->id);

    // First submit: transitions concept to pending and adds one shipping line.
    // No PSP is connected in the test harness, so the method ends with a flash
    // error instead of a redirect - that is expected and harmless here.
    $component->call('submit');

    $order->refresh();
    expect($order->status)->toBe('pending');
    $totalAfterFirst = (float) $order->total;
    $shippingCountAfterFirst = $order->orderProducts()->where('sku', 'shipping_costs')->count();
    expect($shippingCountAfterFirst)->toBe(1);

    // Second submit: order is already pending - mutation block must be skipped.
    $component->call('submit');

    $order->refresh();
    expect((float) $order->total)->toEqual($totalAfterFirst)
        ->and($order->orderProducts()->where('sku', 'shipping_costs')->count())->toBe(1);
});

// Fix 1: paid guard - a paid order must not be reverted to pending on submit.
it('submit on an already-paid proforma does not mutate the order', function () {
    $order = Order::create([
        'email' => 'paid@example.com',
        'status' => 'paid',
        'is_proforma' => true,
        'proforma_allow_shipping' => false,
        'total' => 100.0,
        'subtotal' => 100.0,
    ]);
    $order->orderProducts()->create([
        'product_id' => null, 'name' => 'Maatwerk', 'quantity' => 1, 'price' => 100.0, 'vat_rate' => 21,
    ]);

    Livewire::test(ProformaCheckout::class, ['orderHash' => $order->hash])
        ->set('firstName', 'Jan')->set('lastName', 'Jansen')
        ->set('street', 'Straat')->set('houseNr', '1')
        ->set('zipCode', '1234AB')->set('city', 'Stad')->set('country', 'NL')
        ->call('submit');

    $order->refresh();
    // Status must not have been reverted to pending.
    expect($order->status)->toBe('paid')
        ->and((float) $order->total)->toEqual(100.0);
});

// Fix 2: shipping enabled but no methods available for the country - submit must
// succeed (validation passes, no shipping line added).
it('submit succeeds without a shipping line when shipping is enabled but no methods match the country', function () {
    // Proforma has proforma_allow_shipping=true but we create NO shipping zones,
    // so ShoppingCart::getAvailableShippingMethods returns an empty collection.
    $order = Order::create([
        'email' => 'noshipping@example.com',
        'status' => Order::STATUS_CONCEPT,
        'is_proforma' => true,
        'proforma_allow_shipping' => true,
        'country' => 'NL',
        'total' => 121.0,
        'subtotal' => 121.0,
        'btw' => round(121.0 - (121.0 / 1.21), 2),
    ]);
    $order->orderProducts()->create([
        'product_id' => null, 'name' => 'Maatwerk', 'quantity' => 1, 'price' => 121.0, 'vat_rate' => 21,
    ]);

    Livewire::test(ProformaCheckout::class, ['orderHash' => $order->hash])
        ->set('firstName', 'Jan')->set('lastName', 'Jansen')
        ->set('street', 'Straat')->set('houseNr', '1')
        ->set('zipCode', '1234AB')->set('city', 'Stad')->set('country', 'NL')
        ->call('submit')
        ->assertHasNoErrors(['shippingMethod']);

    $order->refresh();
    // Order transitions to pending without a shipping line.
    expect($order->status)->toBe('pending')
        ->and($order->orderProducts()->where('sku', 'shipping_costs')->count())->toBe(0);
});
