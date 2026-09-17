<?php

use Dashed\DashedCore\Models\User;
use Dashed\DashedEcommerceCore\Models\Order;
use Dashed\DashedEcommerceCore\Models\OrderProduct;

/**
 * "Openstaande bestellingen" toont standaard zowel nog niet afgehandelde
 * ('unhandled') als in behandeling zijnde ('in_treatment') orders — niet alleen
 * unhandled. Een expliciete status filtert nog gewoon exact.
 */
function openOrderWithStatus(string $fulfillment): OrderProduct
{
    $order = Order::create([
        'site_id' => 'site',
        'email' => 'klant@example.com',
        'invoice_id' => 'INV-' . strtoupper(uniqid()),
        'status' => 'paid',
        'fulfillment_status' => $fulfillment,
    ]);

    return OrderProduct::create([
        'order_id' => $order->id,
        'name' => 'Product ' . $fulfillment,
        'sku' => 'SKU-' . strtoupper(uniqid()),
        'quantity' => 1,
        'price' => 10.00,
    ]);
}

it('open-order-products: toont standaard unhandled én in_treatment, niet handled', function () {
    $this->actingAs(User::factory()->create(['role' => 'admin']), 'sanctum');

    $unhandled = openOrderWithStatus('unhandled');
    $inTreatment = openOrderWithStatus('in_treatment');
    $handled = openOrderWithStatus('handled');

    $ids = collect($this->getJson('/api/v1/open-order-products', ['X-Site-Id' => 'site'])->json('data'))->pluck('id');

    expect($ids)->toContain($unhandled->id)
        ->toContain($inTreatment->id)
        ->not->toContain($handled->id);
});

it('open-order-products: een expliciete status filtert exact', function () {
    $this->actingAs(User::factory()->create(['role' => 'admin']), 'sanctum');

    $unhandled = openOrderWithStatus('unhandled');
    $inTreatment = openOrderWithStatus('in_treatment');

    $ids = collect($this->getJson('/api/v1/open-order-products?fulfillment_status=in_treatment', ['X-Site-Id' => 'site'])->json('data'))->pluck('id');

    expect($ids)->toContain($inTreatment->id)->not->toContain($unhandled->id);
});
