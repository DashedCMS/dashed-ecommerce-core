<?php

use Dashed\DashedCore\Models\User;
use Dashed\DashedEcommerceCore\Models\Order;
use Dashed\DashedEcommerceCore\Models\OrderProduct;

/**
 * Regressie: orderregels zónder SKU (bv. losse kassa-/POS-items) mogen niet uit
 * de open-orders-lijst vallen. De cost-SKU-uitsluiting moet null-veilig zijn —
 * `sku NOT IN (...)` is in SQL onwaar voor NULL, waardoor null-sku-regels stil
 * verdwenen en een origin-filter (bv. POS) onterecht 0 resultaten gaf.
 */
it('open-order-products: toont regels zonder sku bij een origin-filter', function () {
    $this->actingAs(User::factory()->create(['role' => 'admin']), 'sanctum');

    $order = Order::create([
        'site_id' => 'site',
        'email' => 'kassa@example.com',
        'invoice_id' => 'INV-' . strtoupper(uniqid()),
        'status' => 'paid',
        'order_origin' => 'pos',
        'fulfillment_status' => 'unhandled',
    ]);

    $line = OrderProduct::create([
        'order_id' => $order->id,
        'name' => 'Los kassa-item zonder sku',
        'sku' => null,
        'quantity' => 1,
        'price' => 10.00,
    ]);

    $res = $this->getJson('/api/v1/open-order-products?order_origin=pos', ['X-Site-Id' => 'site']);
    $res->assertOk();

    $ids = collect($res->json('data'))->pluck('id')->all();
    expect($ids)->toContain($line->id);
});

it('open-order-products: sluit cost-regels (shipping/payment) nog steeds uit', function () {
    $this->actingAs(User::factory()->create(['role' => 'admin']), 'sanctum');

    $order = Order::create([
        'site_id' => 'site',
        'email' => 'kassa@example.com',
        'invoice_id' => 'INV-' . strtoupper(uniqid()),
        'status' => 'paid',
        'order_origin' => 'pos',
        'fulfillment_status' => 'unhandled',
    ]);

    $product = OrderProduct::create(['order_id' => $order->id, 'name' => 'Product', 'sku' => null, 'quantity' => 1, 'price' => 10.00]);
    $shipping = OrderProduct::create(['order_id' => $order->id, 'name' => 'Verzendkosten', 'sku' => 'shipping_costs', 'quantity' => 1, 'price' => 5.00]);

    $res = $this->getJson('/api/v1/open-order-products?order_origin=pos', ['X-Site-Id' => 'site']);
    $res->assertOk();

    $ids = collect($res->json('data'))->pluck('id')->all();
    expect($ids)->toContain($product->id)->not->toContain($shipping->id);
});
