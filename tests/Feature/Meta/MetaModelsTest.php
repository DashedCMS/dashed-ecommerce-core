<?php

use Illuminate\Database\QueryException;
use Dashed\DashedEcommerceCore\Models\Order;
use Dashed\DashedEcommerceCore\Models\MetaCapiEvent;
use Dashed\DashedEcommerceCore\Models\OrderTracking;

it('koppelt klantsignalen aan een order en cast consent naar een boolean', function () {
    $order = Order::create(['status' => 'pending', 'total' => 10, 'subtotal' => 10, 'btw' => 0, 'discount' => 0]);

    OrderTracking::create([
        'order_id' => $order->id,
        'meta_fbp' => 'fb.1.1700000000000.1234567890',
        'marketing_consent' => 1,
    ]);

    expect($order->tracking)->toBeInstanceOf(OrderTracking::class)
        ->and($order->tracking->marketing_consent)->toBeTrue()
        ->and($order->tracking->meta_fbc)->toBeNull();
});

it('staat per order maar één rij klantsignalen toe', function () {
    $order = Order::create(['status' => 'pending', 'total' => 10, 'subtotal' => 10, 'btw' => 0, 'discount' => 0]);
    OrderTracking::create(['order_id' => $order->id, 'marketing_consent' => true]);

    expect(fn () => OrderTracking::create(['order_id' => $order->id, 'marketing_consent' => true]))
        ->toThrow(QueryException::class);
});

it('weigert een tweede event met hetzelfde event_id', function () {
    MetaCapiEvent::create(['site_id' => 'site', 'event_name' => 'Purchase', 'event_id' => 'purchase_1']);

    expect(fn () => MetaCapiEvent::create(['site_id' => 'site', 'event_name' => 'Purchase', 'event_id' => 'purchase_1']))
        ->toThrow(QueryException::class);
});

it('bewaart payload en response als array en begint op pending', function () {
    $event = MetaCapiEvent::create([
        'site_id' => 'site',
        'event_name' => 'Purchase',
        'event_id' => 'purchase_2',
        'payload' => ['event_name' => 'Purchase'],
    ])->fresh();

    expect($event->status)->toBe(MetaCapiEvent::STATUS_PENDING)
        ->and($event->attempts)->toBe(0)
        ->and($event->payload)->toBe(['event_name' => 'Purchase'])
        ->and($event->response)->toBeNull();
});
