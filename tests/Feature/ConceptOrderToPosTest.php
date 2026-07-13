<?php

use App\Models\User;
use Dashed\DashedEcommerceCore\Models\Order;
use Dashed\DashedEcommerceCore\Models\POSCart;
use Dashed\DashedEcommerceCore\Models\OrderProduct;
use Dashed\DashedEcommerceCore\Classes\ConceptOrderService;

uses(\Illuminate\Foundation\Testing\RefreshDatabase::class);

function posCartForTest(): POSCart
{
    $cart = new POSCart();
    $cart->user_id = User::factory()->create()->id;
    $cart->status = 'active';
    $cart->identifier = uniqid();
    $cart->products = [];
    $cart->save();

    return $cart;
}

it('copies a normal order into the cart from its order products, unlinked', function () {
    $order = Order::create(['email' => 'a@b.nl', 'status' => 'paid', 'invoice_id' => 'INV-CP1', 'prices_ex_vat' => false]);
    OrderProduct::create(['order_id' => $order->id, 'product_id' => null, 'name' => 'Los product', 'quantity' => 2, 'price' => 20.0, 'vat_rate' => 21]);

    $cart = posCartForTest();
    $cart->loaded_concept_order_id = 999;
    $cart->save();

    ConceptOrderService::copyIntoCart($cart, $order);

    $products = $cart->fresh()->products;
    expect($products)->toHaveCount(1)
        ->and($products[0]['name'])->toBe('Los product')
        ->and((int) $products[0]['quantity'])->toBe(2)
        ->and((float) $products[0]['price'])->toBe(20.0)
        ->and($products[0]['identifier'])->toBeString()->not->toBe('')
        ->and($cart->fresh()->loaded_concept_order_id)->toBeNull();
});

it('copies a concept order into the cart using its snapshot, unlinked', function () {
    $snapshot = [[
        'id' => null,
        'identifier' => 'oud',
        'name' => 'Snapshot product',
        'quantity' => 1,
        'price' => 15.0,
        'vat_rate' => 21,
        'extra' => [],
    ]];

    $order = Order::create([
        'email' => 'a@b.nl',
        'status' => Order::STATUS_CONCEPT,
        'concept_cart_snapshot' => $snapshot,
    ]);

    $cart = posCartForTest();

    ConceptOrderService::copyIntoCart($cart, $order);

    $products = $cart->fresh()->products;
    expect($products)->toHaveCount(1)
        ->and($products[0]['name'])->toBe('Snapshot product')
        ->and($products[0]['identifier'])->not->toBe('oud')
        ->and($cart->fresh()->loaded_concept_order_id)->toBeNull();
});

it('links the concept when hydrating for editing', function () {
    $snapshot = [['id' => null, 'identifier' => 'x', 'name' => 'Concept regel', 'quantity' => 1, 'price' => 10.0, 'vat_rate' => 21, 'extra' => []]];
    $order = Order::create(['email' => 'a@b.nl', 'status' => Order::STATUS_CONCEPT, 'concept_cart_snapshot' => $snapshot]);

    $cart = posCartForTest();
    $cart->products = [];
    $cart->loaded_concept_order_id = $order->id;
    $cart->save();

    ConceptOrderService::hydrate($cart, $order);

    expect($cart->fresh()->products)->toHaveCount(1)
        ->and($cart->fresh()->loaded_concept_order_id)->toBe($order->id);
});
