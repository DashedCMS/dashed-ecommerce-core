<?php

use Dashed\DashedPopups\Models\Popup;
use Dashed\DashedEcommerceCore\Models\Product;
use Dashed\DashedEcommerceCore\Models\DiscountCode;
use Dashed\DashedEcommerceCore\Models\ProductGroup;
use Dashed\DashedEcommerceCore\Models\ProductCategory;

function makePopup(array $attributes = []): Popup
{
    return Popup::create(array_merge([
        'name' => 'Test popup ' . uniqid(),
        'type' => 'discount',
        'active' => true,
        'discount_percentage' => 10,
        'discount_valid_days' => 14,
        'discount_type' => 'percentage',
        'start_date' => now()->subDay(),
        'end_date' => now()->addDays(30),
    ], $attributes));
}

function makePopupProduct(float $price = 100.0): Product
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

    return Product::withoutEvents(fn () => Product::create([
        'product_group_id' => $group->id,
        'name' => ['en' => 'Product ' . uniqid()],
        'slug' => ['en' => 'product-' . uniqid()],
        'site_ids' => ['default'],
        'current_price' => $price,
        'price' => $price,
        'vat_rate' => 21,
    ]));
}

function makePopupCategory(): ProductCategory
{
    return ProductCategory::create([
        'name' => ['en' => 'Categorie ' . uniqid()],
        'slug' => ['en' => 'categorie-' . uniqid()],
        'site_ids' => ['default'],
    ]);
}

it('relates a popup to discount products and categories', function () {
    $popup = makePopup();

    $product = makePopupProduct();
    $category = makePopupCategory();

    $popup->discountProducts()->sync([$product->id]);
    $popup->discountCategories()->sync([$category->id]);

    expect($popup->discountProducts()->pluck('dashed__products.id')->all())->toBe([$product->id])
        ->and($popup->discountCategories()->pluck('dashed__product_categories.id')->all())->toBe([$category->id]);
});

it('maps a percentage popup to discount code attributes', function () {
    $popup = makePopup([
        'discount_type' => 'percentage', 'discount_percentage' => 10,
        'minimal_requirements' => 'amount', 'minimum_amount' => 50, 'valid_for' => null,
    ]);
    $attrs = $popup->discountCodeAttributes(null);
    expect($attrs['type'])->toBe('percentage')
        ->and((float) $attrs['discount_percentage'])->toBe(10.0)
        ->and($attrs)->not->toHaveKey('discount_amount')
        ->and($attrs['minimal_requirements'])->toBe('amount')
        ->and((float) $attrs['minimum_amount'])->toBe(50.0)
        ->and($attrs['minimum_products_count'])->toBeNull()
        ->and($attrs['use_stock'])->toBeTrue()
        ->and($attrs['stock'])->toBe(1)
        ->and($attrs['limit_use_per_customer'])->toBeTrue();
});

it('uses the override percentage from a variant when given', function () {
    $popup = makePopup(['discount_type' => 'percentage', 'discount_percentage' => 10]);
    expect((float) $popup->discountCodeAttributes(25.0)['discount_percentage'])->toBe(25.0);
});

it('maps an amount popup to a fixed-amount discount code', function () {
    $popup = makePopup([
        'discount_type' => 'amount', 'discount_amount' => 15,
        'minimal_requirements' => null, 'valid_for' => 'products',
    ]);
    $attrs = $popup->discountCodeAttributes(null);
    expect($attrs['type'])->toBe('amount')
        ->and((float) $attrs['discount_amount'])->toBe(15.0)
        ->and($attrs)->not->toHaveKey('discount_percentage')
        ->and($attrs['minimal_requirements'])->toBeNull()
        ->and($attrs['minimum_amount'])->toBeNull()
        ->and($attrs['minimum_products_count'])->toBeNull()
        ->and($attrs['valid_for'])->toBe('products');
});

it('creates a discount code with restrictions and synced products', function () {
    $product = makePopupProduct();

    $popup = makePopup([
        'discount_type' => 'amount', 'discount_amount' => 15,
        'minimal_requirements' => 'amount', 'minimum_amount' => 50, 'valid_for' => 'products',
    ]);
    $popup->discountProducts()->sync([$product->id]);

    $code = $popup->createDiscountCodeFor('WELKOM-TESTCODE', 10.0, 14, ['main']);

    expect($code)->toBeInstanceOf(DiscountCode::class)
        ->and($code->type)->toBe('amount')
        ->and((float) $code->discount_amount)->toBe(15.0)
        ->and($code->minimal_requirements)->toBe('amount')
        ->and((float) $code->minimum_amount)->toBe(50.0)
        ->and($code->valid_for)->toBe('products')
        ->and((int) $code->stock)->toBe(1)
        ->and($code->products()->pluck('dashed__products.id')->all())->toBe([$product->id]);
});

it('does not sync products when valid_for is null', function () {
    $popup = makePopup(['discount_type' => 'percentage', 'discount_percentage' => 10, 'valid_for' => null]);
    $code = $popup->createDiscountCodeFor('WELKOM-NOREST', 10.0, 14, ['main']);
    expect($code->products()->count())->toBe(0)
        ->and($code->productCategories()->count())->toBe(0)
        ->and($code->type)->toBe('percentage')
        ->and((float) $code->discount_percentage)->toBe(10.0);
});
