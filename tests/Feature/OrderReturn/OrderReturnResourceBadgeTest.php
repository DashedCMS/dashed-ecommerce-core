<?php

use Dashed\DashedEcommerceCore\Models\Order;
use Dashed\DashedEcommerceCore\Models\OrderReturn;
use Dashed\DashedEcommerceCore\Filament\Resources\OrderReturnResource;

it('returns null badge when there are no open returns', function () {
    $order = Order::create(['email' => 'a@b.nl', 'status' => 'paid']);
    OrderReturn::create(['order_id' => $order->id, 'email' => 'a@b.nl', 'status' => OrderReturn::STATUS_HANDLED]);

    expect(OrderReturnResource::getNavigationBadge())->toBeNull();
});

it('counts every non-handled return in the badge', function () {
    $order = Order::create(['email' => 'a@b.nl', 'status' => 'paid']);
    OrderReturn::create(['order_id' => $order->id, 'email' => 'a@b.nl']);
    OrderReturn::create(['order_id' => $order->id, 'email' => 'a@b.nl', 'status' => OrderReturn::STATUS_APPROVED]);
    OrderReturn::create(['order_id' => $order->id, 'email' => 'a@b.nl', 'status' => OrderReturn::STATUS_REJECTED]);
    OrderReturn::create(['order_id' => $order->id, 'email' => 'a@b.nl', 'status' => OrderReturn::STATUS_HANDLED]);

    expect(OrderReturnResource::getNavigationBadge())->toBe('3')
        ->and(OrderReturnResource::getNavigationBadgeColor())->toBe('warning');
});
