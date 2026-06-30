<?php

use Dashed\DashedEcommerceCore\Models\Order;
use Dashed\DashedEcommerceCore\Mail\ProformaCheckoutMail;

it('renders the proforma checkout url in the mail', function () {
    $order = Order::create(['email' => 'a@b.nl', 'status' => Order::STATUS_CONCEPT, 'is_proforma' => true]);
    $url = 'https://shop.test/proforma/' . $order->hash;

    $rendered = (new ProformaCheckoutMail($order, $url))->render();

    expect($rendered)->toContain($url);
});
