<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Mail;
use Dashed\DashedEcommerceCore\Models\Product;
use Dashed\DashedEcommerceCore\Models\ProductGroup;
use Dashed\DashedEcommerceCore\Mail\BackInStockMail;
use Dashed\DashedEcommerceCore\Models\StockNotification;
use Dashed\DashedEcommerceCore\Services\BackInStockService;

// Patroon gekopieerd uit tests/StockSyncTest.php: ProductGroup eerst aanmaken,
// dan Product::withoutEvents(...) om de saved-event-dispatch (UpdateProductInformationJob,
// MySQL-specifieke GREATEST) te vermijden op sqlite.
function makeBackInStockProductGroup(): ProductGroup
{
    return ProductGroup::create([
        'name' => ['en' => 'Test Group ' . uniqid()],
        'slug' => ['en' => 'test-group-' . uniqid()],
        'short_description' => ['en' => ''],
        'description' => ['en' => ''],
        'content' => ['en' => ''],
        'search_terms' => ['en' => ''],
        'site_ids' => ['default'],
    ]);
}

function makeBackInStockProduct(array $overrides = []): Product
{
    $group = makeBackInStockProductGroup();

    return Product::withoutEvents(function () use ($group, $overrides) {
        return Product::create(array_merge([
            'name' => ['en' => 'Vaas'],
            'slug' => ['en' => 'vaas-' . uniqid()],
            'site_ids' => ['default'],
            'product_group_id' => $group->id,
            'use_stock' => true,
            'stock' => 0,
            'total_stock' => 0,
            'in_stock' => false,
            'stock_status' => 'out_of_stock',
            'price' => 10.00,
            'current_price' => 10.00,
        ], $overrides));
    });
}

it('subscribes without creating a duplicate pending row', function () {
    $product = makeBackInStockProduct();

    // Confirm the baseline: a fresh out-of-stock product is not directly sellable.
    expect($product->hasDirectSellableStock())->toBeFalse();

    $service = app(BackInStockService::class);
    $service->subscribe('main', $product->id, 'klant@example.com');
    $service->subscribe('main', $product->id, 'klant@example.com');

    expect(StockNotification::pending()->where('product_id', $product->id)->count())->toBe(1);
});

it('lowercases and trims the email so idempotency is not defeated by casing/whitespace', function () {
    $product = makeBackInStockProduct();

    $service = app(BackInStockService::class);
    $service->subscribe('main', $product->id, ' Klant@Example.com ');
    $service->subscribe('main', $product->id, 'klant@example.com');

    expect(StockNotification::pending()->where('product_id', $product->id)->count())->toBe(1);
});

it('mails only once the product becomes buyable again and marks the row notified', function () {
    Mail::fake();

    $product = makeBackInStockProduct();

    app(BackInStockService::class)->subscribe('main', $product->id, 'a@example.com');

    // No stock yet -> hasDirectSellableStock() is false -> nothing sent.
    expect($product->hasDirectSellableStock())->toBeFalse();
    expect(app(BackInStockService::class)->notifyPending('main'))->toBe(0);
    Mail::assertNothingQueued();
    expect(StockNotification::pending()->where('product_id', $product->id)->count())->toBe(1);

    // Add stock -> buyable again.
    $product->update(['stock' => 5, 'total_stock' => 5, 'in_stock' => true, 'stock_status' => 'in_stock']);
    expect($product->fresh()->hasDirectSellableStock())->toBeTrue();

    $sent = app(BackInStockService::class)->notifyPending('main');

    expect($sent)->toBe(1);
    Mail::assertQueued(BackInStockMail::class);
    expect(StockNotification::pending()->count())->toBe(0);

    $row = StockNotification::where('product_id', $product->id)->first();
    expect($row->notified_at)->not->toBeNull();

    // Idempotent: second run must not send again.
    expect(app(BackInStockService::class)->notifyPending('main'))->toBe(0);
    Mail::assertQueued(BackInStockMail::class, 1);
});

it('does not notify pending subscriptions for a different site_id', function () {
    Mail::fake();

    $product = makeBackInStockProduct(['stock' => 5, 'total_stock' => 5, 'in_stock' => true, 'stock_status' => 'in_stock']);

    app(BackInStockService::class)->subscribe('other-site', $product->id, 'a@example.com');

    expect(app(BackInStockService::class)->notifyPending('main'))->toBe(0);
    Mail::assertNothingQueued();
    expect(StockNotification::pending()->count())->toBe(1);
});

it('het command verstuurt de wachtende meldingen en markeert ze', function () {
    Mail::fake();

    $product = makeBackInStockProduct(['stock' => 5, 'total_stock' => 5, 'in_stock' => true, 'stock_status' => 'in_stock']);
    app(BackInStockService::class)->subscribe('main', $product->id, 'klant@example.com');

    $this->artisan('dashed:notify-back-in-stock')->assertSuccessful();

    Mail::assertQueued(BackInStockMail::class);
    expect(StockNotification::where('product_id', $product->id)->first()->notified_at)->not->toBeNull();
});
