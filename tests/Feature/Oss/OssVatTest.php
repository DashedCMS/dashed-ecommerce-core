<?php

use Dashed\DashedCore\Models\Customsetting;
use Dashed\DashedEcommerceCore\Models\Order;
use Dashed\DashedEcommerceCore\Classes\OssVat;
use Dashed\DashedEcommerceCore\Models\OrderProduct;
use Dashed\DashedEcommerceCore\Models\ShippingMethod;

function ossOrder(array $attributes = [], ?string $shippingSort = null): Order
{
    $order = (new Order())->forceFill(array_merge([
        'country' => 'Duitsland',
        'invoice_country' => null,
        'vat_reverse_charge' => false,
        'order_origin' => 'own',
        'shipping_method_id' => null,
    ], $attributes));

    if ($shippingSort) {
        $order->shipping_method_id = 1;
        $order->setRelation('shippingMethod', (new ShippingMethod())->forceFill(['sort' => $shippingSort]));
    }

    return $order;
}

beforeEach(function () {
    OssVat::flush();
    Customsetting::set('company_country', 'Nederland');
    // OSS werkt alleen bij prijzen inclusief btw; de testdatabase staat op exclusief.
    Customsetting::set('taxes_prices_include_taxes', 1);
    Customsetting::set('oss_enabled', '1');
});

it('herkent een verzonden order naar een ander EU-land', function () {
    expect(OssVat::destinationFor(ossOrder()))->toBe('DE');
    expect(OssVat::destinationFor(ossOrder(['country' => 'BE'])))->toBe('BE');
    expect(OssVat::destinationFor(ossOrder(['country' => 'België'])))->toBe('BE');
});

it('valt terug op het factuurland als het afleverland leeg is', function () {
    expect(OssVat::destinationFor(ossOrder(['country' => '', 'invoice_country' => 'Frankrijk'])))->toBe('FR');
});

it('is geen OSS voor het eigen land, buiten de EU of een onherleidbaar land', function () {
    expect(OssVat::destinationFor(ossOrder(['country' => 'Nederland'])))->toBeNull();
    expect(OssVat::destinationFor(ossOrder(['country' => 'Engeland'])))->toBeNull();
    expect(OssVat::destinationFor(ossOrder(['country' => 'klant@example.com'])))->toBeNull();
    expect(OssVat::destinationFor(ossOrder(['country' => '', 'invoice_country' => ''])))->toBeNull();
});

it('is geen OSS bij verlegde btw, ophalen of een kassaorder zonder verzendmethode', function () {
    expect(OssVat::destinationFor(ossOrder(['vat_reverse_charge' => true])))->toBeNull();
    expect(OssVat::destinationFor(ossOrder([], 'take_away')))->toBeNull();
    expect(OssVat::destinationFor(ossOrder(['order_origin' => 'pos'])))->toBeNull();
});

it('is wel OSS voor een kassaorder met verzending en voor Bol en Etsy zonder verzendmethode', function () {
    expect(OssVat::destinationFor(ossOrder(['order_origin' => 'pos'], 'static_amount')))->toBe('DE');
    expect(OssVat::destinationFor(ossOrder(['order_origin' => 'Bol', 'country' => 'BE'])))->toBe('BE');
    expect(OssVat::destinationFor(ossOrder(['order_origin' => 'etsy', 'country' => 'FR'])))->toBe('FR');
});

it('doet niets als de schakelaar uit staat', function () {
    Customsetting::set('oss_enabled', '0');

    expect(OssVat::destinationFor(ossOrder()))->toBeNull();
    expect(OssVat::rateForOrder(ossOrder(), 21))->toBe(21.0);
});

it('staat uit bij prijzen exclusief btw, ook met de schakelaar aan', function () {
    Customsetting::set('taxes_prices_include_taxes', '0');
    Customsetting::set('oss_enabled', '1');

    expect(OssVat::enabled())->toBeFalse();
    expect(OssVat::destinationFor(ossOrder()))->toBeNull();

    Customsetting::set('taxes_prices_include_taxes', '1');

    expect(OssVat::enabled())->toBeTrue();
    expect(OssVat::destinationFor(ossOrder()))->toBe('DE');
});

it('zet alleen het eigen standaardtarief om', function () {
    expect(OssVat::rateFor('DE', 21))->toBe(19.0);
    expect(OssVat::rateFor('BE', 21))->toBe(21.0);
    expect(OssVat::rateFor('DE', 9))->toBe(9.0);
    expect(OssVat::rateFor('DE', 0))->toBe(0.0);
    expect(OssVat::rateFor('DE', 19))->toBe(19.0);
    expect(OssVat::rateFor('FI', 21))->toBe(25.5);
});

it('laat een tarief per land overschrijven', function () {
    Customsetting::set('oss_vat_rates', json_encode(['DE' => 20, 'fi' => '26']));

    expect(OssVat::rateFor('DE', 21))->toBe(20.0);
    expect(OssVat::rateFor('FI', 21))->toBe(26.0);
    expect(OssVat::rateFor('FR', 21))->toBe(20.0);
});

it('maakt sleutels zonder overbodige decimalen', function () {
    expect(OssVat::rateKey(21))->toBe('21');
    expect(OssVat::rateKey(21.00))->toBe('21');
    expect(OssVat::rateKey(25.5))->toBe('25.5');
    expect(OssVat::rateKey(0))->toBe('0');
    expect(OssVat::rateKey(20))->toBe('20');
});

it('geeft de Nederlandse landnaam', function () {
    expect(OssVat::countryName('DE'))->toBe('Duitsland');
    expect(OssVat::countryName('BE'))->toBe('België');
    expect(OssVat::countryName('XX'))->toBe('XX');
});

it('herrekent order-btw uit de regels van een OSS-order', function () {
    $order = Order::create([
        'status' => 'paid', 'country' => 'Duitsland', 'order_origin' => 'Bol',
        'subtotal' => 119.00, 'btw' => 20.65, 'total' => 119.00, 'discount' => 0,
        'vat_percentages' => ['21' => 20.65],
    ]);
    OrderProduct::create(['order_id' => $order->id, 'name' => 'Vaas', 'quantity' => 1, 'price' => 119.00, 'vat_rate' => 19, 'btw' => 19.00]);

    OssVat::recalculateOrderVat($order);

    $order->refresh();
    expect((float) $order->btw)->toBe(19.0);
    expect($order->vat_percentages)->toEqual(['19' => 19.0]);
});

it('laat order-btw met rust als de order geen OSS is', function () {
    $order = Order::create([
        'status' => 'paid', 'country' => 'Nederland',
        'subtotal' => 121.00, 'btw' => 21.00, 'total' => 121.00, 'discount' => 0,
        'vat_percentages' => ['21' => 21.00],
    ]);
    OrderProduct::create(['order_id' => $order->id, 'name' => 'Vaas', 'quantity' => 1, 'price' => 121.00, 'vat_rate' => 21, 'btw' => 5.00]);

    OssVat::recalculateOrderVat($order);

    expect((float) $order->fresh()->btw)->toBe(21.0);
});
