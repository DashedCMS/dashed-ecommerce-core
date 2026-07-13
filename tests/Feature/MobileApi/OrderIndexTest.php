<?php

use Dashed\DashedCore\Models\User;
use Dashed\DashedEcommerceCore\Models\Order;
use Dashed\DashedEcommerceCore\Models\OrderProduct;

function makeOrderWithProducts(array $attributes, array $products): Order
{
    $order = Order::create(array_merge([
        'site_id' => 'site',
        'email' => 'klant@example.com',
        'invoice_id' => 'INV-' . strtoupper(uniqid()),
        'status' => 'paid',
    ], $attributes));

    foreach ($products as $p) {
        OrderProduct::create([
            'order_id' => $order->id,
            'name' => $p[0],
            'quantity' => $p[1],
            'price' => 10.00,
        ]);
    }

    return $order;
}

it('includes ordered products (name x quantity) in the order list', function () {
    $this->actingAs(User::factory()->create(['role' => 'admin']), 'sanctum');
    makeOrderWithProducts([], [['Testproduct A', 2], ['Testproduct B', 1]]);

    $res = $this->getJson('/api/v1/orders', ['X-Site-Id' => 'site']);

    $res->assertOk();
    $products = $res->json('data.0.products');
    expect($products)->toHaveCount(2)
        ->and(collect($products)->pluck('name')->all())->toContain('Testproduct A')->toContain('Testproduct B')
        ->and(collect($products)->firstWhere('name', 'Testproduct A')['quantity'])->toBe(2);
});

it('filters retour_status "unhandled" to orders with a return that is not handled', function () {
    $this->actingAs(User::factory()->create(['role' => 'admin']), 'sanctum');

    $waiting = makeOrderWithProducts(['retour_status' => 'waiting_for_return'], [['P', 1]]);
    $returned = makeOrderWithProducts(['retour_status' => 'returned'], [['P', 1]]);
    $handled = makeOrderWithProducts(['retour_status' => 'handled'], [['P', 1]]);
    $none = makeOrderWithProducts([], [['P', 1]]); // geen retour → retour_status null

    $res = $this->getJson('/api/v1/orders?retour_status=unhandled', ['X-Site-Id' => 'site']);

    $res->assertOk();
    $ids = collect($res->json('data'))->pluck('id')->all();
    expect($ids)->toContain($waiting->id)->toContain($returned->id)
        ->and($ids)->not->toContain($handled->id)
        ->and($ids)->not->toContain($none->id);
});

it('filters retour_status "handled" directly on the column', function () {
    $this->actingAs(User::factory()->create(['role' => 'admin']), 'sanctum');

    $handled = makeOrderWithProducts(['retour_status' => 'handled'], [['P', 1]]);
    makeOrderWithProducts(['retour_status' => 'waiting_for_return'], [['P', 1]]);

    $res = $this->getJson('/api/v1/orders?retour_status=handled', ['X-Site-Id' => 'site']);

    $res->assertOk();
    expect(collect($res->json('data'))->pluck('id')->all())->toBe([$handled->id]);
});
