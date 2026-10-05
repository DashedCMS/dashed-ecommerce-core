<?php

use Illuminate\Support\Facades\DB;
use Dashed\DashedCore\Models\User;
use Dashed\DashedCore\Models\Customsetting;
use Dashed\DashedEcommerceCore\Models\POSCart;
use Dashed\DashedEcommerceCore\Classes\OssVat;
use Dashed\DashedEcommerceCore\Models\ShippingMethod;
use Dashed\DashedEcommerceCore\Classes\ConceptOrderService;

beforeEach(function () {
    OssVat::flush();
    Customsetting::set('company_country', 'Nederland');
    Customsetting::set('taxes_prices_include_taxes', 1);
    Customsetting::set('oss_enabled', '1');
});

function ossVerzendmethode(): ShippingMethod
{
    $zoneId = DB::table('dashed__shipping_zones')->insertGetId([
        'site_id' => 'default', 'name' => json_encode(['en' => 'Zone']), 'zones' => json_encode(['Duitsland']),
        'created_at' => now(), 'updated_at' => now(),
    ]);
    $method = new ShippingMethod();
    $method->forceFill(['shipping_zone_id' => $zoneId, 'name' => ['en' => 'Verzenden'], 'sort' => 'static_amount', 'costs' => 0, 'minimum_order_value' => 0, 'maximum_order_value' => 100000, 'order' => 1])->saveQuietly();

    return $method;
}

function ossConceptCart(string $country, array $extra = []): array
{
    $cashier = User::create([
        'name' => 'Cashier',
        'email' => 'cashier-' . uniqid() . '@example.com',
        'password' => bcrypt('secret'),
    ]);

    $posCart = POSCart::create(array_merge([
        'user_id' => $cashier->id,
        'identifier' => 'oss-' . uniqid(),
        'country' => $country,
        'shipping_method_id' => ossVerzendmethode()->id,
        'products' => [[
            'id' => null,
            'identifier' => 'x',
            'name' => 'Maatwerk',
            'quantity' => 1,
            'singlePrice' => 119,
            'price' => 119,
            'vat_rate' => 21,
            'customProduct' => true,
        ]],
    ], $extra));

    return [$posCart, $cashier];
}

it('zet bij een conceptorder naar Duitsland de order-btw gelijk aan de regel-btw', function () {
    [$posCart, $cashier] = ossConceptCart('Duitsland');

    $order = ConceptOrderService::saveAsConcept($posCart, $cashier)->fresh();

    $line = $order->orderProducts()->first();
    expect((float) $line->vat_rate)->toBe(19.0);
    expect(round((float) $order->btw, 2))->toBe(round((float) $order->orderProducts()->sum('btw'), 2));
    expect($order->vat_percentages)->toHaveKey('19');
    expect($order->vat_percentages)->not->toHaveKey('21');
});

it('laat de btw van een conceptorder naar het eigen land op het eigen tarief', function () {
    [$posCart, $cashier] = ossConceptCart('Nederland');

    $order = ConceptOrderService::saveAsConcept($posCart, $cashier)->fresh();

    expect((float) $order->orderProducts()->first()->vat_rate)->toBe(21.0);
    expect(round((float) $order->btw, 2))->toBe(round((float) $order->orderProducts()->sum('btw'), 2));
});
