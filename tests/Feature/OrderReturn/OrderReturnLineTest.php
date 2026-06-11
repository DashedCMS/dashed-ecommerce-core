<?php

use Dashed\DashedEcommerceCore\Models\Order;
use Dashed\DashedEcommerceCore\Models\OrderProduct;
use Dashed\DashedEcommerceCore\Models\OrderReturn;
use Dashed\DashedEcommerceCore\Models\ReturnReason;
use Dashed\DashedEcommerceCore\Models\OrderReturnLine;

it('links a return line to its return, order product and reason', function () {
    $order = Order::create(['email' => 'a@b.nl', 'status' => 'paid']);
    $orderProduct = OrderProduct::create(['order_id' => $order->id, 'name' => 'Shirt', 'quantity' => 3, 'price' => 20]);
    $return = OrderReturn::create(['order_id' => $order->id, 'email' => 'a@b.nl']);
    $reason = ReturnReason::create(['label' => ['nl' => 'Te klein'], 'is_active' => true]);

    $line = OrderReturnLine::create([
        'order_return_id' => $return->id,
        'order_product_id' => $orderProduct->id,
        'quantity' => 1,
        'return_reason_id' => $reason->id,
        'reason_note' => 'Valt klein uit',
    ]);

    expect($line->orderReturn->is($return))->toBeTrue()
        ->and($line->orderProduct->is($orderProduct))->toBeTrue()
        ->and($line->returnReason->is($reason))->toBeTrue()
        ->and($return->fresh()->lines)->toHaveCount(1)
        ->and($line->quantity)->toBe(1);
});
