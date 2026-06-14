<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Artisan;
use Dashed\DashedEcommerceCore\Models\Product;
use Dashed\DashedEcommerceCore\Models\ProductGroup;

beforeEach(function () {
    // Testbench start zonder geconfigureerde sites; registreer er een zodat
    // Sites::getSites()/getActive() een echte site teruggeven en de
    // per-site-loop in het command draait.
    cms()->builder('sites', [[
        'id' => 'default',
        'name' => 'Default',
        'locales' => ['en'],
    ]]);
});

function lowStockSiteId(): string
{
    return \Dashed\DashedCore\Classes\Sites::getActive() ?: 'default';
}

function makeLowStockProductGroup(): ProductGroup
{
    return ProductGroup::create([
        'name' => ['en' => 'Group ' . uniqid()],
        'slug' => ['en' => 'group-' . uniqid()],
        'short_description' => ['en' => ''],
        'description' => ['en' => ''],
        'content' => ['en' => ''],
        'search_terms' => ['en' => ''],
        'site_ids' => [lowStockSiteId()],
    ]);
}

function makeLowStockProduct(array $overrides = []): Product
{
    $group = makeLowStockProductGroup();

    return Product::withoutEvents(fn () => Product::create(array_merge([
        'name' => ['en' => 'Vaas ' . uniqid()],
        'slug' => ['en' => 'vaas-' . uniqid()],
        'site_ids' => [lowStockSiteId()],
        'product_group_id' => $group->id,
        'use_stock' => true,
        'low_stock_notification' => true,
        'low_stock_notification_limit' => 5,
        'stock' => 2,
        'total_stock' => 2,
        'in_stock' => true,
        'stock_status' => 'in_stock',
        'price' => 10.00,
        'current_price' => 10.00,
    ], $overrides)));
}

it('alerts a product that is at or below its threshold', function () {
    $product = makeLowStockProduct(['stock' => 2, 'low_stock_notification_limit' => 5]);

    expect($product->low_stock_alerted_at)->toBeNull();

    Artisan::call('dashed:check-low-stock');

    expect($product->fresh()->low_stock_alerted_at)->not->toBeNull();
});

it('does not re-alert within 24 hours (dedup)', function () {
    $product = makeLowStockProduct();

    Artisan::call('dashed:check-low-stock');
    $firstAlert = $product->fresh()->low_stock_alerted_at;
    expect($firstAlert)->not->toBeNull();

    // Immediate second pass must not bump the timestamp.
    Artisan::call('dashed:check-low-stock');
    $secondAlert = $product->fresh()->low_stock_alerted_at;

    expect($secondAlert->equalTo($firstAlert))->toBeTrue();
});

it('re-alerts when the previous alert is older than 24 hours', function () {
    $product = makeLowStockProduct();
    $product->forceFill(['low_stock_alerted_at' => now()->subDays(2)])->saveQuietly();

    Artisan::call('dashed:check-low-stock');

    expect($product->fresh()->low_stock_alerted_at->isToday())->toBeTrue();
});

it('clears the alert flag when stock recovers above the threshold', function () {
    $product = makeLowStockProduct([
        'stock' => 20,
        'low_stock_notification_limit' => 5,
    ]);
    $product->forceFill(['low_stock_alerted_at' => now()->subHours(2)])->saveQuietly();

    Artisan::call('dashed:check-low-stock');

    expect($product->fresh()->low_stock_alerted_at)->toBeNull();
});

it('does not alert a product with stock above the threshold', function () {
    $product = makeLowStockProduct([
        'stock' => 20,
        'low_stock_notification_limit' => 5,
    ]);

    Artisan::call('dashed:check-low-stock');

    expect($product->fresh()->low_stock_alerted_at)->toBeNull();
});

it('does not alert when low_stock_notification is disabled', function () {
    $product = makeLowStockProduct([
        'stock' => 2,
        'low_stock_notification' => false,
    ]);

    Artisan::call('dashed:check-low-stock');

    expect($product->fresh()->low_stock_alerted_at)->toBeNull();
});
