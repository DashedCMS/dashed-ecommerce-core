<?php

use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Storage;
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

it('downloads the return label when a label path is present', function () {
    Storage::fake('public');
    $return = makeReturnForStatus();
    Storage::disk('public')->put('dashed/orders/return-label-test.pdf', '%PDF-1.4 test');
    $return->update(['return_label_path' => 'dashed/orders/return-label-test.pdf']);

    $this->get(route('dashed.frontend.return-status.label', $return->hash))
        ->assertSuccessful();
});

it('returns 404 for the label route when no label exists', function () {
    $return = makeReturnForStatus();

    $this->get(route('dashed.frontend.return-status.label', $return->hash))
        ->assertNotFound();
});

it('returns 404 for the label route with an unknown hash', function () {
    $this->get(route('dashed.frontend.return-status.label', 'unknownhash0000000000000000000000'))
        ->assertNotFound();
});
