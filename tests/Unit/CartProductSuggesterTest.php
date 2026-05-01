<?php

declare(strict_types=1);

use Dashed\DashedEcommerceCore\Models\Product;
use Dashed\DashedEcommerceCore\Models\ProductGroup;
use Dashed\DashedEcommerceCore\Services\CartSuggestions\CartProductSuggester;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

function makeSuggesterProductGroup(): ProductGroup
{
    return ProductGroup::create([
        'name' => ['en' => 'Test Group'],
        'slug' => ['en' => 'test-group-'.uniqid()],
        'short_description' => ['en' => ''],
        'description' => ['en' => ''],
        'content' => ['en' => ''],
        'search_terms' => ['en' => ''],
        'site_ids' => [\Dashed\DashedCore\Classes\Sites::getActive()],
    ]);
}

function makeSuggesterProduct(string $name, float $price, ProductGroup $group, array $overrides = []): Product
{
    return Product::withoutEvents(function () use ($name, $price, $group, $overrides) {
        return Product::create(array_merge([
            'name' => ['en' => $name],
            'slug' => ['en' => \Illuminate\Support\Str::slug($name).'-'.uniqid()],
            'site_ids' => [\Dashed\DashedCore\Classes\Sites::getActive()],
            'product_group_id' => $group->id,
            'use_stock' => true,
            'stock' => 50,
            'total_stock' => 50,
            'in_stock' => true,
            'stock_status' => 'in_stock',
            'price' => $price,
            'current_price' => $price,
            'public' => 1,
        ], $overrides));
    });
}

function setFreeShippingThreshold(float $minimum): void
{
    Cache::forget('free-shipping-method');

    DB::table('dashed__shipping_zones')->insertOrIgnore([
        'id' => 1,
        'site_id' => 'default',
        'name' => json_encode(['nl' => 'Test zone']),
        'zones' => json_encode(['NL']),
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    DB::table('dashed__shipping_methods')->insert([
        'shipping_zone_id' => 1,
        'name' => json_encode(['nl' => 'Free']),
        'sort' => 'free_delivery',
        'minimum_order_value' => $minimum,
        'maximum_order_value' => 9999,
        'costs' => 0,
        'order' => 1,
        'created_at' => now(),
        'updated_at' => now(),
    ]);
}

beforeEach(function () {
    Cache::forget('free-shipping-method');
    $this->suggester = new CartProductSuggester();
});

it('returns empty collection when cart is empty', function () {
    $result = $this->suggester->suggest(cartProductIds: [], cartTotal: 0.0, limit: 6);

    expect($result)->toHaveCount(0);
});

it('aggregates explicit cross-sell from all cart items', function () {
    $group = makeSuggesterProductGroup();
    $a = makeSuggesterProduct('A', 10, $group);
    $b = makeSuggesterProduct('B', 12, $group);
    $crossA = makeSuggesterProduct('CrossA', 20, $group);
    $crossB = makeSuggesterProduct('CrossB', 25, $group);

    $a->crossSellProducts()->attach($crossA->id);
    $b->crossSellProducts()->attach($crossB->id);

    $result = $this->suggester->suggest(
        cartProductIds: [$a->id, $b->id],
        cartTotal: 22.0,
        limit: 6,
        boostSlots: 0,
    );

    $ids = $result->pluck('id')->all();
    expect($ids)->toContain($crossA->id);
    expect($ids)->toContain($crossB->id);
});

it('excludes products already in cart', function () {
    $group = makeSuggesterProductGroup();
    $a = makeSuggesterProduct('A', 10, $group);
    $b = makeSuggesterProduct('B', 12, $group);
    $a->crossSellProducts()->attach($b->id);

    $result = $this->suggester->suggest(
        cartProductIds: [$a->id, $b->id],
        cartTotal: 22.0,
        limit: 6,
        boostSlots: 0,
    );

    expect($result->pluck('id')->all())->not->toContain($b->id);
});

it('excludes out-of-stock products when require_in_stock', function () {
    $group = makeSuggesterProductGroup();
    $a = makeSuggesterProduct('A', 10, $group);
    $oos = makeSuggesterProduct('OOS', 20, $group, ['stock' => 0, 'in_stock' => 0, 'stock_status' => 'out_of_stock']);
    $a->crossSellProducts()->attach($oos->id);

    $result = $this->suggester->suggest(
        cartProductIds: [$a->id],
        cartTotal: 10.0,
        limit: 6,
        boostSlots: 0,
        requireInStock: true,
    );

    expect($result->pluck('id')->all())->not->toContain($oos->id);
});

it('honors limit setting', function () {
    $group = makeSuggesterProductGroup();
    $a = makeSuggesterProduct('A', 10, $group);
    for ($i = 0; $i < 10; $i++) {
        $cs = makeSuggesterProduct('CS'.$i, 20 + $i, $group);
        $a->crossSellProducts()->attach($cs->id);
    }

    $result = $this->suggester->suggest(
        cartProductIds: [$a->id],
        cartTotal: 10.0,
        limit: 3,
        boostSlots: 0,
    );

    expect($result)->toHaveCount(3);
});

it('boosts gap-closers to first slots when gap > 0', function () {
    setFreeShippingThreshold(100.00);

    $group = makeSuggesterProductGroup();
    $a = makeSuggesterProduct('A', 80, $group);

    $tooSmall = makeSuggesterProduct('Small', 5, $group);
    $sweetSpot = makeSuggesterProduct('Sweet', 22, $group);
    $tooBig = makeSuggesterProduct('Big', 100, $group);

    $a->crossSellProducts()->attach([$tooSmall->id, $sweetSpot->id, $tooBig->id]);

    $result = $this->suggester->suggest(
        cartProductIds: [$a->id],
        cartTotal: 80.0,
        limit: 6,
        boostSlots: 3,
    );

    expect($result->first()->id)->toBe($sweetSpot->id);
    expect($result->first()->is_gap_closer)->toBeTrue();
});

it('does not boost when gap is 0 (over threshold)', function () {
    setFreeShippingThreshold(50.00);

    $group = makeSuggesterProductGroup();
    $a = makeSuggesterProduct('A', 100, $group);

    $sweetSpot = makeSuggesterProduct('Sweet', 22, $group);
    $a->crossSellProducts()->attach($sweetSpot->id);

    $result = $this->suggester->suggest(
        cartProductIds: [$a->id],
        cartTotal: 100.0,
        limit: 6,
        boostSlots: 3,
    );

    foreach ($result as $p) {
        expect($p->is_gap_closer)->toBeFalse();
    }
});

it('falls back to random when no cross-sell or category', function () {
    $group = makeSuggesterProductGroup();
    $a = makeSuggesterProduct('A', 10, $group);
    $randomCandidate = makeSuggesterProduct('Random', 50, $group);

    $result = $this->suggester->suggest(
        cartProductIds: [$a->id],
        cartTotal: 10.0,
        limit: 6,
        boostSlots: 0,
        fallbackRandom: true,
    );

    expect($result->pluck('id')->all())->toContain($randomCandidate->id);
});
