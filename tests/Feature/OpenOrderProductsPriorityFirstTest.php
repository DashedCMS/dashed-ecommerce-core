<?php

use Illuminate\Support\Str;
use Dashed\DashedEcommerceCore\Models\Order;
use Dashed\DashedEcommerceCore\Exports\OpenOrderProducts;
use Dashed\DashedEcommerceCore\Filament\Resources\OpenOrderProducts\OpenOrderProductResource;
use Dashed\DashedEcommerceCore\Filament\Resources\OpenOrderProducts\Pages\ListOpenOrderProducts;

function makePriorityTestOrder(string $invoiceId, string $sku, bool $priority): Order
{
    $order = Order::create([
        'first_name' => 'T', 'last_name' => 'K', 'email' => 'k@example.com',
        'hash' => Str::random(32), 'total' => 10, 'site_id' => 'default', 'ip' => '127.0.0.1',
        'fulfillment_status' => 'unhandled', 'invoice_id' => $invoiceId,
    ]);
    $order->forceFill(['is_priority' => $priority])->saveQuietly();

    $order->orderProducts()->create(['name' => 'Regel ' . $sku, 'sku' => $sku, 'quantity' => 1, 'price' => 10]);

    return $order;
}

it('puts priority orders on top, before any other sort', function () {
    $priority = makePriorityTestOrder('PRIO-1', 'SKU-PRIO', true);
    makePriorityTestOrder('NORM-1', 'SKU-NORM-1', false);
    makePriorityTestOrder('NORM-2', 'SKU-NORM-2', false);

    $tab = (new ListOpenOrderProducts)->getTabs()['all'];
    $rows = $tab->modifyQuery(OpenOrderProductResource::getEloquentQuery())
        ->orderByDesc('order_id')
        ->get();

    expect($rows->first()->order_id)->toBe($priority->id)
        ->and($rows)->toHaveCount(3);

    $rows = $tab->modifyQuery(OpenOrderProductResource::getEloquentQuery())
        ->orderBy('order_id')
        ->get();

    expect($rows->first()->order_id)->toBe($priority->id);
});

it('puts groups containing a priority order on top in the grouped tabs', function () {
    makePriorityTestOrder('NORM-1', 'SKU-A', false);
    makePriorityTestOrder('PRIO-1', 'SKU-B', true);
    makePriorityTestOrder('NORM-2', 'SKU-C', false);

    $tab = (new ListOpenOrderProducts)->getTabs()['grouped'];
    $rows = $tab->modifyQuery(OpenOrderProductResource::getEloquentQuery())
        ->orderBy('sku')
        ->get();

    expect($rows->first()->sku)->toBe('SKU-B')
        ->and($rows)->toHaveCount(3);

    $groupTab = (new ListOpenOrderProducts)->getTabs()['grouped_product_group'];
    expect($groupTab->modifyQuery(OpenOrderProductResource::getEloquentQuery())->get())->toHaveCount(1);
});

it('puts priority orders on top in the excel export', function () {
    makePriorityTestOrder('NORM-1', 'SKU-A', false);
    makePriorityTestOrder('PRIO-1', 'SKU-B', true);
    makePriorityTestOrder('NORM-2', 'SKU-C', false);

    expect((new OpenOrderProducts('all'))->array()[1][0])->toBe('PRIO-1')
        ->and((new OpenOrderProducts('grouped'))->array()[1][2])->toBe('SKU-B');
});
