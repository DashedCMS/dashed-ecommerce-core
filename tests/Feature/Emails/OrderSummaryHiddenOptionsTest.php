<?php

use Dashed\DashedCore\Mail\EmailBlocks\OrderSummaryBlock;
use Dashed\DashedEcommerceCore\Models\Order;
use Dashed\DashedEcommerceCore\Models\OrderProduct;
use Dashed\DashedEcommerceCore\Models\Product;
use Dashed\DashedEcommerceCore\Models\ProductGroup;

uses(\Illuminate\Foundation\Testing\RefreshDatabase::class);

it('renders chosen variation options (hidden_options) in the order-summary email block', function () {
    $productGroup = ProductGroup::create([
        'name' => ['en' => 'Shirts'],
        'slug' => ['en' => 'shirts'],
        'short_description' => ['en' => ''],
        'description' => ['en' => ''],
        'content' => ['en' => ''],
        'search_terms' => ['en' => ''],
        'site_ids' => ['default'],
    ]);

    $product = Product::withoutEvents(fn () => Product::create([
        'name' => ['en' => 'T-shirt'],
        'slug' => ['en' => 't-shirt'],
        'site_ids' => ['default'],
        'product_group_id' => $productGroup->id,
        'price' => 20.00,
        'current_price' => 20.00,
    ]));

    $order = Order::create([
        'invoice_id' => 'INV-'.uniqid(),
        'status' => 'paid',
        'order_origin' => 'own',
        'total' => 20.0,
    ]);

    $orderProduct = new OrderProduct();
    $orderProduct->order_id = $order->id;
    $orderProduct->product_id = $product->id;
    $orderProduct->name = 'T-shirt';
    $orderProduct->sku = 'TSHIRT-001';
    $orderProduct->quantity = 1;
    $orderProduct->price = 20.0;
    $orderProduct->product_extras = [];
    $orderProduct->hidden_options = [
        'Kleur' => 'Rood',
        'Maat' => 'L',
        'Upload' => 'data:image/png;base64,iVBORw0KGgoAAAANSUhEUg==',
    ];
    $orderProduct->save();

    $html = OrderSummaryBlock::render(['show_totals' => false], ['order' => $order->fresh()]);

    expect($html)->toContain('Kleur: Rood');
    expect($html)->toContain('Maat: L');
    // Bestand-/base64-waarden worden bewust niet getoond.
    expect($html)->not->toContain('base64');
});
