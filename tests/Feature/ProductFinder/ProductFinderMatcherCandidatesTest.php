<?php

uses(\Illuminate\Foundation\Testing\RefreshDatabase::class);

use Illuminate\Support\Str;
use Dashed\DashedAi\Facades\Ai;
use Dashed\DashedEcommerceCore\Models\Product;
use Dashed\DashedEcommerceCore\Models\ProductFinder;
use Dashed\DashedEcommerceCore\Models\ProductGroup;
use Dashed\DashedEcommerceCore\Models\ProductCategory;
use Dashed\DashedEcommerceCore\Services\ProductFinder\ProductFinderMatcher;

function candProduct(string $name, array $overrides = []): Product
{
    $group = ProductGroup::create([
        'name' => ['en' => $name . ' G'], 'slug' => ['en' => Str::slug($name) . '-g'],
        'short_description' => ['en' => ''], 'description' => ['en' => ''],
        'content' => ['en' => ''], 'search_terms' => ['en' => ''], 'site_ids' => ['default'],
    ]);

    return Product::withoutEvents(fn () => Product::create(array_merge([
        'name' => ['en' => $name], 'slug' => ['en' => Str::slug($name)],
        'site_ids' => ['default'], 'product_group_id' => $group->id,
        'public' => 1, 'use_stock' => false, 'in_stock' => true, 'stock_status' => 'in_stock',
        'price' => 10, 'current_price' => 10,
    ], $overrides)));
}

it('sluit out-of-stock producten uit wanneer only_in_stock aan staat', function () {
    $inStock = candProduct('InStock', ['in_stock' => true]);
    $outStock = candProduct('OutStock', ['in_stock' => false, 'stock_status' => 'out_of_stock']);

    $finder = ProductFinder::create(['site_id' => 'default', 'name' => 'F', 'is_active' => true, 'only_in_stock' => true, 'result_count' => 10]);

    Ai::shouldReceive('hasProvider')->andReturn(false); // forceer fallback = de kandidaten zelf

    $ids = collect((new ProductFinderMatcher())->match($finder, []))->map(fn ($r) => $r['product']->id);

    expect($ids)->toContain($inStock->id);
    expect($ids)->not->toContain($outStock->id);
});

it('beperkt tot de gekozen categorieën wanneer category_ids gezet is', function () {
    $cat = ProductCategory::create(['name' => ['en' => 'Cat'], 'slug' => ['en' => 'cat'], 'site_ids' => ['default']]);
    $inCat = candProduct('InCat');
    $inCat->productCategories()->attach($cat->id);
    $outCat = candProduct('OutCat');

    $finder = ProductFinder::create(['site_id' => 'default', 'name' => 'F', 'is_active' => true,
        'only_in_stock' => false, 'category_ids' => [$cat->id], 'result_count' => 10]);

    Ai::shouldReceive('hasProvider')->andReturn(false);

    $ids = collect((new ProductFinderMatcher())->match($finder, []))->map(fn ($r) => $r['product']->id);

    expect($ids)->toContain($inCat->id);
    expect($ids)->not->toContain($outCat->id);
});
