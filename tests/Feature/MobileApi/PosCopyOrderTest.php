<?php

use Dashed\DashedCore\Models\User;
use Dashed\DashedEcommerceCore\Models\Order;
use Dashed\DashedEcommerceCore\Models\POSCart;
use Dashed\DashedEcommerceCore\Models\OrderProduct;

it('kopieert een order naar de kassa-cart', function () {
    $this->actingAs(User::factory()->create(['role' => 'admin']), 'sanctum');

    $order = Order::create([
        'site_id' => 'site',
        'email' => 'klant@example.com',
        'invoice_id' => 'INV-' . strtoupper(uniqid()),
        'status' => 'paid',
        'first_name' => 'Kopieer',
        'last_name' => 'Klant',
    ]);
    OrderProduct::create([
        'order_id' => $order->id,
        'name' => 'Testproduct',
        'quantity' => 2,
        'price' => 10.0,
    ]);

    $cart = POSCart::create(['identifier' => 'pos-test-1', 'products' => []]);

    $res = $this->postJson('/api/v1/point-of-sale/copy-order-to-cart', [
        'posIdentifier' => 'pos-test-1',
        'orderId' => $order->id,
        'copyCustomerDetails' => true,
    ], ['X-Site-Id' => 'site']);

    $res->assertOk()->assertJsonPath('success', true);

    expect(POSCart::find($cart->id)->products)->not->toBeEmpty();
});

it('geeft 404 als de kassa-sessie niet bestaat', function () {
    $this->actingAs(User::factory()->create(['role' => 'admin']), 'sanctum');
    $order = Order::create(['site_id' => 'site', 'email' => 'a@b.nl', 'status' => 'paid']);

    $this->postJson('/api/v1/point-of-sale/copy-order-to-cart', [
        'posIdentifier' => 'bestaat-niet',
        'orderId' => $order->id,
    ], ['X-Site-Id' => 'site'])->assertStatus(404);
});
