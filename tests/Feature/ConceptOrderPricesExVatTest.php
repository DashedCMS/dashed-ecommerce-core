<?php

use Dashed\DashedCore\Models\User;
use Dashed\DashedEcommerceCore\Models\Order;
use Dashed\DashedEcommerceCore\Models\POSCart;
use Dashed\DashedEcommerceCore\Classes\ConceptOrderService;
use Dashed\DashedEcommerceCore\Controllers\Api\PointOfSale\PointOfSaleApiController;

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

it('restores the POS cart from the concept snapshot verbatim (totals, vat_rate, extras)', function () {
    $cashier = makeCashier();
    $products = [
        [
            'id' => null,
            'identifier' => 'first',
            'name' => 'Custom 9%',
            'quantity' => 2,
            'singlePrice' => 10.90,
            'price' => 21.80,
            'vat_rate' => 9,
            'extra' => [],
            'customProduct' => true,
            'isCustomPrice' => true,
        ],
        [
            'id' => null,
            'identifier' => 'second',
            'name' => 'Custom 21%',
            'quantity' => 1,
            'singlePrice' => 50.00,
            'price' => 50.00,
            'vat_rate' => 21,
            'extra' => ['engraving' => 'Happy bday'],
            'customProduct' => true,
            'isCustomPrice' => true,
        ],
    ];

    $posCart = POSCart::create([
        'user_id' => $cashier->id,
        'identifier' => 'test-'.uniqid(),
        'products' => $products,
        'prices_ex_vat' => true,
    ]);

    $order = ConceptOrderService::saveAsConcept($posCart, $cashier);

    expect((float) $order->subtotal)->toEqualWithDelta(71.80, 0.001);
    expect((float) $order->btw)->toEqualWithDelta(1.80 + 8.68, 0.02); // 9% of €20 ex + 21% of €41.32 ex
    expect($order->concept_cart_snapshot)->toBeArray();
    expect(count($order->concept_cart_snapshot))->toBe(2);

    $freshPos = POSCart::create([
        'user_id' => $cashier->id,
        'identifier' => 'fresh-'.uniqid(),
        'products' => [],
    ]);

    ConceptOrderService::hydrate($freshPos, Order::find($order->id));

    $restored = $freshPos->fresh()->products;
    expect($restored)->toHaveCount(2);

    // Quantities, unit prices, and vat rates survive the round-trip.
    expect($restored[0]['quantity'])->toBe(2);
    expect((float) $restored[0]['price'])->toEqualWithDelta(21.80, 0.001);
    expect((float) $restored[0]['singlePrice'])->toEqualWithDelta(10.90, 0.001);
    expect((float) $restored[0]['vat_rate'])->toBe(9.0);

    expect((float) $restored[1]['price'])->toEqualWithDelta(50.00, 0.001);
    expect((float) $restored[1]['vat_rate'])->toBe(21.0);
    expect($restored[1]['extra'])->toBe(['engraving' => 'Happy bday']);

    // prices_ex_vat and identifiers are preserved as expected.
    expect($freshPos->fresh()->prices_ex_vat)->toBeTrue();
    expect($restored[0]['identifier'])->not->toBe('first');
});

it('copies prices_ex_vat from POSCart to the order created via PointOfSaleApiController::createOrder', function () {
    $cashier = User::create([
        'first_name' => 'Cash',
        'last_name' => 'Ier',
        'email' => 'cashier-'.uniqid().'@test.dev',
        'password' => bcrypt('x'),
    ]);

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
            'vat_rate' => 21,
        ]],
        'prices_ex_vat' => true,
    ]);

    $controller = app(PointOfSaleApiController::class);
    $response = $controller->createOrder('pos', $posCart, null, 'pos', $cashier->id);

    expect($response['success'] ?? false)->toBeTrue();

    $order = Order::latest('id')->first();
    expect($order)->not->toBeNull();
    expect($order->prices_ex_vat)->toBeTrue();
});
