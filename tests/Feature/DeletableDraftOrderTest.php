<?php

use Dashed\DashedEcommerceCore\Models\Order;
use Dashed\DashedEcommerceCore\Models\OrderProduct;
use Dashed\DashedEcommerceCore\Classes\ConceptOrderService;

uses(\Illuminate\Foundation\Testing\RefreshDatabase::class);

it('marks a concept draft as deletable', function () {
    $order = Order::create(['email' => 'a@b.nl', 'status' => Order::STATUS_CONCEPT]);

    expect($order->isDeletableDraft())->toBeTrue();
});

it('marks an unpaid proforma as deletable', function () {
    $order = Order::create(['email' => 'a@b.nl', 'status' => 'pending', 'is_proforma' => true, 'invoice_id' => 'PROFORMA']);

    expect($order->isDeletableDraft())->toBeTrue();
});

it('does not mark a paid order as deletable', function () {
    $order = Order::create(['email' => 'a@b.nl', 'status' => 'paid', 'is_proforma' => true]);

    expect($order->isDeletableDraft())->toBeFalse();
});

it('does not mark an order with a real invoice as deletable', function () {
    $order = Order::create(['email' => 'a@b.nl', 'status' => Order::STATUS_CONCEPT, 'invoice_id' => '2026-0001']);

    expect($order->isDeletableDraft())->toBeFalse();
});

it('soft-deletes a draft order and its products', function () {
    $order = Order::create(['email' => 'a@b.nl', 'status' => Order::STATUS_CONCEPT]);
    $product = OrderProduct::create(['order_id' => $order->id, 'name' => 'X', 'quantity' => 1, 'price' => 10]);

    ConceptOrderService::deleteDraft($order);

    expect(Order::find($order->id))->toBeNull()
        ->and(Order::withTrashed()->find($order->id))->not->toBeNull()
        ->and(OrderProduct::find($product->id))->toBeNull()
        ->and(OrderProduct::withTrashed()->find($product->id))->not->toBeNull();
});

it('refuses to delete a non-draft order', function () {
    $order = Order::create(['email' => 'a@b.nl', 'status' => 'paid']);

    expect(fn () => ConceptOrderService::deleteDraft($order))->toThrow(LogicException::class);
});
