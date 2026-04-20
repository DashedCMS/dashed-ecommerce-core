<?php

use Dashed\DashedCore\Models\User;
use Dashed\DashedEcommerceCore\Models\POSCart;
use Dashed\DashedEcommerceCore\Models\Order;
use Dashed\DashedEcommerceCore\Classes\ConceptOrderService;

function makeCashier(): User
{
    return User::create([
        'name' => 'Cashier',
        'email' => 'cashier-'.uniqid().'@example.com',
        'password' => bcrypt('secret'),
    ]);
}

it('persists the prices_ex_vat flag from the POS cart onto the concept order', function () {
    $cashier = makeCashier();
    $posCart = POSCart::create([
        'user_id' => $cashier->id,
        'identifier' => 'test-'.uniqid(),
        'products' => [[
            'id' => null,
            'identifier' => 'x',
            'name' => 'Thing',
            'quantity' => 1,
            'singlePrice' => 121,
            'price' => 121,
        ]],
        'prices_ex_vat' => true,
    ]);

    $order = ConceptOrderService::saveAsConcept($posCart, $cashier);

    expect($order->prices_ex_vat)->toBeTrue();
    expect(Order::find($order->id)->prices_ex_vat)->toBeTrue();
});

it('restores the prices_ex_vat flag to the POS cart when hydrating from a concept', function () {
    $cashier = makeCashier();
    $posCart = POSCart::create([
        'user_id' => $cashier->id,
        'identifier' => 'test-'.uniqid(),
        'products' => [],
        'prices_ex_vat' => false,
    ]);

    $order = Order::create([
        'status' => Order::STATUS_CONCEPT,
        'order_origin' => 'pos',
        'user_id' => $cashier->id,
        'prices_ex_vat' => true,
        'total' => 0,
        'subtotal' => 0,
        'btw' => 0,
        'discount' => 0,
    ]);

    ConceptOrderService::hydrate($posCart, $order);

    expect($posCart->fresh()->prices_ex_vat)->toBeTrue();
});
