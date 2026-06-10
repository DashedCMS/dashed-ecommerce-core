<?php

use Dashed\DashedEcommerceCore\Models\Product;
use Dashed\DashedEcommerceCore\Models\ProductGroup;

function makeBackorderProduct(array $attributes = []): Product
{
    $group = ProductGroup::create([
        'name' => ['en' => 'Groep ' . uniqid()],
        'slug' => ['en' => 'groep-' . uniqid()],
        'short_description' => ['en' => ''],
        'description' => ['en' => ''],
        'content' => ['en' => ''],
        'search_terms' => ['en' => ''],
        'site_ids' => ['default'],
    ]);

    return Product::withoutEvents(fn () => Product::create(array_merge([
        'product_group_id' => $group->id,
        'name' => ['en' => 'Product ' . uniqid()],
        'slug' => ['en' => 'product-' . uniqid()],
        'site_ids' => ['default'],
        'price' => 100.00,
        'current_price' => 100.00,
        'vat_rate' => 21,
        'use_stock' => true,
        'out_of_stock_sellable' => true,
        'stock' => 1,
        'expected_delivery_in_days' => 5,
    ], $attributes)));
}

it('returns 0 when the product does not track stock', function () {
    $product = makeBackorderProduct(['use_stock' => false]);

    expect($product->backorderedQuantity(5))->toBe(0);
});

it('returns 0 when there is enough physical stock', function () {
    $product = makeBackorderProduct(['stock' => 10]);

    expect($product->backorderedQuantity(2))->toBe(0);
});

it('returns the shortfall on a partial backorder', function () {
    $product = makeBackorderProduct(['stock' => 1]);

    expect($product->backorderedQuantity(2))->toBe(1);
});

it('returns the full quantity when nothing is in stock', function () {
    $product = makeBackorderProduct(['stock' => 0]);

    expect($product->backorderedQuantity(3))->toBe(3);
});

it('returns 0 when the product is not sellable out of stock', function () {
    $product = makeBackorderProduct(['stock' => 1, 'out_of_stock_sellable' => false]);

    expect($product->backorderedQuantity(2))->toBe(0);
});

it('returns 0 for a bundle product (handled per component elsewhere)', function () {
    $product = makeBackorderProduct(['stock' => 0, 'is_bundle' => true]);

    expect($product->backorderedQuantity(2))->toBe(0);
});
