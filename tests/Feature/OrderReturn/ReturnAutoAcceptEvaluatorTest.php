<?php

declare(strict_types=1);

use Dashed\DashedCore\Models\Customsetting;
use Dashed\DashedEcommerceCore\Models\Order;
use Dashed\DashedEcommerceCore\Models\OrderProduct;
use Dashed\DashedEcommerceCore\Models\OrderReturn;
use Dashed\DashedEcommerceCore\Models\OrderReturnLine;
use Dashed\DashedEcommerceCore\Models\Product;
use Dashed\DashedEcommerceCore\Models\ProductCategory;
use Dashed\DashedEcommerceCore\Services\OrderReturn\ReturnAutoAcceptEvaluator;

beforeEach(function () {
    Customsetting::set('returns_auto_accept_enabled', '1');
    Customsetting::set('returns_auto_accept_max_days', '14');
    Customsetting::set('returns_auto_accept_excluded_category_ids', []);
    Customsetting::set('returns_auto_accept_excluded_order_origins', []);
    Customsetting::set('returns_auto_accept_max_amount', null);
    $this->evaluator = app(ReturnAutoAcceptEvaluator::class);
});

function makeReturnForEvaluator(array $orderAttrs = [], int $qty = 1, float $price = 10.0): OrderReturn
{
    $order = Order::create(array_merge(['email' => 'a@b.nl', 'status' => 'paid', 'order_origin' => 'own'], $orderAttrs));
    // OrderProduct.price is a LINE total (price for all qty items combined).
    // Unit price = price / quantity.
    $op = OrderProduct::create(['order_id' => $order->id, 'name' => 'P', 'quantity' => $qty, 'price' => $price]);
    $return = OrderReturn::create(['order_id' => $order->id, 'email' => 'a@b.nl']);
    OrderReturnLine::create(['order_return_id' => $return->id, 'order_product_id' => $op->id, 'quantity' => $qty]);

    return $return->fresh('lines');
}

it('returns false when auto-accept is disabled', function () {
    Customsetting::set('returns_auto_accept_enabled', '0');
    expect($this->evaluator->shouldAutoAccept(makeReturnForEvaluator()))->toBeFalse();
});

it('accepts a recent order within the day window', function () {
    expect($this->evaluator->shouldAutoAccept(makeReturnForEvaluator()))->toBeTrue();
});

it('rejects an order older than the day window', function () {
    $return = makeReturnForEvaluator();
    $return->order->update(['created_at' => now()->subDays(30)]);
    expect($this->evaluator->shouldAutoAccept($return->fresh('lines')))->toBeFalse();
});

it('rejects an excluded order origin', function () {
    Customsetting::set('returns_auto_accept_excluded_order_origins', ['Bol']);
    expect($this->evaluator->shouldAutoAccept(makeReturnForEvaluator(['order_origin' => 'Bol'])))->toBeFalse();
});

it('rejects above the max amount', function () {
    Customsetting::set('returns_auto_accept_max_amount', '5');
    // price=10.0 is a LINE total for qty=1, so unit price = 10.0 > 5
    expect($this->evaluator->shouldAutoAccept(makeReturnForEvaluator(qty: 1, price: 10.0)))->toBeFalse();
});

it('accepts at or below the max amount', function () {
    Customsetting::set('returns_auto_accept_max_amount', '50');
    // price=10.0 is a LINE total for qty=1, so unit price = 10.0 <= 50
    expect($this->evaluator->shouldAutoAccept(makeReturnForEvaluator(qty: 1, price: 10.0)))->toBeTrue();
});

it('rejects when a returned product is in an excluded category', function () {
    $category = ProductCategory::create([
        'name' => ['en' => 'Excluded Cat'],
        'slug' => ['en' => 'excluded-cat'],
        'site_ids' => [\Dashed\DashedCore\Classes\Sites::getActive()],
    ]);

    Customsetting::set('returns_auto_accept_excluded_category_ids', [$category->id]);

    $order = Order::create(['email' => 'a@b.nl', 'status' => 'paid', 'order_origin' => 'own']);
    $product = Product::withoutEvents(function () use ($category) {
        $p = Product::create([
            'name' => ['en' => 'Cat Product'],
            'slug' => ['en' => 'cat-product-'.uniqid()],
            'price' => 20.0,
            'site_ids' => [\Dashed\DashedCore\Classes\Sites::getActive()],
        ]);
        $p->productCategories()->attach($category->id);

        return $p;
    });

    $op = OrderProduct::create(['order_id' => $order->id, 'name' => 'Cat Product', 'quantity' => 1, 'price' => 20.0, 'product_id' => $product->id]);
    $return = OrderReturn::create(['order_id' => $order->id, 'email' => 'a@b.nl']);
    OrderReturnLine::create(['order_return_id' => $return->id, 'order_product_id' => $op->id, 'quantity' => 1]);

    expect($this->evaluator->shouldAutoAccept($return->fresh('lines')))->toBeFalse();
});

it('accepts when returned products are not in an excluded category', function () {
    $excludedCategory = ProductCategory::create([
        'name' => ['en' => 'Excluded Cat'],
        'slug' => ['en' => 'excluded-cat-'.uniqid()],
        'site_ids' => [\Dashed\DashedCore\Classes\Sites::getActive()],
    ]);
    $allowedCategory = ProductCategory::create([
        'name' => ['en' => 'Allowed Cat'],
        'slug' => ['en' => 'allowed-cat-'.uniqid()],
        'site_ids' => [\Dashed\DashedCore\Classes\Sites::getActive()],
    ]);

    Customsetting::set('returns_auto_accept_excluded_category_ids', [$excludedCategory->id]);

    $order = Order::create(['email' => 'a@b.nl', 'status' => 'paid', 'order_origin' => 'own']);
    $product = Product::withoutEvents(function () use ($allowedCategory) {
        $p = Product::create([
            'name' => ['en' => 'Allowed Product'],
            'slug' => ['en' => 'allowed-product-'.uniqid()],
            'price' => 15.0,
            'site_ids' => [\Dashed\DashedCore\Classes\Sites::getActive()],
        ]);
        $p->productCategories()->attach($allowedCategory->id);

        return $p;
    });

    $op = OrderProduct::create(['order_id' => $order->id, 'name' => 'Allowed Product', 'quantity' => 1, 'price' => 15.0, 'product_id' => $product->id]);
    $return = OrderReturn::create(['order_id' => $order->id, 'email' => 'a@b.nl']);
    OrderReturnLine::create(['order_return_id' => $return->id, 'order_product_id' => $op->id, 'quantity' => 1]);

    expect($this->evaluator->shouldAutoAccept($return->fresh('lines')))->toBeTrue();
});
