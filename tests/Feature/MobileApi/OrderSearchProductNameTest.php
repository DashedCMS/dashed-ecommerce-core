<?php

use Dashed\DashedCore\Models\User;
use Dashed\DashedEcommerceCore\Models\Order;
use Dashed\DashedEcommerceCore\Models\OrderProduct;

function makeOrderWithProductName(string $productName): Order
{
    $order = Order::create([
        'site_id' => 'site',
        'email' => 'klant@example.com',
        'invoice_id' => 'INV-' . strtoupper(uniqid()),
        'status' => 'paid',
    ]);

    OrderProduct::create([
        'order_id' => $order->id,
        'name' => $productName,
        'sku' => 'SKU' . strtoupper(uniqid()),
        'quantity' => 1,
        'price' => 10.00,
    ]);

    return $order;
}

it('order search matches on ordered product name (multi-term AND)', function () {
    $this->actingAs(User::factory()->create(['role' => 'admin']), 'sanctum');

    $hit = makeOrderWithProductName('Japandi Hond - Zwart - 5CM');
    $miss = makeOrderWithProductName('Losse vaas - 20CM - Blauw');

    $res = $this->getJson('/api/v1/orders?search=Japandi Hond', ['X-Site-Id' => 'site']);

    $res->assertOk();
    $ids = collect($res->json('data'))->pluck('id')->all();
    expect($ids)->toContain($hit->id)
        ->and($ids)->not->toContain($miss->id);
});
