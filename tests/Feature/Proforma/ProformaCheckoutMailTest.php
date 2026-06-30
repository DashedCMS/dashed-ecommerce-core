<?php

declare(strict_types=1);

use Dashed\DashedEcommerceCore\Models\Order;
use Dashed\DashedEcommerceCore\Mail\ProformaCheckoutMail;

it('renders the proforma checkout url in the mail', function () {
    $order = Order::create(['email' => 'a@b.nl', 'status' => Order::STATUS_CONCEPT, 'is_proforma' => true]);
    $url = 'https://shop.test/proforma/' . $order->hash;

    $rendered = (new ProformaCheckoutMail($order, $url))->render();

    expect($rendered)->toContain($url);
});

/**
 * Zonder opgeslagen email-template moet de mail tóch netjes zijn: de
 * gedeelde layout met order-overzicht (producten), niet de kale fallback.
 * Dit faalt als build() terugvalt op platte <p>-HTML.
 */
it('renders the styled layout with product lines even without a saved template', function () {
    $order = Order::create([
        'email' => 'a@b.nl',
        'status' => Order::STATUS_CONCEPT,
        'is_proforma' => true,
        'first_name' => 'Jan',
        'total' => 121.0,
        'btw' => 21.0,
    ]);
    $order->orderProducts()->create([
        'product_id' => null,
        'name' => 'Maatwerk dienst',
        'quantity' => 1,
        'price' => 121.0,
        'vat_rate' => 21,
    ]);

    $html = (new ProformaCheckoutMail($order, url('/proforma/' . $order->hash)))->render();

    expect($html)->toContain('Maatwerk dienst')                  // order-overzicht (gestyled pad)
        ->and($html)->toContain('/proforma/' . $order->hash)     // afrekenlink
        ->and($html)->not->toContain('Jouw bestelling staat klaar om te worden afgerekend.'); // niet de oude kale fallback
});
