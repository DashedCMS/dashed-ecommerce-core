<?php

use Dashed\DashedCore\Models\User;
use Dashed\DashedEcommerceCore\Models\Order;
use Dashed\DashedEcommerceCore\Models\OrderProduct;

/**
 * Prioriteit-markering: toggle-endpoint, vlag in de lijst-payload en het
 * priority-filter op de orderlijst.
 */
it('orders: prioriteit togglen, zien in de lijst en erop filteren', function () {
    $this->actingAs(User::factory()->create(['role' => 'admin']), 'sanctum');

    $a = Order::create(['site_id' => 'site', 'email' => 'a@example.com', 'invoice_id' => 'INV-A' . uniqid(), 'status' => 'paid']);
    $b = Order::create(['site_id' => 'site', 'email' => 'b@example.com', 'invoice_id' => 'INV-B' . uniqid(), 'status' => 'paid']);

    // Toggle aan (zonder body: flip).
    $res = $this->postJson("/api/v1/orders/{$a->id}/priority", [], ['X-Site-Id' => 'site']);
    $res->assertOk();
    expect($res->json('data.is_priority'))->toBeTrue();

    // Vlag zichtbaar in de lijst.
    $list = $this->getJson('/api/v1/orders', ['X-Site-Id' => 'site']);
    $rows = collect($list->json('data'))->keyBy('id');
    expect($rows[$a->id]['is_priority'])->toBeTrue()
        ->and($rows[$b->id]['is_priority'])->toBeFalse();

    // Filter: alleen prioriteit.
    $filtered = $this->getJson('/api/v1/orders?priority=1', ['X-Site-Id' => 'site']);
    $ids = collect($filtered->json('data'))->pluck('id');
    expect($ids)->toContain($a->id)->not->toContain($b->id);

    // Expliciet uitzetten.
    $off = $this->postJson("/api/v1/orders/{$a->id}/priority", ['on' => false], ['X-Site-Id' => 'site']);
    expect($off->json('data.is_priority'))->toBeFalse();
});

it('open-order-products: prioriteit-orders staan altijd bovenaan', function () {
    $this->actingAs(User::factory()->create(['role' => 'admin']), 'sanctum');

    // Nieuwste order zónder prioriteit, oudere mét — prioriteit moet winnen
    // van de standaard-sortering (nieuwste eerst).
    $normal = Order::create(['site_id' => 'site', 'email' => 'a@example.com', 'invoice_id' => 'INV-N' . strtoupper(uniqid()), 'status' => 'paid', 'fulfillment_status' => 'unhandled']);
    $prio = Order::create(['site_id' => 'site', 'email' => 'b@example.com', 'invoice_id' => 'INV-P' . strtoupper(uniqid()), 'status' => 'paid', 'fulfillment_status' => 'unhandled']);
    $prio->forceFill(['is_priority' => true])->save();

    OrderProduct::create(['order_id' => $normal->id, 'name' => 'Gewoon', 'quantity' => 1, 'price' => 5]);
    OrderProduct::create(['order_id' => $prio->id, 'name' => 'Belangrijk', 'quantity' => 1, 'price' => 5]);

    $res = $this->getJson('/api/v1/open-order-products', ['X-Site-Id' => 'site']);
    $res->assertOk();

    $rows = collect($res->json('data'));
    expect($rows->first()['name'])->toBe('Belangrijk')
        ->and($rows->first()['is_priority'])->toBeTrue();
});
