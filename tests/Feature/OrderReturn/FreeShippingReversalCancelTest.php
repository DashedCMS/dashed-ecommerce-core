<?php

require_once __DIR__ . '/../../Support/SpilOrder.php';
require_once __DIR__ . '/../../Support/FreeShippingReversalFixtures.php';

use Dashed\DashedEcommerceCore\Livewire\Orders\CancelOrder;

function cancelComponent(array $f): CancelOrder
{
    $component = new CancelOrder();
    $component->order = $f['order']->fresh();

    return $component;
}

it('rekent de inhouding uit voor de gekozen aantallen', function () {
    $f = reversalFixture();
    $data = ["order_product_{$f['shirt']->id}" => 1, "order_product_{$f['broek']->id}" => 1, 'charge_shipping' => true];

    expect(cancelComponent($f)->reversalAmountFor($data))->toBe(7.95);
});

it('houdt niets in als de toggle uit staat, bij een volledige annulering of boven de drempel', function () {
    $f = reversalFixture();
    $shirt = "order_product_{$f['shirt']->id}";
    $broek = "order_product_{$f['broek']->id}";

    expect(cancelComponent($f)->reversalAmountFor([$shirt => 1, $broek => 1, 'charge_shipping' => false]))->toBeNull()
        ->and(cancelComponent($f)->reversalAmountFor([$shirt => 3, $broek => 1, 'charge_shipping' => true]))->toBeNull()
        ->and(cancelComponent($f)->reversalAmountFor([$shirt => 1, $broek => 0, 'charge_shipping' => true]))->toBeNull();
});

it('begrenst een afwijkend bedrag op het voorstel', function () {
    $f = reversalFixture();
    $data = ["order_product_{$f['shirt']->id}" => 1, "order_product_{$f['broek']->id}" => 1, 'charge_shipping' => true];

    expect(cancelComponent($f)->reversalAmountFor($data + ['charge_shipping_amount' => 5]))->toBe(5.0)
        ->and(cancelComponent($f)->reversalAmountFor($data + ['charge_shipping_amount' => 50]))->toBe(7.95);
});
