<?php

use Dashed\DashedEcommerceCore\Jobs\SyncProductStockJob;
use Dashed\DashedEcommerceCore\Models\Order;
use Dashed\DashedEcommerceCore\Models\OrderProduct;
use Dashed\DashedEcommerceCore\Models\Product;
use Dashed\DashedEcommerceCore\Models\ProductGroup;

function makeProductGroup(string $name, string $slug): ProductGroup
{
    return ProductGroup::create([
        'name' => ['en' => $name],
        'slug' => ['en' => $slug],
        'short_description' => ['en' => ''],
        'description' => ['en' => ''],
        'content' => ['en' => ''],
        'search_terms' => ['en' => ''],
        'site_ids' => ['default'],
    ]);
}

beforeEach(function () {
    $this->productGroup = makeProductGroup('Test Group', 'test-group');
    $this->productGroupB = makeProductGroup('Test Group B', 'test-group-b');
});

function createProduct(string $name, $productGroupId, array $overrides = []): Product
{
    // Use saveQuietly to bypass the saved event which dispatches
    // UpdateProductInformationJob (uses MySQL-specific GREATEST function).
    return Product::withoutEvents(function () use ($name, $productGroupId, $overrides) {
        return Product::create(array_merge([
            'name' => ['en' => $name],
            'slug' => ['en' => \Illuminate\Support\Str::slug($name)],
            'site_ids' => ['default'],
            'product_group_id' => $productGroupId,
            'use_stock' => true,
            'stock' => 50,
            'total_stock' => 50,
            'in_stock' => true,
            'stock_status' => 'in_stock',
            'price' => 10.00,
            'current_price' => 10.00,
        ], $overrides));
    });
}

it('has a stockSource belongsTo relationship', function () {
    $source = createProduct('Source', $this->productGroup->id);
    $receiver = createProduct('Receiver', $this->productGroupB->id, [
        'stock_source_product_id' => $source->id,
    ]);

    expect($receiver->stockSource)->toBeInstanceOf(Product::class);
    expect($receiver->stockSource->id)->toBe($source->id);
});

it('has a stockSyncedProducts hasMany relationship', function () {
    $source = createProduct('Source', $this->productGroup->id);
    createProduct('Receiver A', $this->productGroupB->id, [
        'stock_source_product_id' => $source->id,
    ]);
    createProduct('Receiver B', $this->productGroupB->id, [
        'stock_source_product_id' => $source->id,
    ]);

    expect($source->stockSyncedProducts)->toHaveCount(2);
});

it('returns all products in the sync group via stockSyncGroup', function () {
    $source = createProduct('Source', $this->productGroup->id);
    $receiverA = createProduct('Receiver A', $this->productGroupB->id, [
        'stock_source_product_id' => $source->id,
    ]);
    $receiverB = createProduct('Receiver B', $this->productGroupB->id, [
        'stock_source_product_id' => $source->id,
    ]);

    $group = $source->stockSyncGroup();
    expect($group)->toHaveCount(3);
    expect($group->pluck('id')->toArray())->toContain($source->id, $receiverA->id, $receiverB->id);

    $group = $receiverA->stockSyncGroup();
    expect($group)->toHaveCount(3);
});

it('returns null stockSyncGroup for unlinked products', function () {
    $product = createProduct('Standalone', $this->productGroup->id);
    expect($product->stockSyncGroup())->toBeNull();
});

it('returns the stock source product for a receiver', function () {
    $source = createProduct('Source', $this->productGroup->id);
    $receiver = createProduct('Receiver', $this->productGroupB->id, [
        'stock_source_product_id' => $source->id,
    ]);

    expect($receiver->getStockSourceProduct()->id)->toBe($source->id);
    expect($source->getStockSourceProduct()->id)->toBe($source->id);
});

it('syncs stock from source to all receivers', function () {
    $source = createProduct('Source', $this->productGroup->id, ['stock' => 100, 'total_stock' => 100]);
    $receiverA = createProduct('Receiver A', $this->productGroupB->id, [
        'stock_source_product_id' => $source->id,
        'stock' => 100,
        'total_stock' => 100,
    ]);
    $receiverB = createProduct('Receiver B', $this->productGroupB->id, [
        'stock_source_product_id' => $source->id,
        'stock' => 100,
        'total_stock' => 100,
    ]);

    $source->stock = 80;
    $source->saveQuietly();

    (new SyncProductStockJob($source))->handle();

    expect($receiverA->fresh()->stock)->toBe(80);
    expect($receiverB->fresh()->stock)->toBe(80);
});

