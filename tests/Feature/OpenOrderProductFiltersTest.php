<?php

use Illuminate\Support\Str;
use Dashed\DashedEcommerceCore\Models\Order;
use Dashed\DashedEcommerceCore\Models\Product;
use Dashed\DashedEcommerceCore\Models\ProductGroup;
use Dashed\DashedEcommerceCore\Models\ProductCategory;
use Dashed\DashedEcommerceCore\Filament\Resources\OpenOrderProducts\OpenOrderProductResource;

function makeFilterProduct(string $tag): Product
{
    $group = ProductGroup::create([
        'name' => ['en' => 'Group ' . $tag], 'slug' => ['en' => 'group-' . $tag . '-' . uniqid()],
        'short_description' => ['en' => ''], 'description' => ['en' => ''],
        'content' => ['en' => ''], 'search_terms' => ['en' => ''], 'site_ids' => ['default'],
    ]);

    return Product::withoutEvents(fn () => Product::create([
        'product_group_id' => $group->id, 'name' => ['en' => 'Product ' . $tag],
        'slug' => ['en' => 'product-' . $tag . '-' . uniqid()], 'site_ids' => ['default'],
        'price' => 10, 'current_price' => 10, 'vat_rate' => 21,
    ]));
}

function makeOpenOrderProductFor(Product $product): void
{
    $order = Order::create([
        'first_name' => 'T', 'last_name' => 'K', 'email' => 'k@example.com',
        'hash' => Str::random(32), 'total' => 10, 'site_id' => 'default', 'ip' => '127.0.0.1',
        'fulfillment_status' => 'unhandled',
    ]);

    $order->orderProducts()->create([
        'product_id' => $product->id,
        'name' => 'Regel',
        'quantity' => 1,
        'price' => 10,
    ]);
}

it('filters open order products by product, group and category', function () {
    $productA = makeFilterProduct('A');
    $productB = makeFilterProduct('B');

    $categoryA = ProductCategory::create([
        'name' => ['en' => 'Cat A'], 'slug' => ['en' => 'cat-a-' . uniqid()], 'site_ids' => ['default'],
    ]);
    $productA->productCategories()->attach($categoryA->id);

    makeOpenOrderProductFor($productA);
    makeOpenOrderProductFor($productB);

    expect(OpenOrderProductResource::getEloquentQuery()->count())->toBe(2);

    $byProduct = OpenOrderProductResource::getEloquentQuery()
        ->whereIn('dashed__order_products.product_id', [$productA->id])->get();
    expect($byProduct)->toHaveCount(1)
        ->and($byProduct->first()->product_id)->toBe($productA->id);

    $byGroup = OpenOrderProductResource::getEloquentQuery()
        ->whereHas('product', fn ($q) => $q->whereIn('product_group_id', [$productA->productGroup->id]))->get();
    expect($byGroup)->toHaveCount(1)
        ->and($byGroup->first()->product_id)->toBe($productA->id);

    $byCategory = OpenOrderProductResource::getEloquentQuery()
        ->whereHas('product.productCategories', fn ($q) => $q->whereIn('dashed__product_categories.id', [$categoryA->id]))->get();
    expect($byCategory)->toHaveCount(1)
        ->and($byCategory->first()->product_id)->toBe($productA->id);
});
