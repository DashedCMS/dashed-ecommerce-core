<?php

use Dashed\DashedCore\Models\User;
use Illuminate\Support\Facades\DB;
use Dashed\DashedEcommerceCore\Models\Product;
use Dashed\DashedEcommerceCore\Models\PriceGroup;
use Dashed\DashedEcommerceCore\Models\ProductGroup;
use Dashed\DashedEcommerceCore\Models\ProductExtra;
use Dashed\DashedEcommerceCore\Models\ProductExtraOption;
use Dashed\DashedEcommerceCore\Models\ProductCategory;
use Dashed\DashedEcommerceCore\Jobs\ProcessPricesPerPriceGroup;

it('uses the group extra option price in the cart total', function () {
    $group = PriceGroup::create(['name' => 'B2B']);
    $user = User::factory()->create(['price_group_id' => $group->id]);
    $this->actingAs($user);

    $productGroup = ProductGroup::create([
        'name' => ['en' => 'Groep'],
        'slug' => ['en' => 'groep-' . uniqid()],
        'short_description' => ['en' => ''],
        'description' => ['en' => ''],
        'content' => ['en' => ''],
        'search_terms' => ['en' => ''],
        'site_ids' => ['default'],
    ]);

    $product = Product::withoutEvents(fn () => Product::create([
        'product_group_id' => $productGroup->id,
        'name' => ['en' => 'P'],
        'slug' => ['en' => 'product-' . uniqid()],
        'site_ids' => ['default'],
        'current_price' => 100.00,
        'price' => 100.00,
        'vat_rate' => 21,
    ]));

    $extra = ProductExtra::create(['name' => ['en' => 'Gravering'], 'type' => 'single', 'price' => 0]);
    $option = ProductExtraOption::create([
        'product_extra_id' => $extra->id,
        'value' => ['en' => 'Ja'],
        'price' => 5.00,
    ]);

    DB::table('dashed__product_extra_option_price_group')->insert([
        'price_group_id' => $group->id, 'product_extra_option_id' => $option->id, 'price' => 3.00,
    ]);

    $cartItem = (object) [
        'product_id' => $product->id,
        'quantity' => 1,
        'options' => ['options' => [(string) $option->id => ['quantity' => 1]]],
    ];

    // 100 product + 3 group extra price (instead of 5 default)
    expect(Product::getShoppingCartItemPrice($cartItem))->toBe(103.00);
});

it('cascades a category discount to group product prices', function () {
    \Illuminate\Support\Facades\Queue::fake();

    $group = PriceGroup::create(['name' => 'B2B']);
    $category = ProductCategory::create([
        'name' => ['en' => 'Vazen'],
        'slug' => ['en' => 'vazen-' . uniqid()],
        'site_ids' => ['default'],
    ]);

    $productGroup = ProductGroup::create([
        'name' => ['en' => 'G'],
        'slug' => ['en' => 'groep-' . uniqid()],
        'short_description' => ['en' => ''],
        'description' => ['en' => ''],
        'content' => ['en' => ''],
        'search_terms' => ['en' => ''],
        'site_ids' => ['default'],
    ]);
    $product = Product::withoutEvents(fn () => Product::create([
        'product_group_id' => $productGroup->id,
        'name' => ['en' => 'P'],
        'slug' => ['en' => 'product-' . uniqid()],
        'site_ids' => ['default'],
        'current_price' => 100.00,
        'price' => 100.00,
        'vat_rate' => 21,
    ]));
    $product->productCategories()->attach($category->id);

    (new ProcessPricesPerPriceGroup($group->id, [
        'product_category_ids' => [$category->id],
        $category->id . '_category_discount_price' => null,
        $category->id . '_category_discount_percentage' => 20,
    ]))->handle();

    $row = DB::table('dashed__product_price_group')
        ->where('price_group_id', $group->id)
        ->where('product_id', $product->id)
        ->first();

    expect((int) $row->discount_percentage)->toBe(20)
        ->and((bool) $row->activated_by_category)->toBeTrue();
});
