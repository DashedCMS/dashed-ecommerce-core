<?php

use Dashed\DashedEcommerceCore\Models\Order;
use Dashed\DashedEcommerceCore\Models\Product;
use Dashed\DashedEcommerceCore\Models\OrderProduct;
use Dashed\DashedEcommerceCore\Models\ProductGroup;

function openOrdersProduct(): Product
{
    $group = ProductGroup::create([
        'name' => ['en' => 'Group'],
        'slug' => ['en' => 'group'],
        'short_description' => ['en' => ''],
        'description' => ['en' => ''],
        'content' => ['en' => ''],
        'search_terms' => ['en' => ''],
        'site_ids' => ['default'],
    ]);

    return Product::withoutEvents(fn () => Product::create([
        'name' => ['en' => 'Product'],
        'slug' => ['en' => 'product'],
        'site_ids' => ['default'],
        'product_group_id' => $group->id,
        'price' => 10.00,
        'current_price' => 10.00,
    ]));
}

function orderWithProduct(Product $product, string $status, string $fulfillmentStatus): Order
{
    $order = Order::withoutEvents(fn () => Order::create([
        'total' => 10.00,
        'status' => $status,
        'fulfillment_status' => $fulfillmentStatus,
        'email' => 'klant@example.com',
        'site_id' => 'default',
        'ip' => '127.0.0.1',
        'hash' => bin2hex(random_bytes(8)),
    ]));

    OrderProduct::withoutEvents(fn () => OrderProduct::create([
        'order_id' => $order->id,
        'product_id' => $product->id,
        'name' => 'Product',
        'quantity' => 1,
        'price' => 10.00,
    ]));

    return $order;
}

it('telt alleen betaalde, nog niet afgehandelde orders met dit product', function () {
    $product = openOrdersProduct();

    // Tellen wel mee:
    orderWithProduct($product, 'paid', 'unhandled');
    orderWithProduct($product, 'partially_paid', 'unhandled');

    // Tellen niet mee: alles wat niet op 'unhandled' staat telt niet als
    // openstaande bestelling, ook al is de order nog niet volledig afgehandeld.
    orderWithProduct($product, 'paid', 'handled');
    orderWithProduct($product, 'paid', 'in_treatment');
    orderWithProduct($product, 'paid', 'packed');
    orderWithProduct($product, 'paid', 'ready_for_pickup');
    orderWithProduct($product, 'paid', 'shipped');
    orderWithProduct($product, 'pending', 'unhandled');
    orderWithProduct($product, 'cancelled', 'unhandled');

    // Andere producten mogen niet meetellen:
    $otherProduct = openOrdersProduct();
    orderWithProduct($otherProduct, 'paid', 'unhandled');

    expect($product->openOrdersCount())->toBe(2);
});

it('geeft 0 als er geen openstaande orders zijn', function () {
    $product = openOrdersProduct();
    orderWithProduct($product, 'paid', 'handled');

    expect($product->openOrdersCount())->toBe(0);
});
