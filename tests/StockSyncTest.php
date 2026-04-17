<?php

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
