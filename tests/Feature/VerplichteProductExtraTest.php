<?php

declare(strict_types=1);

use Dashed\DashedEcommerceCore\Models\Product;
use Dashed\DashedEcommerceCore\Models\ProductExtra;
use Dashed\DashedEcommerceCore\Models\ProductGroup;
use Dashed\DashedEcommerceCore\Livewire\Frontend\Cart\CartSuggestions;

uses(\Illuminate\Foundation\Testing\RefreshDatabase::class);

function verplichtExtraProduct(bool $required = true, bool $public = false): Product
{
    $group = ProductGroup::create([
        'name' => ['en' => 'Wenskaart ' . uniqid()],
        'slug' => ['en' => 'wenskaart-' . uniqid()],
        'site_ids' => ['default'],
    ]);

    $product = Product::withoutEvents(fn () => Product::create([
        'name' => ['en' => 'Wenskaart'],
        'slug' => ['en' => 'wenskaart-' . uniqid()],
        'site_ids' => ['default'],
        'product_group_id' => $group->id,
        'public' => $public,
        'use_stock' => false,
        'stock_status' => 'in_stock',
        'price' => 2.95,
        'current_price' => 2.95,
    ]));

    $extra = ProductExtra::create(['product_id' => $product->id, 'name' => ['en' => 'Tekst'], 'type' => 'textarea', 'required' => $required]);
    $extra->forceFill(['min_length' => 1, 'max_length' => 20])->save();

    return $product->fresh();
}

it('herkent een product met een verplichte extra', function () {
    expect(verplichtExtraProduct()->hasRequiredExtras())->toBeTrue()
        ->and(verplichtExtraProduct(required: false)->hasRequiredExtras())->toBeFalse();
});

it('keurt een lege of alleen-spaties tekst af bij een verplicht tekstveld', function () {
    $extra = verplichtExtraProduct()->allProductExtras()->first();

    expect($extra->textValueError(null))->toBe('required')
        ->and($extra->textValueError('   '))->toBe('required')
        ->and($extra->textValueError('Gefeliciteerd!'))->toBeNull();
});

it('keurt een te lange tekst af, ook zonder browsercontrole', function () {
    $extra = verplichtExtraProduct()->allProductExtras()->first();

    expect($extra->textValueError(str_repeat('a', 20)))->toBeNull()
        ->and($extra->textValueError(str_repeat('a', 21)))->toBe('max')
        ->and($extra->textValueError('  ' . str_repeat('a', 20) . "\n"))->toBeNull();
});

it('laat een leeg optioneel tekstveld toe', function () {
    $extra = verplichtExtraProduct(required: false)->allProductExtras()->first();

    expect($extra->textValueError(''))->toBeNull();
});

it('opent in de suggesties de modal voor een product met een verplichte extra in plaats van direct toe te voegen', function () {
    $product = verplichtExtraProduct();

    $component = new CartSuggestions();
    $component->openQuickAdd($product->id);

    expect($component->quickAddProductId)->toBe($product->id)
        ->and(cartHelper()->getCartItems())->toHaveCount(0);
});
