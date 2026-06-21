<?php

use Dashed\DashedEcommerceCore\Models\Order;
use Dashed\DashedEcommerceCore\Models\OrderProduct;
use Dashed\DashedEcommerceCore\Http\Resources\Api\Mobile\OrderProductResource;

uses(\Illuminate\Foundation\Testing\RefreshDatabase::class);

it('persists the added_via source on an order product', function () {
    $order = Order::create([
        'invoice_id' => 'INV-'.uniqid(),
        'status' => 'paid',
        'order_origin' => 'own',
        'total' => 10.0,
    ]);

    $orderProduct = OrderProduct::create([
        'order_id' => $order->id,
        'name' => 'Extra product',
        'quantity' => 1,
        'price' => 10.0,
        'added_via' => 'cross_sell',
    ]);

    expect($orderProduct->fresh()->added_via)->toBe('cross_sell');
});

it('exposes added_via in the mobile order product resource', function () {
    $order = Order::create([
        'invoice_id' => 'INV-'.uniqid(),
        'status' => 'paid',
        'order_origin' => 'own',
        'total' => 10.0,
    ]);

    $orderProduct = OrderProduct::create([
        'order_id' => $order->id,
        'name' => 'Extra product',
        'quantity' => 1,
        'price' => 10.0,
        'added_via' => 'cross_sell',
    ]);

    $array = (new OrderProductResource($orderProduct))->toArray(request());

    expect($array['added_via'])->toBe('cross_sell');
});
