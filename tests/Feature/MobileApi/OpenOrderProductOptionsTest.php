<?php

use Dashed\DashedCore\Models\User;
use Dashed\DashedEcommerceCore\Models\Order;
use Dashed\DashedEcommerceCore\Models\OrderProduct;

/**
 * De per-regel-weergave geeft de gekozen productopties (product_extras) mee als
 * één regel — zelfde formaat als de Filament-kolom: "Naam: Waarde | Naam: Waarde".
 */
it('open-order-products: geeft productopties mee als options-regel', function () {
    $this->actingAs(User::factory()->create(['is_superadmin' => true]), 'sanctum');

    $order = Order::create([
        'site_id' => 'site',
        'email' => 'klant@example.com',
        'invoice_id' => 'INV-' . strtoupper(uniqid()),
        'status' => 'paid',
        'fulfillment_status' => 'unhandled',
    ]);

    $withOptions = OrderProduct::create([
        'order_id' => $order->id,
        'name' => 'Vaas met opties',
        'sku' => 'VAAS-1',
        'quantity' => 1,
        'price' => 24.95,
        'product_extras' => [
            ['name' => 'Kleur', 'value' => 'Rose Gold'],
            ['name' => 'Formaat', 'value' => '25CM'],
        ],
    ]);

    $without = OrderProduct::create([
        'order_id' => $order->id,
        'name' => 'Vaas zonder opties',
        'sku' => 'VAAS-2',
        'quantity' => 1,
        'price' => 24.95,
    ]);

    $res = $this->getJson('/api/v1/open-order-products', ['X-Site-Id' => 'site']);
    $res->assertOk();

    $rows = collect($res->json('data'))->keyBy('id');
    expect($rows[$withOptions->id]['options'])->toBe('Kleur: Rose Gold | Formaat: 25CM')
        ->and($rows[$without->id]['options'])->toBeNull();
});

it('open-order-products: zoekt ook op productopties (naam en waarde, ook met accenten)', function () {
    $this->actingAs(User::factory()->create(['is_superadmin' => true]), 'sanctum');

    $order = Order::create([
        'site_id' => 'site',
        'email' => 'klant@example.com',
        'invoice_id' => 'INV-' . strtoupper(uniqid()),
        'status' => 'paid',
        'fulfillment_status' => 'unhandled',
    ]);

    $hit = OrderProduct::create([
        'order_id' => $order->id,
        'name' => 'Naambord',
        'quantity' => 1,
        'price' => 10,
        'product_extras' => [['name' => 'Gravering', 'value' => 'Familie Rosé']],
    ]);
    $miss = OrderProduct::create([
        'order_id' => $order->id,
        'name' => 'Naambord',
        'quantity' => 1,
        'price' => 10,
        'product_extras' => [['name' => 'Gravering', 'value' => 'Jansen']],
    ]);

    $ids = fn (string $q) => collect($this->getJson('/api/v1/open-order-products?search=' . urlencode($q), ['X-Site-Id' => 'site'])->assertOk()->json('data'))->pluck('id')->all();

    expect($ids('rosé'))->toContain($hit->id)->not->toContain($miss->id);
    expect($ids('naambord familie'))->toBe([$hit->id]);
    expect($ids('gravering'))->toContain($hit->id)->toContain($miss->id);
});
