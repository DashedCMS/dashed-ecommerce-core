<?php

use Dashed\DashedEcommerceCore\Models\Order;
use Dashed\DashedCore\Models\Customsetting;
use Dashed\DashedEcommerceCore\Filament\Widgets\Statistics\DoelenWidget;

function makeDoelenPaidOrder(float $total): Order
{
    return Order::withoutEvents(fn () => Order::create([
        'total' => $total,
        'status' => 'paid',
        'email' => 'klant@example.com',
        'site_id' => 'site',
        'ip' => '127.0.0.1',
        'hash' => bin2hex(random_bytes(8)),
    ]));
}

it('computes target progress per period', function () {
    Customsetting::set('dashboard_revenue_target_month', 1000, 'site');
    Customsetting::set('dashboard_orders_target_month', 4, 'site');
    makeDoelenPaidOrder(250);
    makeDoelenPaidOrder(250);

    $rows = (new DoelenWidget())->rows();
    $month = collect($rows)->firstWhere('key', 'month');

    expect($month['revenue'])->toBe(500.0)
        ->and($month['revenueTarget'])->toBe(1000.0)
        ->and($month['revenuePct'])->toBe(50)
        ->and($month['orders'])->toBe(2)
        ->and($month['ordersTarget'])->toBe(4)
        ->and($month['ordersPct'])->toBe(50)
        ->and($month['hasTarget'])->toBeTrue();
});

it('marks periods without a target and never divides by zero', function () {
    makeDoelenPaidOrder(99);

    $rows = (new DoelenWidget())->rows();
    $today = collect($rows)->firstWhere('key', 'today');

    expect($today['revenueTarget'])->toBe(0.0)
        ->and($today['revenuePct'])->toBe(0)
        ->and($today['hasTarget'])->toBeFalse();
});
