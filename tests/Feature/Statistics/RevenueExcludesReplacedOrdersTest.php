<?php

use Dashed\DashedEcommerceCore\Models\Order;
use Dashed\DashedEcommerceCore\Classes\OrderStatistics;

it('counts a modified order once: the replacement, not the replaced original', function () {
    $original = Order::create(['email' => 'a@b.nl', 'status' => 'paid', 'total' => 100]);
    $replacement = Order::create(['email' => 'a@b.nl', 'status' => 'paid', 'total' => 120]);
    $original->replaced_by_order_id = $replacement->id;
    $original->save();
    Order::create(['email' => 'a@b.nl', 'status' => 'return', 'total' => -100, 'credit_for_order_id' => $original->id]);
    Order::create(['email' => 'c@d.nl', 'status' => 'paid', 'total' => 50]);

    expect((float) Order::query()->countsAsRevenue()->sum('total'))->toBe(170.0)
        ->and(Order::query()->countsAsRevenue()->count())->toBe(2)
        ->and(OrderStatistics::totals(Order::query()->countsAsRevenue())['amount'])->toBe(170.0);
});

it('still leaves ordinary credit orders out of paid revenue', function () {
    $order = Order::create(['email' => 'a@b.nl', 'status' => 'paid', 'total' => 80]);
    Order::create(['email' => 'a@b.nl', 'status' => 'return', 'total' => -30, 'credit_for_order_id' => $order->id]);

    expect((float) Order::query()->countsAsRevenue()->sum('total'))->toBe(80.0);
});
