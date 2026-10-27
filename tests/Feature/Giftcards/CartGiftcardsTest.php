<?php

declare(strict_types=1);

use Illuminate\Support\Str;
use Dashed\DashedCore\Classes\Sites;
use Dashed\DashedEcommerceCore\Models\Cart;
use Dashed\DashedCore\Models\Customsetting;
use Dashed\DashedEcommerceCore\Models\Product;
use Dashed\DashedEcommerceCore\Models\CartItem;
use Dashed\DashedEcommerceCore\Classes\CartHelper;
use Dashed\DashedEcommerceCore\Models\DiscountCode;
use Dashed\DashedEcommerceCore\Models\ProductGroup;
use Dashed\DashedEcommerceCore\Services\Giftcards\CartGiftcards;

/**
 * Een verkochte cadeaubon (giftcard_type = verkocht) is betaalmiddel: hij
 * verlaagt het bedrag dat de klant nog moet betalen, niet de btw of het
 * totaal. Een promo-bon blijft zich als korting gedragen.
 */
beforeEach(function () {
    Customsetting::set('taxes_prices_include_taxes', 1);
    CartHelper::$calculateInclusiveTax = true;
    CartHelper::$cart = null;
    CartHelper::$cartItemsInitialized = false;
    CartHelper::$cartItems = [];
    CartHelper::$cartProductsById = [];
});

function cadeauProduct(float $prijs, bool $bonProduct = false): Product
{
    $group = ProductGroup::create([
        'name' => ['nl' => 'Groep'],
        'slug' => ['nl' => 'groep-' . uniqid()],
        'short_description' => ['nl' => ''],
        'description' => ['nl' => ''],
        'content' => ['nl' => ''],
        'search_terms' => ['nl' => ''],
        'site_ids' => [Sites::getActive()],
    ]);

    $product = Product::withoutEvents(fn () => Product::create([
        'name' => ['nl' => 'Product'],
        'slug' => ['nl' => 'product-' . uniqid()],
        'site_ids' => [Sites::getActive()],
        'product_group_id' => $group->id,
        'use_stock' => 0,
        'stock' => 0,
        'total_stock' => 0,
        'in_stock' => 1,
        'stock_status' => 'in_stock',
        'price' => $prijs,
        'current_price' => $prijs,
        'vat_rate' => 21,
        'public' => 1,
    ]));

    if ($bonProduct) {
        $product->forceFill(['is_giftcard_product' => true]);
        Product::withoutEvents(fn () => $product->save());
    }

    return $product;
}

function cadeauBon(float $saldo, string $type = 'verkocht'): DiscountCode
{
    $code = new DiscountCode();
    $code->forceFill([
        'site_ids' => [Sites::getActive()],
        'name' => 'Cadeaubon',
        'code' => 'BON' . strtoupper(uniqid()),
        'type' => 'amount',
        'discount_amount' => $saldo,
        'is_giftcard' => 1,
        'giftcard_type' => $type,
        'valid_for' => 'all',
    ]);
    DiscountCode::withoutEvents(fn () => $code->save());

    return $code->fresh();
}

function procentCode(float $procent): DiscountCode
{
    return DiscountCode::withoutEvents(fn () => DiscountCode::create([
        'site_ids' => [Sites::getActive()],
        'name' => 'Korting',
        'code' => 'KORT' . strtoupper(uniqid()),
        'type' => 'percentage',
        'discount_percentage' => $procent,
        'discount_amount' => 0,
        'is_giftcard' => 0,
        'valid_for' => 'all',
    ]));
}

/** @param array<int, Product> $producten */
function cadeauWagen(array $producten): Cart
{
    $token = (string) Str::uuid();
    request()->cookies->set(config('dashed-ecommerce.cart_cookie', 'cart_token'), $token);

    $cart = Cart::create(['token' => $token, 'type' => 'default']);

    foreach ($producten as $product) {
        CartItem::create([
            'cart_id' => $cart->id,
            'product_id' => $product->id,
            'name' => 'Product',
            'unit_price' => $product->price,
            'quantity' => 1,
            'options' => [],
            'options_hash' => '',
        ]);
    }

    CartHelper::$cart = null;
    CartHelper::$cartItemsInitialized = false;
    CartHelper::$cartItems = [];
    CartHelper::$cartProductsById = [];
    cartHelper()->updateData();

    return $cart;
}

