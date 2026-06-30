<?php
// tests/Feature/Proforma/ProformaOrderScopeTest.php

use Dashed\DashedEcommerceCore\Models\Order;

it('scopes proforma orders awaiting payment', function () {
    $proforma = Order::create(['email' => 'a@b.nl', 'status' => Order::STATUS_CONCEPT, 'is_proforma' => true]);
    $plainConcept = Order::create(['email' => 'c@d.nl', 'status' => Order::STATUS_CONCEPT, 'is_proforma' => false]);
    $paid = Order::create(['email' => 'e@f.nl', 'status' => 'paid', 'is_proforma' => true]);

    $ids = Order::proformaAwaitingPayment()->pluck('id')->all();

    expect($ids)->toContain($proforma->id)
        ->and($ids)->not->toContain($plainConcept->id)
        ->and($ids)->not->toContain($paid->id);
});

it('casts the proforma flags', function () {
    $order = Order::create(['email' => 'a@b.nl', 'status' => Order::STATUS_CONCEPT, 'is_proforma' => true, 'proforma_allow_shipping' => true]);
    expect($order->fresh()->is_proforma)->toBeTrue()
        ->and($order->fresh()->proforma_allow_shipping)->toBeTrue();
});
