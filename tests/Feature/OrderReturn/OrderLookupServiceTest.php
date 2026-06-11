<?php

use Dashed\DashedEcommerceCore\Models\Order;
use Dashed\DashedEcommerceCore\Services\OrderReturn\OrderLookupService;

beforeEach(function () {
    $this->service = app(OrderLookupService::class);
});

it('finds a paid order by invoice id and email (case-insensitive)', function () {
    $order = Order::create(['email' => 'Klant@Example.com', 'status' => 'paid', 'invoice_id' => 'INV-1001']);

    $found = $this->service->find('INV-1001', 'klant@example.com');

    expect($found?->id)->toBe($order->id);
});

it('finds a paid order by numeric id', function () {
    $order = Order::create(['email' => 'klant@example.com', 'status' => 'paid']);

    $found = $this->service->find((string) $order->id, 'klant@example.com');

    expect($found?->id)->toBe($order->id);
});

it('returns null when email does not match', function () {
    Order::create(['email' => 'klant@example.com', 'status' => 'paid', 'invoice_id' => 'INV-1001']);

    expect($this->service->find('INV-1001', 'iemand@anders.nl'))->toBeNull();
});

it('does not return concept or cancelled orders', function () {
    Order::create(['email' => 'a@b.nl', 'status' => 'concept', 'invoice_id' => 'C-1']);
    Order::create(['email' => 'a@b.nl', 'status' => 'cancelled', 'invoice_id' => 'X-1']);

    expect($this->service->find('C-1', 'a@b.nl'))->toBeNull()
        ->and($this->service->find('X-1', 'a@b.nl'))->toBeNull();
});