it('test_sold_giftcard_does_not_lower_vat', function () {
    cadeauWagen([cadeauProduct(52.95)]);
    $taxZonderBon = cartHelper()->getTax();

    $bon = cadeauBon(50);
    $resultaat = cartHelper()->applyDiscountCode($bon->code);

    expect($resultaat['status'])->toBe('success')
        ->and(cartHelper()->getTax())->toBe($taxZonderBon)
        ->and(cartHelper()->getTotal())->toBe(52.95)
        ->and(cartHelper()->getDiscount())->toBe(0.0)
        ->and(cartHelper()->getDiscountCode())->toBeNull()
        ->and(cartHelper()->getGiftcardsTotal())->toBe(50.0)
        ->and(cartHelper()->getAmountToPay())->toBe(2.95)
        ->and(cartHelper()->getCart()->fresh()->meta['applied_gift_cards'])->toBe([
            ['code' => $bon->code, 'discount_code_id' => $bon->id],
        ]);
});

it('test_sold_giftcard_stacks_with_discount_code', function () {
    cadeauWagen([cadeauProduct(100)]);

    $korting = procentCode(10);
    $bon = cadeauBon(50);

    expect(cartHelper()->applyDiscountCode($korting->code)['status'])->toBe('success')
        ->and(cartHelper()->applyDiscountCode($bon->code)['status'])->toBe('success');

    expect(cartHelper()->getDiscountCode()?->id)->toBe($korting->id)
        ->and(cartHelper()->getTotal())->toBe(90.0)
        ->and(cartHelper()->getGiftcardRedemptions())->toBe([
            ['code' => $bon->code, 'discount_code_id' => $bon->id, 'redeemed' => 50.0],
        ])
        ->and(cartHelper()->getAmountToPay())->toBe(40.0);
});

it('test_giftcard_larger_than_total_leaves_zero_to_pay', function () {
    cadeauWagen([cadeauProduct(30)]);
    $bon = cadeauBon(100);

    cartHelper()->applyDiscountCode($bon->code);

    expect(cartHelper()->getTotal())->toBe(30.0)
        ->and(cartHelper()->getAmountToPay())->toBe(0.0)
        ->and(cartHelper()->getGiftcardRedemptions()[0]['redeemed'])->toBe(30.0);
});

it('test_promo_giftcard_still_acts_as_discount', function () {
    cadeauWagen([cadeauProduct(52.95)]);
    $taxZonderBon = cartHelper()->getTax();

    $bon = cadeauBon(50, 'promo');
    cartHelper()->applyDiscountCode($bon->code);

    expect(cartHelper()->getDiscountCode()?->id)->toBe($bon->id)
        ->and(cartHelper()->getTax())->toBeLessThan($taxZonderBon)
        ->and(cartHelper()->getTotal())->toBe(2.95)
        ->and(cartHelper()->getGiftcardRedemptions())->toBe([])
        ->and(cartHelper()->getAmountToPay())->toBe(2.95);
});

it('test_cannot_pay_giftcard_product_with_giftcard', function () {
    $cart = cadeauWagen([cadeauProduct(25, bonProduct: true)]);
    $bon = cadeauBon(100);

    $resultaat = CartGiftcards::apply($cart, $bon->code);
    expect($resultaat['success'])->toBeFalse()
        ->and(CartGiftcards::applied($cart->fresh()))->toBe([]);

    cadeauWagen([cadeauProduct(25, bonProduct: true), cadeauProduct(30)]);
    cartHelper()->applyDiscountCode($bon->code);

    expect(cartHelper()->getTotal())->toBe(55.0)
        ->and(cartHelper()->getGiftcardRedemptions()[0]['redeemed'])->toBe(30.0)
        ->and(cartHelper()->getAmountToPay())->toBe(25.0);
});

it('refuses unknown, promo, empty and duplicate cards and can remove one', function () {
    $cart = cadeauWagen([cadeauProduct(30)]);
    $bon = cadeauBon(20);

    expect(CartGiftcards::apply($cart, 'BESTAATNIET')['success'])->toBeFalse()
        ->and(CartGiftcards::apply($cart, cadeauBon(20, 'promo')->code)['success'])->toBeFalse()
        ->and(CartGiftcards::apply($cart, cadeauBon(0)->code)['success'])->toBeFalse()
        ->and(CartGiftcards::apply($cart, $bon->code)['success'])->toBeTrue()
        ->and(CartGiftcards::apply($cart->fresh(), strtolower($bon->code))['success'])->toBeFalse();

    CartGiftcards::remove($cart->fresh(), $bon->code);
    expect(CartGiftcards::applied($cart->fresh()))->toBe([]);
});
