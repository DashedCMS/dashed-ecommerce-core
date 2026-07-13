<?php

use Dashed\DashedEcommerceCore\Models\Order;
use Dashed\DashedEcommerceCore\Models\OrderReturn;
use Dashed\DashedEcommerceCore\Models\OrderReturnMessage;

uses(\Illuminate\Foundation\Testing\RefreshDatabase::class);

it('stores a message with a sender and links it to the return', function () {
    $order = Order::create(['email' => 'a@b.nl', 'status' => 'paid']);
    $return = OrderReturn::create(['order_id' => $order->id, 'email' => 'a@b.nl']);

    $message = OrderReturnMessage::create([
        'order_return_id' => $return->id,
        'sender' => OrderReturnMessage::SENDER_ADMIN,
        'message' => '<p>Hallo</p>',
    ]);

    expect($message->sender)->toBe('admin')
        ->and($message->orderReturn->is($return))->toBeTrue()
        ->and($return->messages()->count())->toBe(1);
});

it('orders messages chronologically on the relation', function () {
    $order = Order::create(['email' => 'a@b.nl', 'status' => 'paid']);
    $return = OrderReturn::create(['order_id' => $order->id, 'email' => 'a@b.nl']);

    $first = OrderReturnMessage::create(['order_return_id' => $return->id, 'sender' => 'admin', 'message' => 'een']);
    $second = OrderReturnMessage::create(['order_return_id' => $return->id, 'sender' => 'customer', 'message' => 'twee']);

    expect($return->messages()->pluck('id')->all())->toBe([$first->id, $second->id]);
});
