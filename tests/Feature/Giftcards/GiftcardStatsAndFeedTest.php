<?php

use Illuminate\Support\Facades\DB;
use Dashed\DashedCore\Classes\Sites;
use Dashed\DashedEcommerceCore\Models\Product;
use Dashed\DashedEcommerceCore\Models\DiscountCode;
use Dashed\DashedEcommerceCore\Models\ProductGroup;
use Dashed\DashedEcommerceCore\Jobs\UpdateProductInformationJob;
use Dashed\DashedEcommerceCore\Filament\Resources\GiftcardResource\Widgets\GiftcardStats;

function statsGiftcardCode(array $attributes): DiscountCode
{
    $code = new DiscountCode();
    $code->forceFill(array_merge([
        'site_ids' => [Sites::getActive()],
        'name' => 'Cadeaubon',
        'code' => 'GIFT' . strtoupper(uniqid()),
        'type' => 'amount',
        'discount_amount' => 0,
        'reserved_amount' => 0,
        'is_giftcard' => 1,
    ], $attributes));
    $code->save();

    return $code;
}

function feedTestGroup(bool $giftcard): array
{
    $group = ProductGroup::create([
        'name' => ['en' => 'Groep'],
        'slug' => ['en' => 'groep-' . uniqid()],
        'short_description' => ['en' => ''],
        'description' => ['en' => ''],
        'content' => ['en' => ''],
        'search_terms' => ['en' => ''],
        'site_ids' => ['default'],
    ]);
    $group->is_giftcard_product = $giftcard;
    $group->saveQuietly();

    $product = Product::withoutEvents(fn () => Product::create([
        'product_group_id' => $group->id,
        'name' => ['en' => 'Product'],
        'slug' => ['en' => 'product-' . uniqid()],
        'site_ids' => ['default'],
        'current_price' => 25,
        'price' => 25,
        'vat_rate' => $giftcard ? 0 : 21,
        'public' => 1,
    ]));

    return [$group, $product];
}

it('sums the open balance of sold giftcards only', function () {
    statsGiftcardCode(['giftcard_type' => 'verkocht', 'discount_amount' => 25, 'reserved_amount' => 10]);
    statsGiftcardCode(['giftcard_type' => 'verkocht', 'discount_amount' => 40]);
    statsGiftcardCode(['giftcard_type' => 'promo', 'discount_amount' => 100]);

    expect(GiftcardStats::openSoldBalance())->toBe(75.0);
});

it('keeps giftcard products out of the product feed', function () {
    [$group, $product] = feedTestGroup(true);

    // Een oude feedrij van vóór de bon-eigenschap moet verdwijnen.
    DB::table('dashed__product_feed_data')->insert([
        'product_id' => $product->id,
        'locale' => 'en',
        'payload' => '{}',
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    (new UpdateProductInformationJob($group, false))->handle();

    expect(DB::table('dashed__product_feed_data')->where('product_id', $product->id)->count())->toBe(0);
});

it('still writes feed rows for normal products', function () {
    [$group, $product] = feedTestGroup(false);

    (new UpdateProductInformationJob($group, false))->handle();

    expect(DB::table('dashed__product_feed_data')->where('product_id', $product->id)->count())->toBeGreaterThan(0);
});
