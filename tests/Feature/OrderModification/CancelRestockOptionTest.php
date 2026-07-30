<?php

use Illuminate\Support\Facades\Queue;
use Dashed\DashedEcommerceCore\Models\Order;
use Dashed\DashedEcommerceCore\Models\Product;
use Dashed\DashedEcommerceCore\Models\ProductGroup;
use Dashed\DashedEcommerceCore\Models\OrderProduct;

uses(\Illuminate\Foundation\Testing\RefreshDatabase::class);

/**
 * Exact het patroon uit tests/Feature/IdempotentStockUpdateTest.php: name en
 * slug zijn translatable arrays, de save-hook wordt uitgezet en de queue
 * gefaket omdat UpdateProductInformationJob MySQL-only SQL gebruikt.
 */
function stockedProduct(int $stock = 5): Product
{
    Queue::fake();

    $group = ProductGroup::create([
        'name' => ['en' => 'Group'], 'slug' => ['en' => 'group-' . uniqid()],
        'short_description' => ['en' => ''], 'description' => ['en' => ''],
        'content' => ['en' => ''], 'search_terms' => ['en' => ''],
        'site_ids' => ['site'],
    ]);

    return Product::withoutEvents(fn () => Product::create([
        'product_group_id' => $group->id,
        'name' => ['en' => 'Voorraadproduct'],
        'slug' => ['en' => 'voorraad-' . uniqid()],
        'site_ids' => ['site'],
        'price' => 10, 'current_price' => 10, 'vat_rate' => 21,
        'use_stock' => true, 'stock' => $stock,
        'images' => [],
    ]));
}

function paidOrderWithStockedProduct(int $stock = 5, int $quantity = 2): array
{
    $product = stockedProduct($stock);

    $order = Order::create(['email' => 'a@b.nl', 'status' => 'paid', 'total' => 20]);
    OrderProduct::create([
        'order_id' => $order->id,
        'product_id' => $product->id,
        'name' => 'Voorraadproduct',
        'quantity' => $quantity,
        'price' => 20,
        'vat_rate' => 21,
    ]);

    return [$order->fresh(), $product];
}

it('boekt de voorraad standaard terug bij annuleren', function () {
    [$order, $product] = paidOrderWithStockedProduct(stock: 5, quantity: 2);

    $order->markAsCancelled();

    expect($product->fresh()->stock)->toBe(7);
});

it('laat de voorraad staan wanneer terugboeken uitgezet is', function () {
    [$order, $product] = paidOrderWithStockedProduct(stock: 5, quantity: 2);

    $order->markAsCancelled(sendMail: false, refillStock: false);

    expect($product->fresh()->stock)->toBe(5)
        ->and($order->fresh()->status)->toBe('cancelled');
});
