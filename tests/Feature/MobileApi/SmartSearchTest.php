<?php

use Dashed\DashedCore\Models\User;
use Dashed\DashedEcommerceCore\Models\Order;
use Dashed\DashedEcommerceCore\Models\OrderProduct;

/** Openstaande, betaalde order met een product met de gegeven naam. */
function makeOpenOrderProduct(string $productName): OrderProduct
{
    $order = Order::create([
        'site_id' => 'site',
        'email' => 'klant@example.com',
        'invoice_id' => 'INV-' . strtoupper(uniqid()),
        'status' => 'paid',
        'fulfillment_status' => 'unhandled',
    ]);

    return OrderProduct::create([
        'order_id' => $order->id,
        'name' => $productName,
        'sku' => 'SKU' . strtoupper(uniqid()),
        'quantity' => 1,
        'price' => 10.00,
    ]);
}

it('open-order-products: smart multi-term search matches non-adjacent words', function () {
    $this->actingAs(User::factory()->create(['role' => 'admin']), 'sanctum');

    $hit = makeOpenOrderProduct('Familie beeldjes - 15CM - Papa & mama met 4 kinderen - Bruin');
    makeOpenOrderProduct('Losse vaas - 20CM - Blauw');

    // Woorden staan niet aaneengesloten en in andere volgorde in de naam.
    $res = $this->getJson('/api/v1/open-order-products?search=15cm kinderen', ['X-Site-Id' => 'site']);
    $res->assertOk();
    $ids = collect($res->json('data'))->pluck('id')->all();
    expect($ids)->toContain($hit->id)->toHaveCount(1);

    // Eén niet-matchend woord → geen resultaat (AND).
    $none = $this->getJson('/api/v1/open-order-products?search=15cm blauw', ['X-Site-Id' => 'site']);
    expect($none->json('data'))->toHaveCount(0);
});

it('open-order-products: single-term search still works', function () {
    $this->actingAs(User::factory()->create(['role' => 'admin']), 'sanctum');
    $hit = makeOpenOrderProduct('Familie beeldjes - 15CM - 4 kinderen');

    $res = $this->getJson('/api/v1/open-order-products?search=kinderen', ['X-Site-Id' => 'site']);
    expect(collect($res->json('data'))->pluck('id')->all())->toContain($hit->id);
});
