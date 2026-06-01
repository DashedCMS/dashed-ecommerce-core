<?php

use Dashed\DashedCore\Models\User;
use Dashed\DashedEcommerceCore\Models\PriceGroup;
use Dashed\DashedEcommerceCore\Models\Product;
use Dashed\DashedEcommerceCore\Models\ProductGroup;
use Illuminate\Support\Facades\DB;

it('creates a price group with defaults', function () {
    $group = PriceGroup::create(['name' => 'B2B Standaard']);

    expect($group->name)->toBe('B2B Standaard')
        ->and((bool) $group->show_prices_ex_vat)->toBeFalse();
});

it('links a user to a price group', function () {
    $group = PriceGroup::create(['name' => 'Groothandel']);
    $user = User::factory()->create(['price_group_id' => $group->id]);

    expect($user->priceGroup->id)->toBe($group->id)
        ->and($group->fresh()->users)->toHaveCount(1);
});

function makeProduct(float $price): Product
{
    $group = ProductGroup::create([
        'name' => ['en' => 'Groep ' . $price],
        'slug' => ['en' => 'groep-' . str_replace('.', '-', $price) . '-' . uniqid()],
        'short_description' => ['en' => ''],
        'description' => ['en' => ''],
        'content' => ['en' => ''],
        'search_terms' => ['en' => ''],
        'site_ids' => ['default'],
    ]);

    return Product::withoutEvents(function () use ($group, $price) {
        return Product::create([
            'product_group_id' => $group->id,
            'name' => ['en' => 'Product ' . $price],
            'slug' => ['en' => 'product-' . str_replace('.', '-', $price) . '-' . uniqid()],
            'site_ids' => ['default'],
            'current_price' => $price,
            'price' => $price,
            'vat_rate' => 21,
        ]);
    });
}

it('returns the group price when the user has a price group and no personal override', function () {
    $group = PriceGroup::create(['name' => 'B2B']);
    $user = User::factory()->create(['price_group_id' => $group->id]);
    $product = makeProduct(100.00);

    DB::table('dashed__product_price_group')->insert([
        'price_group_id' => $group->id,
        'product_id' => $product->id,
        'price' => 80.00,
    ]);

    expect((float) $product->priceForUser($user))->toBe(80.00);
});

it('lets a personal override win over the group price', function () {
    $group = PriceGroup::create(['name' => 'B2B']);
    $user = User::factory()->create(['price_group_id' => $group->id, 'has_custom_pricing' => true]);
    $product = makeProduct(100.00);

    DB::table('dashed__product_price_group')->insert([
        'price_group_id' => $group->id, 'product_id' => $product->id, 'price' => 80.00,
    ]);
    DB::table('dashed__product_user')->insert([
        'user_id' => $user->id, 'product_id' => $product->id, 'price' => 70.00,
    ]);

    expect((float) $product->priceForUser($user))->toBe(70.00);
});

it('computes the group price from a discount_percentage when price is null', function () {
    $group = PriceGroup::create(['name' => 'B2B']);
    $user = User::factory()->create(['price_group_id' => $group->id]);
    $product = makeProduct(100.00);

    DB::table('dashed__product_price_group')->insert([
        'price_group_id' => $group->id, 'product_id' => $product->id,
        'price' => null, 'discount_percentage' => 25,
    ]);

    expect((float) $product->priceForUser($user))->toBe(75.00);
});

it('falls back to current_price when no group row exists', function () {
    $group = PriceGroup::create(['name' => 'B2B']);
    $user = User::factory()->create(['price_group_id' => $group->id]);
    $product = makeProduct(100.00);

    expect((float) $product->priceForUser($user))->toBe(100.00);
});
