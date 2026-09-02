<?php

use Dashed\DashedCore\Models\User;
use Dashed\DashedEcommerceCore\Models\Order;
use Dashed\DashedEcommerceCore\Models\ShippingZone;
use Dashed\DashedEcommerceCore\Models\ShippingMethod;

/**
 * Het order-detail geeft de naam van de gekozen verzendmethode mee
 * (shipping_method), zoals de Filament ViewOrder-pagina die ook toont.
 */
it('order-detail: bevat de verzendmethode-naam', function () {
    $this->actingAs(User::factory()->create(['role' => 'admin']), 'sanctum');

    $zone = ShippingZone::create(['site_id' => 'site', 'name' => ['nl' => 'Nederland']]);
    $method = ShippingMethod::create([
        'shipping_zone_id' => $zone->id,
        'name' => ['nl' => 'PostNL pakket', 'en' => 'PostNL parcel'],
    ]);

    $order = Order::create([
        'site_id' => 'site',
        'email' => 'klant@example.com',
        'invoice_id' => 'INV-' . strtoupper(uniqid()),
        'status' => 'paid',
        'shipping_method_id' => $method->id,
    ]);

    $res = $this->getJson("/api/v1/orders/{$order->id}?detail=1", ['X-Site-Id' => 'site']);
    $res->assertOk();

    $data = $res->json('data') ?? $res->json();
    expect($data['shipping_method'])->toBe('PostNL pakket');
});
