<?php

use Dashed\DashedCore\Models\Customsetting;
use Dashed\DashedEcommerceCore\Models\Order;
use Dashed\DashedEcommerceCore\Models\Product;
use Dashed\DashedEcommerceCore\Classes\OssVat;
use Dashed\DashedEcommerceCore\Models\OrderProduct;
use Dashed\DashedEcommerceCore\Models\ProductGroup;
use Illuminate\Support\Facades\DB;
use Dashed\DashedEcommerceCore\Models\ShippingMethod;

function ossLineProduct(float $vatRate = 21): Product
{
    $group = ProductGroup::create([
        'name' => ['en' => 'Groep'],
        'slug' => ['en' => 'groep-' . uniqid()],
        'short_description' => ['en' => ''],
        'description' => ['en' => ''],
        'content' => ['en' => ''],
        'search_terms' => ['en' => ''],
        'site_ids' => ['default'],
        'public' => 1,
    ]);

    return Product::withoutEvents(fn () => Product::create([
        'product_group_id' => $group->id,
        'name' => ['en' => 'Vaas'],
        'slug' => ['en' => 'vaas-' . uniqid()],
        'site_ids' => ['default'],
        'current_price' => 119.00,
        'price' => 119.00,
        'vat_rate' => $vatRate,
        'public' => 1,
    ]));
}

function ossLineOrder(string $country, array $attributes = []): Order
{
    return Order::create(array_merge([
        'status' => 'pending',
        'country' => $country,
        'order_origin' => 'own',
        'subtotal' => 119.00,
        'btw' => 0,
        'total' => 119.00,
        'discount' => 0,
        'vat_percentages' => [],
    ], $attributes));
}

beforeEach(function () {
    OssVat::flush();
    Customsetting::set('company_country', 'Nederland');
    Customsetting::set('oss_enabled', '1');
});

it('geeft een productregel naar Duitsland 19% met dezelfde prijs', function () {
    $order = ossLineOrder('Duitsland');
    $line = OrderProduct::create([
        'order_id' => $order->id, 'product_id' => ossLineProduct()->id,
        'name' => 'Vaas', 'quantity' => 1, 'price' => 119.00, 'vat_rate' => 21,
    ]);

    expect((float) $line->vat_rate)->toBe(19.0);
    expect(round((float) $line->btw, 2))->toBe(19.00);
    expect((float) $line->price)->toBe(119.00);
});

it('laat een verlaagd producttarief staan', function () {
    $order = ossLineOrder('Duitsland');
    $line = OrderProduct::create([
        'order_id' => $order->id, 'product_id' => ossLineProduct(9)->id,
        'name' => 'Boek', 'quantity' => 1, 'price' => 10.90,
    ]);

    expect((float) $line->vat_rate)->toBe(9.0);
    expect(round((float) $line->btw, 2))->toBe(0.90);
});

it('zet verzendkosten zonder product om en herrekent de btw', function () {
    $order = ossLineOrder('Duitsland');
    $line = OrderProduct::create([
        'order_id' => $order->id, 'name' => 'Verzenden', 'sku' => 'shipping_costs',
        'quantity' => 1, 'price' => 8.95, 'vat_rate' => 21, 'btw' => 1.55,
    ]);

    expect((float) $line->vat_rate)->toBe(19.0);
    expect(round((float) $line->btw, 2))->toBe(1.43);
});

it('laat een al omgezette regel zonder product met rust', function () {
    $order = ossLineOrder('Duitsland');
    $line = OrderProduct::create([
        'order_id' => $order->id, 'name' => 'Verzenden', 'sku' => 'shipping_costs',
        'quantity' => 1, 'price' => 8.95, 'vat_rate' => 19, 'btw' => 1.43,
    ]);

    expect((float) $line->vat_rate)->toBe(19.0);
    expect(round((float) $line->btw, 2))->toBe(1.43);
});

