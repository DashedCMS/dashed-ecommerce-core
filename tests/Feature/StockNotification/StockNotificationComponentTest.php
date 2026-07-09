<?php

use Livewire\Livewire;
use Dashed\DashedEcommerceCore\Models\Product;
use Dashed\DashedEcommerceCore\Models\ProductGroup;
use Dashed\DashedEcommerceCore\Models\StockNotification;
use Dashed\DashedEcommerceCore\Livewire\Frontend\Products\StockNotification as StockNotificationComponent;

function stockNotificationGroup(): ProductGroup
{
    return ProductGroup::create([
        'name' => ['en' => 'Group'],
        'slug' => ['en' => 'group'],
        'short_description' => ['en' => ''],
        'description' => ['en' => ''],
        'content' => ['en' => ''],
        'search_terms' => ['en' => ''],
        'site_ids' => ['default'],
    ]);
}

function stockNotificationProduct(array $overrides = []): Product
{
    return Product::withoutEvents(function () use ($overrides) {
        return Product::create(array_merge([
            'name' => ['en' => 'Test product'],
            'slug' => ['en' => 'test-product'],
            'site_ids' => ['default'],
            'product_group_id' => stockNotificationGroup()->id,
            'use_stock' => true,
            'stock' => 0,
            'total_stock' => 0,
            'in_stock' => false,
            'stock_status' => 'out_of_stock',
            'out_of_stock_sellable' => false,
            'price' => 10.00,
            'current_price' => 10.00,
        ], $overrides));
    });
}

it('toont het formulier voor een uitverkocht product', function () {
    $product = stockNotificationProduct();

    Livewire::test(StockNotificationComponent::class, ['product' => $product])
        ->assertSee('Uitverkocht');

    // shouldShow is een computed property (niet assertbaar via assertSet);
    // test hem direct op de component-instance.
    $component = Livewire::test(StockNotificationComponent::class, ['product' => $product]);
    expect($component->instance()->shouldShow)->toBeTrue();
});

it('verbergt zich voor een verkoopbaar product', function () {
    $product = stockNotificationProduct([
        'stock' => 25,
        'total_stock' => 25,
        'in_stock' => true,
        'stock_status' => 'in_stock',
    ]);

    $component = Livewire::test(StockNotificationComponent::class, ['product' => $product])
        ->assertDontSee('Uitverkocht');

    expect($component->instance()->shouldShow)->toBeFalse();
});

it('slaat een aanmelding op en toont de successtaat', function () {
    $product = stockNotificationProduct();

    Livewire::test(StockNotificationComponent::class, ['product' => $product])
        ->set('email', 'Test@Example.com')
        ->call('submit')
        ->assertSet('submitted', true)
        ->assertSet('alreadySubscribed', false);

    expect(StockNotification::where('product_id', $product->id)
        ->where('email', 'test@example.com')
        ->count())->toBe(1);
});

it('herkent een dubbele aanmelding zonder duplicaat', function () {
    $product = stockNotificationProduct();

    Livewire::test(StockNotificationComponent::class, ['product' => $product])
        ->set('email', 'dubbel@example.com')
        ->call('submit')
        ->assertSet('alreadySubscribed', false);

    Livewire::test(StockNotificationComponent::class, ['product' => $product])
        ->set('email', 'dubbel@example.com')
        ->call('submit')
        ->assertSet('submitted', true)
        ->assertSet('alreadySubscribed', true);

    expect(StockNotification::where('product_id', $product->id)
        ->where('email', 'dubbel@example.com')
        ->count())->toBe(1);
});

it('weigert een ongeldig e-mailadres', function () {
    $product = stockNotificationProduct();

    Livewire::test(StockNotificationComponent::class, ['product' => $product])
        ->set('email', 'geen-email')
        ->call('submit')
        ->assertHasErrors(['email' => 'email'])
        ->assertSet('submitted', false);

    expect(StockNotification::where('product_id', $product->id)->count())->toBe(0);
});
