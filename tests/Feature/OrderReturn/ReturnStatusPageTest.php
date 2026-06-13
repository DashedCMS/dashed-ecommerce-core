<?php

use Illuminate\Support\Facades\RateLimiter;
use Dashed\DashedEcommerceCore\Models\Order;
use Dashed\DashedEcommerceCore\Models\OrderProduct;
use Dashed\DashedEcommerceCore\Models\OrderReturn;
use Dashed\DashedEcommerceCore\Models\OrderReturnLine;

beforeEach(function () {
    RateLimiter::clear('return-status:127.0.0.1');
});

function makeReturnForStatus(string $status = OrderReturn::STATUS_REQUESTED, array $attrs = []): OrderReturn
{
    $order = Order::create(['email' => 'a@b.nl', 'status' => 'paid', 'invoice_id' => 'INV-500']);
    $op = OrderProduct::create(['order_id' => $order->id, 'name' => 'Shirt', 'quantity' => 2, 'price' => 20]);
    $return = OrderReturn::create(array_merge(['order_id' => $order->id, 'email' => 'a@b.nl', 'status' => $status], $attrs));
    OrderReturnLine::create(['order_return_id' => $return->id, 'order_product_id' => $op->id, 'quantity' => 1]);

    return $return->fresh();
}

it('shows the status page for a valid hash', function () {
    $return = makeReturnForStatus();

    $this->get(route('dashed.frontend.return-status', $return->hash))
        ->assertOk()
        ->assertSee('Shirt');
});

it('returns 404 for an unknown hash', function () {
    $this->get(route('dashed.frontend.return-status', 'nonexistenthashvalue000000000000'))
        ->assertNotFound();
});

it('shows the rejection reason for a rejected return', function () {
    $return = makeReturnForStatus(OrderReturn::STATUS_REJECTED, ['rejected_reason' => 'Buiten de termijn', 'rejected_at' => now()]);

    $this->get(route('dashed.frontend.return-status', $return->hash))
        ->assertOk()
        ->assertSee('Buiten de termijn');
});

it('does not show a rejection reason for a non-rejected return', function () {
    $return = makeReturnForStatus(OrderReturn::STATUS_APPROVED, ['approved_at' => now()]);

    $this->get(route('dashed.frontend.return-status', $return->hash))
        ->assertOk()
        ->assertDontSee('Buiten de termijn');
});

it('rate-limits the status page after too many attempts', function () {
    $return = makeReturnForStatus();
    $url = route('dashed.frontend.return-status', $return->hash);

    for ($i = 0; $i < 30; $i++) {
        $this->get($url)->assertOk();
    }
    $this->get($url)->assertStatus(429);
});
