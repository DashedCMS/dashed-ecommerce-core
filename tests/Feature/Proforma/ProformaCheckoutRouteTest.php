<?php

use Dashed\DashedEcommerceCore\Models\Order;

it('returns 404 for a non-proforma order hash', function () {
    $order = Order::create(['email' => 'a@b.nl', 'status' => Order::STATUS_CONCEPT, 'is_proforma' => false]);
    $this->get('/proforma/' . $order->hash)->assertNotFound();
});

it('shows the proforma checkout for an unpaid proforma', function () {
    $order = Order::create(['email' => 'a@b.nl', 'status' => Order::STATUS_CONCEPT, 'is_proforma' => true]);
    $this->get('/proforma/' . $order->hash)->assertOk();
});
