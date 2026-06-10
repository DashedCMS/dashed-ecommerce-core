<?php

use Dashed\DashedCore\Models\Customsetting;
use Dashed\DashedEcommerceCore\Models\Product;
use Dashed\DashedEcommerceCore\Models\ProductGroup;

function makeNoticeProduct(array $attributes = []): Product
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

    return Product::withoutEvents(fn () => Product::create(array_merge([
        'product_group_id' => $group->id,
        'name' => ['en' => 'Vaas'],
        'slug' => ['en' => 'product-' . uniqid()],
        'site_ids' => ['default'],
        'price' => 100.00,
        'current_price' => 100.00,
        'vat_rate' => 21,
        'use_stock' => true,
        'out_of_stock_sellable' => true,
        'stock' => 1,
    ], $attributes)));
}

it('returns null when nothing is backordered', function () {
    $product = makeNoticeProduct(['stock' => 10, 'expected_delivery_in_days' => 5]);

    expect(cartHelper()->backorderNoticeLine($product, 2))->toBeNull();
});

it('builds a line with a delivery date', function () {
    $date = now()->addDays(10);
    $product = makeNoticeProduct(['stock' => 1, 'expected_in_stock_date' => $date]);

    $line = cartHelper()->backorderNoticeLine($product, 3);

    expect($line)->toContain('Vaas')
        ->and($line)->toContain('1 van 3')
        ->and($line)->toContain('2 wordt nabesteld')
        ->and($line)->toContain('verwacht op ' . $date->format('d-m-Y'));
});

it('builds a line with delivery days', function () {
    $product = makeNoticeProduct(['stock' => 1, 'expected_delivery_in_days' => 5]);

    $line = cartHelper()->backorderNoticeLine($product, 2);

    expect($line)->toContain('1 van 2')
        ->and($line)->toContain('verwacht over 5 dagen');
});

it('builds a generic line when no delivery time is configured', function () {
    Customsetting::set('product_out_of_stock_sellable_date_should_be_valid', '0', \Dashed\DashedCore\Classes\Sites::getActive() ?: 'default');

    $product = makeNoticeProduct(['stock' => 1]);

    $line = cartHelper()->backorderNoticeLine($product, 2);

    expect($line)->toContain('1 van 2')
        ->and($line)->toContain('wordt nabesteld');
});
