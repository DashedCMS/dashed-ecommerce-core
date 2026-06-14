<?php

declare(strict_types=1);

use Dashed\DashedCore\Models\User;
use Dashed\DashedEcommerceCore\Models\Order;
use Dashed\DashedEcommerceMyParcel\Models\MyParcelOrder;
use Dashed\DashedEcommerceVeloyd\Models\VeloydOrder;

/**
 * Verzend-hub (GET api/v1/shipments): geaggregeerd overzicht van alle zendingen
 * over Veloyd + MyParcel heen, site-bewust, met dezelfde genormaliseerde
 * status-badges als de per-order labellijst. Plus de retourlabel-route.
 */
function makeShipmentOrder(string $siteId = 'site', array $overrides = []): Order
{
    $order = Order::create(array_merge([
        'invoice_id' => 'INV-' . uniqid(),
        'status' => 'paid',
        'order_origin' => 'own',
        'first_name' => 'Jan',
        'last_name' => 'Jansen',
        'total' => 10,
    ], $overrides));

    // site_id zit niet in $fillable van Order in alle paden; expliciet zetten.
    $order->site_id = $siteId;
    $order->save();

    return $order;
}

it('aggregates shipments from both carriers for the active site', function () {
    $this->actingAs(User::factory()->create(['role' => 'admin']), 'sanctum');

    $order = makeShipmentOrder();

    VeloydOrder::create([
        'order_id' => $order->id,
        'shipment_id' => 'V-1',
        'carrier' => 'PostNL',
        'status' => 'shipped',
        'is_return' => false,
        'track_and_trace' => [['3STESTV' => 'https://t/3STESTV']],
    ]);
    MyParcelOrder::create([
        'order_id' => $order->id,
        'shipment_id' => 'M-1',
        'carrier' => 'DHL',
        'status' => 'delivered',
        'is_return' => true,
        'track_and_trace' => [['3STESTM' => 'https://t/3STESTM']],
    ]);

    $res = $this->getJson('/api/v1/shipments', ['X-Site-Id' => 'site']);

    $res->assertStatus(200);
    expect($res->json('meta.total'))->toBe(2);

    $byCarrier = collect($res->json('data'))->keyBy('carrier');

    expect($byCarrier['veloyd']['status'])->toBe('shipped')
        ->and($byCarrier['veloyd']['status_label'])->toBe('Verzonden')
        ->and($byCarrier['veloyd']['status_tone'])->toBe('success')
        ->and($byCarrier['veloyd']['is_return'])->toBeFalse()
        ->and($byCarrier['veloyd']['track_trace'])->toBe(['3STESTV'])
        ->and($byCarrier['veloyd']['invoice_id'])->toBe($order->invoice_id)
        ->and($byCarrier['veloyd']['customer_name'])->toBe('Jan Jansen');

    expect($byCarrier['myparcel']['status'])->toBe('delivered')
        ->and($byCarrier['myparcel']['status_label'])->toBe('Geleverd')
        ->and($byCarrier['myparcel']['is_return'])->toBeTrue();
});

it('filters shipments by carrier', function () {
    $this->actingAs(User::factory()->create(['role' => 'admin']), 'sanctum');

    $order = makeShipmentOrder();
    VeloydOrder::create(['order_id' => $order->id, 'shipment_id' => 'V-1', 'carrier' => 'PostNL', 'status' => 'shipped']);
    MyParcelOrder::create(['order_id' => $order->id, 'shipment_id' => 'M-1', 'carrier' => 'DHL', 'status' => 'shipped']);

    $res = $this->getJson('/api/v1/shipments?carrier=veloyd', ['X-Site-Id' => 'site']);

    $res->assertStatus(200);
    expect($res->json('meta.total'))->toBe(1)
        ->and($res->json('data.0.carrier'))->toBe('veloyd');
});

it('filters shipments by is_return', function () {
    $this->actingAs(User::factory()->create(['role' => 'admin']), 'sanctum');

    $order = makeShipmentOrder();
    VeloydOrder::create(['order_id' => $order->id, 'shipment_id' => 'V-1', 'carrier' => 'PostNL', 'status' => 'shipped', 'is_return' => false]);
    VeloydOrder::create(['order_id' => $order->id, 'shipment_id' => 'V-2', 'carrier' => 'PostNL', 'status' => 'returned', 'is_return' => true]);

    $res = $this->getJson('/api/v1/shipments?is_return=1', ['X-Site-Id' => 'site']);

    $res->assertStatus(200);
    expect($res->json('meta.total'))->toBe(1)
        ->and($res->json('data.0.is_return'))->toBeTrue()
        ->and($res->json('data.0.status'))->toBe('returned');
});

it('scopes shipments to the active site', function () {
    $this->actingAs(User::factory()->create(['role' => 'admin']), 'sanctum');

    $mine = makeShipmentOrder('site');
    $other = makeShipmentOrder('other');

    VeloydOrder::create(['order_id' => $mine->id, 'shipment_id' => 'V-MINE', 'carrier' => 'PostNL', 'status' => 'shipped']);
    VeloydOrder::create(['order_id' => $other->id, 'shipment_id' => 'V-OTHER', 'carrier' => 'PostNL', 'status' => 'shipped']);

    $res = $this->getJson('/api/v1/shipments', ['X-Site-Id' => 'site']);

    $res->assertStatus(200);
    expect($res->json('meta.total'))->toBe(1)
        ->and($res->json('data.0.id'))->toBe(VeloydOrder::where('shipment_id', 'V-MINE')->first()->id);
});

it('rejects shipments without the orders.read ability', function () {
    $this->actingAs(User::factory()->create(['role' => 'customer']), 'sanctum');

    $this->getJson('/api/v1/shipments', ['X-Site-Id' => 'site'])->assertStatus(403);
});

it('wires the return-label route, gated by orders.write, with a structured error when no carrier is known', function () {
    $this->actingAs(User::factory()->create(['role' => 'admin']), 'sanctum');

    $order = makeShipmentOrder();

    // Geen bestaande carrier-rij → geen carrier bekend → nette 422 met ok:false.
    $res = $this->postJson("/api/v1/orders/{$order->id}/return-label", [], ['X-Site-Id' => 'site']);

    $res->assertStatus(422)
        ->assertJsonPath('ok', false);
    expect($res->json('message'))->toBeString()->not->toBe('');
});

it('rejects the return-label action without the orders.write ability', function () {
    $this->actingAs(User::factory()->create(['role' => 'customer']), 'sanctum');

    $order = makeShipmentOrder();

    $this->postJson("/api/v1/orders/{$order->id}/return-label", [], ['X-Site-Id' => 'site'])
        ->assertStatus(403);
});