it('syncs stock from receiver back to source and other receivers', function () {
    $source = createProduct('Source', $this->productGroup->id, ['stock' => 100, 'total_stock' => 100]);
    $receiverA = createProduct('Receiver A', $this->productGroupB->id, [
        'stock_source_product_id' => $source->id,
        'stock' => 100,
        'total_stock' => 100,
    ]);
    $receiverB = createProduct('Receiver B', $this->productGroupB->id, [
        'stock_source_product_id' => $source->id,
        'stock' => 100,
        'total_stock' => 100,
    ]);

    $receiverA->stock = 95;
    $receiverA->saveQuietly();

    (new SyncProductStockJob($receiverA))->handle();

    expect($source->fresh()->stock)->toBe(95);
    expect($receiverB->fresh()->stock)->toBe(95);
});

it('does nothing for products without sync group', function () {
    $standalone = createProduct('Standalone', $this->productGroup->id, ['stock' => 50]);

    (new SyncProductStockJob($standalone))->handle();

    expect($standalone->fresh()->stock)->toBe(50);
});

it('updates total_stock and in_stock for all synced products', function () {
    $source = createProduct('Source', $this->productGroup->id, [
        'stock' => 10,
        'total_stock' => 10,
        'in_stock' => true,
    ]);
    $receiver = createProduct('Receiver', $this->productGroupB->id, [
        'stock_source_product_id' => $source->id,
        'stock' => 10,
        'total_stock' => 10,
        'in_stock' => true,
    ]);

    $source->stock = 0;
    $source->saveQuietly();

    (new SyncProductStockJob($source))->handle();

    $freshReceiver = $receiver->fresh();
    expect($freshReceiver->stock)->toBe(0);
    expect($freshReceiver->total_stock)->toBe(0);
    expect((bool) $freshReceiver->in_stock)->toBeFalse();
});

it('calculates reserved stock across the entire sync group', function () {
    $source = createProduct('Source', $this->productGroup->id, ['stock' => 100, 'total_stock' => 100]);
    $receiverA = createProduct('Receiver A', $this->productGroupB->id, [
        'stock_source_product_id' => $source->id,
        'stock' => 100,
        'total_stock' => 100,
    ]);
    $receiverB = createProduct('Receiver B', $this->productGroupB->id, [
        'stock_source_product_id' => $source->id,
        'stock' => 100,
        'total_stock' => 100,
    ]);

    $orderA = Order::withoutEvents(function () {
        return Order::create([
            'status' => 'pending',
            'hash' => 'test-hash-a',
            'site_id' => 'default',
            'ip' => '127.0.0.1',
        ]);
    });
    OrderProduct::create([
        'order_id' => $orderA->id,
        'product_id' => $source->id,
        'quantity' => 2,
        'price' => 10,
    ]);

    $orderB = Order::withoutEvents(function () {
        return Order::create([
            'status' => 'pending',
            'hash' => 'test-hash-b',
            'site_id' => 'default',
            'ip' => '127.0.0.1',
        ]);
    });
    OrderProduct::create([
        'order_id' => $orderB->id,
        'product_id' => $receiverA->id,
        'quantity' => 3,
        'price' => 10,
    ]);

    $source->calculateReservedStock();

    expect($source->fresh()->reserved_stock)->toBe(5);
    expect($receiverA->fresh()->reserved_stock)->toBe(5);
    expect($receiverB->fresh()->reserved_stock)->toBe(5);
});

it('calculates reserved stock normally for non-synced products', function () {
    $standalone = createProduct('Standalone', $this->productGroup->id, ['stock' => 50]);

    $order = Order::withoutEvents(function () {
        return Order::create([
            'status' => 'pending',
            'hash' => 'test-hash-standalone',
            'site_id' => 'default',
            'ip' => '127.0.0.1',
        ]);
    });
    OrderProduct::create([
        'order_id' => $order->id,
        'product_id' => $standalone->id,
        'quantity' => 4,
        'price' => 10,
    ]);

    $standalone->calculateReservedStock();

    expect($standalone->fresh()->reserved_stock)->toBe(4);
});
