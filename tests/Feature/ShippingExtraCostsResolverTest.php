<?php

declare(strict_types=1);

use Dashed\DashedCore\Classes\Sites;
use Dashed\DashedEcommerceCore\Models\Cart;
use Dashed\DashedEcommerceCore\Models\Product;
use Dashed\DashedEcommerceCore\Models\CartItem;
use Dashed\DashedEcommerceCore\Classes\CartHelper;
use Dashed\DashedEcommerceCore\Models\ProductGroup;
use Dashed\DashedEcommerceCore\Models\ShippingZone;
use Dashed\DashedEcommerceCore\Classes\ShoppingCart;
use Dashed\DashedEcommerceCore\Models\ShippingMethod;

/**
 * Een webshop kan een toeslag bovenop de verzendkosten registreren
 * (ShippingMethod::resolveExtraCostsUsing()), bijvoorbeeld per extra doos
 * als de bestelling niet in één pakket past. Die telt bij elke methode
 * behalve afhalen, ook bij gratis verzending, zonder dat die methode
 * daardoor vervalt.
 */
beforeEach(function () {
    CartHelper::$cart = null;
    CartHelper::$cartItemsInitialized = false;
    CartHelper::$cartItems = [];
    CartHelper::$cartProductsById = [];
    ShippingMethod::resolveExtraCostsUsing(null);
});

afterEach(fn () => ShippingMethod::resolveExtraCostsUsing(null));

$productVan = function (float $prijs): Product {
    $group = ProductGroup::create([
        'name' => ['nl' => 'Groep'],
        'slug' => ['nl' => 'groep-' . uniqid()],
        'short_description' => ['nl' => ''],
        'description' => ['nl' => ''],
        'content' => ['nl' => ''],
        'search_terms' => ['nl' => ''],
        'site_ids' => [Sites::getActive()],
    ]);

    return Product::withoutEvents(fn () => Product::create([
        'name' => ['nl' => 'Product'],
        'slug' => ['nl' => 'product-' . uniqid()],
        'site_ids' => [Sites::getActive()],
        'product_group_id' => $group->id,
        'use_stock' => 0,
        'stock' => 0,
        'total_stock' => 0,
        'in_stock' => 1,
        'stock_status' => 'in_stock',
        'price' => $prijs,
        'current_price' => $prijs,
        'public' => 1,
    ]));
};

$wagenMet = function (Product $product, int $aantal = 1): void {
    $token = (string) \Illuminate\Support\Str::uuid();
    request()->cookies->set(config('dashed-ecommerce.cart_cookie', 'cart_token'), $token);

    $cart = Cart::create(['token' => $token, 'type' => 'default']);
    CartItem::create([
        'cart_id' => $cart->id,
        'product_id' => $product->id,
        'name' => 'Product',
        'unit_price' => $product->price,
        'quantity' => $aantal,
        'options' => [],
        'options_hash' => '',
    ]);

    CartHelper::$cart = null;
    CartHelper::$cartItemsInitialized = false;
    CartHelper::$cartItems = [];
    CartHelper::$cartProductsById = [];
};

$zoneMetMethodes = function (): ShippingZone {
    $zone = ShippingZone::create([
        'site_id' => Sites::getActive(),
        'name' => ['nl' => 'Nederland'],
        'zones' => ['Nederland'],
        'search_fields' => 'Nederland',
    ]);

    foreach ([['Verzenden', 'static_amount', 5], ['Gratis verzending', 'free_delivery', 0], ['Ophalen', 'take_away', 0]] as [$naam, $soort, $kosten]) {
        ShippingMethod::create([
            'shipping_zone_id' => $zone->id,
            'name' => ['nl' => $naam],
            'costs' => $kosten,
            'sort' => $soort,
            'minimum_order_value' => 0,
            'maximum_order_value' => 100000,
            'order' => 1,
        ]);
    }

    return $zone;
};

$perNaam = fn () => collect(ShoppingCart::getAvailableShippingMethods('Nederland'))
    ->mapWithKeys(fn ($m) => [$m->getTranslation('name', 'nl') => ['costs' => (float) $m->costs, 'extra' => (float) $m->extraCosts, 'label' => $m->costsFormatted]])
    ->all();

it('rekent zonder resolver de gewone kosten', function () use ($productVan, $wagenMet, $zoneMetMethodes, $perNaam) {
    $zoneMetMethodes();
    $wagenMet($productVan(80));

    $methodes = $perNaam();

    expect($methodes['Verzenden']['costs'])->toBe(5.0)
        ->and($methodes['Verzenden']['extra'])->toBe(0.0)
        ->and($methodes['Gratis verzending']['costs'])->toBe(0.0)
        ->and($methodes['Ophalen']['costs'])->toBe(0.0);
});

it('telt de toeslag bij elke methode behalve afhalen en laat gratis verzending staan', function () use ($productVan, $wagenMet, $zoneMetMethodes, $perNaam) {
    $zone = $zoneMetMethodes();
    $wagenMet($productVan(80), 3);

    $ontvangen = [];
    ShippingMethod::resolveExtraCostsUsing(function (ShippingMethod $methode, ?int $zoneId, array $cartItems) use (&$ontvangen): float {
        $ontvangen[$methode->getTranslation('name', 'nl')] = ['zone' => $zoneId, 'qty' => $cartItems[0]->qty ?? null];

        return 7.95;
    });

    $methodes = $perNaam();

    expect($methodes['Verzenden']['costs'])->toBe(12.95)
        ->and($methodes['Verzenden']['extra'])->toBe(7.95)
        ->and($methodes['Gratis verzending']['costs'])->toBe(7.95)
        ->and($methodes['Gratis verzending']['extra'])->toBe(7.95)
        ->and($methodes['Gratis verzending']['label'])->not->toBe('Gratis')
        ->and($methodes['Ophalen']['costs'])->toBe(0.0)
        ->and($methodes['Ophalen']['extra'])->toBe(0.0)
        ->and($ontvangen['Verzenden'])->toBe(['zone' => $zone->id, 'qty' => 3])
        ->and($ontvangen)->not->toHaveKey('Ophalen');
});

it('negeert een negatieve of nul-toeslag', function () use ($productVan, $wagenMet, $zoneMetMethodes, $perNaam) {
    $zoneMetMethodes();
    $wagenMet($productVan(80));

    ShippingMethod::resolveExtraCostsUsing(fn () => -3.0);

    $methodes = $perNaam();

    expect($methodes['Verzenden']['costs'])->toBe(5.0)
        ->and($methodes['Gratis verzending']['costs'])->toBe(0.0)
        ->and($methodes['Gratis verzending']['label'])->toBe('Gratis');
});

it('geeft de toeslag ook aan meegegeven regels zonder wagen', function () use ($productVan, $zoneMetMethodes) {
    $zone = $zoneMetMethodes();
    $product = $productVan(80);
    $methode = ShippingMethod::where('sort', 'free_delivery')->first();

    ShippingMethod::resolveExtraCostsUsing(fn (ShippingMethod $m, ?int $zoneId, array $items) => 7.95 * array_sum(array_map(fn ($i) => $i->qty, $items)));

    $regels = [(object) ['model' => $product, 'qty' => 2]];

    expect($methode->costsForCart($zone->id, $regels))->toBe(15.9)
        ->and($methode->extraCostsForCart($zone->id, $regels))->toBe(15.9)
        ->and($methode->costsForCart($zone->id, []))->toBe(0.0);
});
