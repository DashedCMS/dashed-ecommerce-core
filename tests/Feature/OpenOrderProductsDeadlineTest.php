<?php

use Illuminate\Support\Str;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Schema;
use Dashed\DashedEcommerceCore\Models\Order;
use Dashed\DashedEcommerceCore\Filament\Resources\OpenOrderProducts\OpenOrderProductResource;
use Dashed\DashedEcommerceCore\Filament\Resources\OpenOrderProducts\Tables\OpenOrderProductsTable;

function makeOpenDeadlineLine(string $name, ?string $deliverBy, ?string $shipBy = null): void
{
    $order = Order::create([
        'first_name' => 'T', 'last_name' => 'K', 'email' => 'k@example.com',
        'hash' => Str::random(32), 'total' => 10, 'site_id' => 'default', 'ip' => '127.0.0.1',
        'fulfillment_status' => 'unhandled', 'status' => 'paid', 'invoice_id' => 'DL-' . Str::random(8),
    ]);

    Order::query()->whereKey($order->id)->update([
        'deliver_by_date' => $deliverBy,
        'ship_by_date' => $shipBy,
    ]);

    $order->orderProducts()->create(['name' => $name, 'quantity' => 1, 'price' => 10]);
}

beforeEach(function () {
    Schema::disableForeignKeyConstraints();
    Carbon::setTestNow('2026-11-15 10:00:00');
});

afterEach(function () {
    Carbon::setTestNow();
});

it('sorts open order lines by deadline with lines without a deadline last', function () {
    makeOpenDeadlineLine('Geen', null);
    makeOpenDeadlineLine('Later', '2026-11-20');
    makeOpenDeadlineLine('Etsy', null, '2026-11-17');
    makeOpenDeadlineLine('Eerst', '2026-11-16');

    $asc = OpenOrderProductsTable::orderByDeadline(OpenOrderProductResource::getEloquentQuery(), 'asc')
        ->pluck('name')->all();
    $desc = OpenOrderProductsTable::orderByDeadline(OpenOrderProductResource::getEloquentQuery(), 'desc')
        ->pluck('name')->all();

    expect($asc)->toBe(['Eerst', 'Etsy', 'Later', 'Geen'])
        ->and($desc)->toBe(['Later', 'Etsy', 'Eerst', 'Geen']);
});

it('filters open order lines by deadline', function () {
    makeOpenDeadlineLine('Te laat', '2026-11-14');
    makeOpenDeadlineLine('Vandaag', '2026-11-15');
    makeOpenDeadlineLine('Morgen', null, '2026-11-16');
    makeOpenDeadlineLine('Geen', null);

    $names = fn (?string $value) => OpenOrderProductsTable::filterDeadline(OpenOrderProductResource::getEloquentQuery(), $value)
        ->orderBy('dashed__order_products.id')->pluck('name')->all();

    expect($names('late'))->toBe(['Te laat'])
        ->and($names('today'))->toBe(['Vandaag'])
        ->and($names('tomorrow'))->toBe(['Morgen'])
        ->and($names('today_or_earlier'))->toBe(['Te laat', 'Vandaag'])
        ->and($names(null))->toHaveCount(4);
});
