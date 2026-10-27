<?php

use Dashed\DashedCore\Classes\Sites;
use Dashed\DashedEcommerceCore\Models\Product;
use Dashed\DashedEcommerceCore\Models\ProductGroup;
use Dashed\DashedEcommerceCore\Models\DiscountCode;

function makeGiftcardTestCode(array $attributes = []): DiscountCode
{
    $code = new DiscountCode();
    $code->forceFill(array_merge([
        'site_ids' => [Sites::getActive()],
        'name' => 'Cadeaubon',
        'code' => 'GIFT' . strtoupper(uniqid()),
        'type' => 'amount',
        'discount_amount' => 25.0,
        'is_giftcard' => 1,
    ], $attributes));
    $code->save();

    return $code->fresh();
}

it('recognises a sold giftcard', function () {
    $sold = makeGiftcardTestCode(['giftcard_type' => 'verkocht']);
    $promo = makeGiftcardTestCode();

    expect($sold->isSoldGiftcard())->toBeTrue()
        ->and($promo->isSoldGiftcard())->toBeFalse()
        ->and($promo->giftcard_type)->toBe('promo')
        ->and(DiscountCode::soldGiftcard()->pluck('id')->all())->toBe([$sold->id]);
});

it('can create a giftcard without an authenticated user', function () {
    $code = makeGiftcardTestCode();

    $log = $code->logs()->first();

    expect($log)->not->toBeNull()
        ->and($log->user_id)->toBeNull();
});

it('lets group flags flow to the product', function () {
    $group = ProductGroup::create([
        'name' => ['en' => 'Bon'],
        'slug' => ['en' => 'bon-' . uniqid()],
        'short_description' => ['en' => ''],
        'description' => ['en' => ''],
        'content' => ['en' => ''],
        'search_terms' => ['en' => ''],
        'site_ids' => ['default'],
    ]);
    $product = Product::withoutEvents(fn () => Product::create([
        'product_group_id' => $group->id,
        'name' => ['en' => 'Bon 25'],
        'slug' => ['en' => 'bon-25-' . uniqid()],
        'site_ids' => ['default'],
        'current_price' => 25,
        'price' => 25,
        'vat_rate' => 21,
    ]));

    expect($product->isDigital())->toBeFalse()->and($product->isGiftcardProduct())->toBeFalse();

    $group->is_digital = true;
    $group->is_giftcard_product = true;
    $group->save();

    $product = $product->fresh();
    expect($product->isDigital())->toBeTrue()->and($product->isGiftcardProduct())->toBeTrue();
});