it('houdt 21% voor Nederland en als de schakelaar uit staat', function () {
    $product = ossLineProduct();

    $nl = OrderProduct::create(['order_id' => ossLineOrder('Nederland')->id, 'product_id' => $product->id, 'name' => 'Vaas', 'quantity' => 1, 'price' => 121.00]);
    expect((float) $nl->vat_rate)->toBe(21.0);

    Customsetting::set('oss_enabled', '0');
    $de = OrderProduct::create(['order_id' => ossLineOrder('Duitsland')->id, 'product_id' => $product->id, 'name' => 'Vaas', 'quantity' => 1, 'price' => 119.00]);
    expect((float) $de->vat_rate)->toBe(21.0);
});

it('houdt 0% bij verlegde btw naar het buitenland', function () {
    $order = ossLineOrder('Duitsland', ['vat_reverse_charge' => true]);
    $line = OrderProduct::create([
        'order_id' => $order->id, 'name' => 'Verzenden', 'sku' => 'shipping_costs',
        'quantity' => 1, 'price' => 8.95, 'vat_rate' => 21, 'btw' => 1.55,
    ]);

    expect((float) $line->vat_rate)->toBe(21.0);
});

it('spiegelt op een creditorder het tarief van de oorspronkelijke regel', function () {
    $original = ossLineOrder('Duitsland');
    $credit = ossLineOrder('Duitsland', ['credit_for_order_id' => $original->id, 'total' => -119.00]);

    $line = OrderProduct::create([
        'order_id' => $credit->id, 'product_id' => ossLineProduct()->id,
        'name' => 'Vaas', 'quantity' => -1, 'price' => -119.00, 'vat_rate' => 21,
    ]);

    expect((float) $line->vat_rate)->toBe(21.0);
    expect(round((float) $line->btw, 2))->toBe(-20.65);
});

it('houdt 21% bij ophalen (take_away) naar het buitenland', function () {
    $zoneId = DB::table('dashed__shipping_zones')->insertGetId([
        'site_id' => 'default', 'name' => json_encode(['en' => 'Zone']), 'zones' => json_encode(['Duitsland']),
        'created_at' => now(), 'updated_at' => now(),
    ]);
    $method = new ShippingMethod();
    $method->forceFill(['shipping_zone_id' => $zoneId, 'name' => ['en' => 'Ophalen'], 'sort' => 'take_away', 'costs' => 0, 'minimum_order_value' => 0, 'maximum_order_value' => 100000, 'order' => 1])->saveQuietly();
    $order = ossLineOrder('Duitsland', ['shipping_method_id' => $method->id]);

    $line = OrderProduct::create([
        'order_id' => $order->id, 'name' => 'Vaas', 'quantity' => 1, 'price' => 121.00, 'vat_rate' => 21, 'btw' => 21.00,
    ]);

    expect((float) $line->vat_rate)->toBe(21.0);
});

it('laat een product zonder tarief op een OSS-order op null met de 21-terugval', function () {
    $order = ossLineOrder('Duitsland');
    $product = ossLineProduct();
    // De kolom is NOT NULL, dus het null-tarief zetten we alleen op de geladen relatie.
    $product->vat_rate = null;

    $line = new OrderProduct(['order_id' => $order->id, 'product_id' => $product->id, 'name' => 'Vaas', 'quantity' => 1, 'price' => 121.00]);
    $line->setRelation('product', $product);
    $line->save();

    expect($line->vat_rate)->toBeNull();
    expect(round((float) $line->btw, 2))->toBe(21.00);
});

it('laadt de order niet in de hook als OSS uit staat', function () {
    $order = ossLineOrder('Duitsland');
    $product = ossLineProduct();
    Customsetting::set('oss_enabled', '0');

    DB::flushQueryLog();
    DB::enableQueryLog();
    OrderProduct::create(['order_id' => $order->id, 'product_id' => $product->id, 'name' => 'Vaas', 'quantity' => 1, 'price' => 121.00]);
    $queries = collect(DB::getQueryLog())->pluck('query')->filter(fn ($q) => str_starts_with($q, 'select') && (str_contains($q, 'dashed__orders') || str_contains($q, 'dashed__shipping_methods')));
    DB::disableQueryLog();

    expect($queries)->toBeEmpty();
});
