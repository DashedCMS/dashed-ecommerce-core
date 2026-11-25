<?php

use Dashed\DashedCore\Models\User;
use Dashed\DashedEcommerceCore\Models\Order;
use Dashed\DashedEcommerceCore\Models\OrderLog;
use Dashed\DashedEcommerceCore\Models\OrderProduct;

function onHoldOrder(array $attrs = []): Order
{
    return Order::create(array_merge([
        'site_id' => 'site',
        'email' => 'klant@example.com',
        'invoice_id' => 'INV-' . strtoupper(uniqid()),
        'status' => 'paid',
        'fulfillment_status' => 'packed',
    ], $attrs));
}

it('zet een bestelling on hold met reden en haalt hem er weer af', function () {
    $user = User::factory()->create(['is_superadmin' => true]);
    $this->actingAs($user, 'sanctum');
    $order = onHoldOrder();

    $res = $this->postJson("/api/v1/orders/{$order->id}/hold", ['on' => true, 'reason' => 'Wacht op akkoord klant'], ['X-Site-Id' => 'site']);
    $res->assertOk()
        ->assertJsonPath('data.on_hold', true)
        ->assertJsonPath('data.on_hold_reason', 'Wacht op akkoord klant')
        ->assertJsonPath('data.on_hold_by.id', $user->id);

    $order->refresh();
    expect($order->isOnHold())->toBeTrue()
        ->and($order->fulfillment_status)->toBe('packed')
        ->and(OrderLog::where('order_id', $order->id)->where('tag', 'order.on-hold.placed')->value('note'))->toBe('Wacht op akkoord klant');

    $this->postJson("/api/v1/orders/{$order->id}/hold", ['on' => false], ['X-Site-Id' => 'site'])
        ->assertOk()->assertJsonPath('data.on_hold', false);

    expect($order->fresh()->isOnHold())->toBeFalse()
        ->and(OrderLog::where('order_id', $order->id)->where('tag', 'order.on-hold.released')->exists())->toBeTrue();
});

it('filtert orders en open orderregels op on hold', function () {
    $this->actingAs(User::factory()->create(['is_superadmin' => true]), 'sanctum');
    $held = onHoldOrder(['fulfillment_status' => 'unhandled']);
    $held->putOnHold('Adres klopt niet');
    $free = onHoldOrder(['fulfillment_status' => 'unhandled']);
    foreach ([$held, $free] as $o) {
        OrderProduct::create(['order_id' => $o->id, 'name' => 'Vaas', 'quantity' => 1, 'price' => 10]);
    }

    $rows = collect($this->getJson('/api/v1/open-order-products?on_hold=1', ['X-Site-Id' => 'site'])->assertOk()->json('data'));
    expect($rows->pluck('order_id')->unique()->values()->all())->toBe([$held->id])
        ->and($rows->first()['on_hold'])->toBeTrue()
        ->and($rows->first()['on_hold_reason'])->toBe('Adres klopt niet');

    $rows = collect($this->getJson('/api/v1/open-order-products?on_hold=0', ['X-Site-Id' => 'site'])->assertOk()->json('data'));
    expect($rows->pluck('order_id')->unique()->values()->all())->toBe([$free->id]);

    $ids = collect($this->getJson('/api/v1/orders?on_hold=1', ['X-Site-Id' => 'site'])->assertOk()->json('data'))->pluck('id')->all();
    expect($ids)->toContain($held->id)->not->toContain($free->id);
});

it('heft on hold op zodra de bestelling verzonden is', function () {
    $order = onHoldOrder();
    $order->putOnHold('Wacht op klant', userId: null);

    $order->changeFulfillmentStatus('shipped');

    expect($order->fresh()->isOnHold())->toBeFalse()
        ->and(OrderLog::where('order_id', $order->id)->where('tag', 'order.on-hold.auto-released')->exists())->toBeTrue();
});
