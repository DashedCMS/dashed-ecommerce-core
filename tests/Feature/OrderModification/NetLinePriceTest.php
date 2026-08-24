<?php

use Dashed\DashedCore\Models\User;
use Dashed\DashedCore\Classes\Sites;
use Illuminate\Support\Facades\Queue;
use Dashed\DashedCore\Models\Customsetting;
use Dashed\DashedEcommerceCore\Models\Order;
use Dashed\DashedEcommerceCore\Models\Product;
use Dashed\DashedEcommerceCore\Models\DiscountCode;
use Dashed\DashedEcommerceCore\Models\OrderProduct;
use Dashed\DashedEcommerceCore\Models\ProductGroup;
use Dashed\DashedEcommerceCore\Classes\OrderTotalsCalculator;
use Dashed\DashedEcommerceCore\Classes\OrderModificationService;

uses(\Illuminate\Foundation\Testing\RefreshDatabase::class);

/**
 * De regelprijs is in dit systeem de prijs ná een procentuele kortingscode.
 *
 * Checkout::createOrder() zet per regel `price = discountedPrice` en
 * `discount = originalPrice - discountedPrice`. Daaruit volgt de conventie die
 * hier vastgelegd wordt, en die op elke echte order klopt:
 *
 *   subtotal = som(price + discount)
 *   discount = som(discount) + een eventuele vaste korting op orderniveau
 *   total    = som(price)    - diezelfde vaste korting
 *
 * Wie `price` als prijs vóór korting leest, trekt de korting een tweede keer
 * af. Dat is precies wat het wijzigscherm deed.
 */
beforeEach(function () {
    Customsetting::set('taxes_prices_include_taxes', 1);
    $this->actingAs(User::factory()->create(['role' => 'superadmin']));
});

function netCode(float $percentage = 12.5): DiscountCode
{
    return DiscountCode::create([
        'site_ids' => [Sites::getActive()],
        'name' => 'Welkomstkorting',
        'code' => 'NET-' . strtoupper(uniqid()),
        'type' => 'percentage',
        'discount_percentage' => $percentage,
        'valid_for' => 'all',
        'use_stock' => 0,
    ]);
}

function netAmountCode(float $amount): DiscountCode
{
    return DiscountCode::create([
        'site_ids' => [Sites::getActive()],
        'name' => 'Vaste korting',
        'code' => 'NETVAST-' . strtoupper(uniqid()),
        'type' => 'amount',
        'discount_amount' => $amount,
        'use_stock' => 0,
    ]);
}

function netProduct(float $price): Product
{
    Queue::fake();

    $group = ProductGroup::create([
        'name' => ['en' => 'Groep'], 'slug' => ['en' => 'net-groep-' . uniqid()],
        'short_description' => ['en' => ''], 'description' => ['en' => ''],
        'content' => ['en' => ''], 'search_terms' => ['en' => ''],
        'site_ids' => ['site'],
    ]);

    return Product::withoutEvents(fn () => Product::create([
        'product_group_id' => $group->id,
        'name' => ['en' => 'Product'],
        'slug' => ['en' => 'net-' . uniqid()],
        'site_ids' => ['site'],
        'price' => $price, 'current_price' => $price, 'vat_rate' => 21,
        'use_stock' => false, 'stock' => 0,
        'images' => [],
    ]));
}

/**
 * @param  array<int, array<string, mixed>>  $lines
 */
function netOrder(?DiscountCode $code, array $lines, float $discount = 0.0): Order
{
    $order = new Order();
    $order->email = 'klant@example.com';
    $order->status = 'pending';
    $order->discount_code_id = $code?->id;
    $order->discount = $discount;
    $order->save();

    foreach ($lines as $line) {
        OrderProduct::create(array_merge([
            'order_id' => $order->id,
            'name' => 'Regel',
            'quantity' => 1,
            'vat_rate' => 21,
            'discount' => 0,
        ], $line));
    }

    return $order->fresh();
}

it('reproduceert de totalen van een order precies zoals de winkelwagen ze opslaat', function () {
    // Order 2843 uit productie: 12,5% code, drie regels waarvan de prijs de
    // kortingsprijs is. Zonder deze test is er geen ijkpunt voor de conventie.
    $order = netOrder(netCode(12.5), [
        ['price' => 7.83, 'discount' => 1.12],
        ['price' => 4.38, 'discount' => 0.62],
        ['price' => 33.21, 'discount' => 4.74],
    ], discount: 6.48);

    OrderTotalsCalculator::recalculate($order);

    expect(round($order->subtotal, 2))->toBe(51.90)
        ->and(round($order->discount, 2))->toBe(6.48)
        ->and(round($order->total, 2))->toBe(45.42)
        // 45,42 incl. 21% => 7,88. De btw hoort over wat de klant betaalt te
        // gaan, niet over het subtotaal vóór korting.
        ->and(round($order->btw, 2))->toBe(7.88);
});

it('past de kortingscode niet nog een keer toe op regels die hem al bevatten', function () {
    // De gemelde fout. Regels van order 2843, waarbij het beeldje voor een
    // goedkoper beeldje wordt omgeruild en er verzendkosten bij komen.
    // Som van de regelprijzen = 39,24 en dat hoort het nieuwe totaal te zijn.
    $waxinelicht = netProduct(8.95);
    $sleutelhanger = netProduct(5.00);
    $grootBeeldje = netProduct(37.95);
    $kleinBeeldje = netProduct(22.95);

    $order = netOrder(netCode(12.5), [
        ['price' => 7.83, 'discount' => 1.12, 'product_id' => $waxinelicht->id],
        ['price' => 4.38, 'discount' => 0.62, 'product_id' => $sleutelhanger->id],
        ['price' => 33.21, 'discount' => 4.74, 'product_id' => $grootBeeldje->id],
    ], discount: 6.48);

    $bron = $order->orderProducts->values();

    OrderModificationService::writeLines($order, [
        ['order_product_id' => $bron[0]->id, 'product_id' => $waxinelicht->id, 'name' => 'Waxinelichthouder', 'quantity' => 1, 'price' => 7.83, 'vat_rate' => 21],
        ['order_product_id' => $bron[1]->id, 'product_id' => $sleutelhanger->id, 'name' => 'Sleutelhanger', 'quantity' => 1, 'price' => 4.38, 'vat_rate' => 21],
        ['order_product_id' => $bron[2]->id, 'product_id' => $kleinBeeldje->id, 'name' => 'Klein beeldje', 'quantity' => 1, 'price' => 20.08, 'vat_rate' => 21],
        ['order_product_id' => null, 'product_id' => null, 'name' => 'Verzendkosten', 'quantity' => 1, 'price' => 6.95, 'vat_rate' => 21],
    ]);

    OrderTotalsCalculator::recalculate($order);

    expect(round($order->total, 2))->toBe(39.24)
        // De twee ongewijzigde regels houden hun eigen korting; het omgeruilde
        // beeldje en de nieuwe verzendregel hebben er geen.
        ->and(round($order->discount, 2))->toBe(1.74)
        ->and(round($order->subtotal, 2))->toBe(40.98);
});

it('laat de korting van een regel meeschalen met zijn prijs', function () {
    // Aantal van 1 naar 2 verdubbelt het regeltotaal, dus ook de korting die
    // in dat regeltotaal verwerkt zit. Bleef de korting staan, dan zou het
    // subtotaal 1,12 te laag uitkomen.
    $product = netProduct(8.95);

    $order = netOrder(netCode(12.5), [
        ['price' => 7.83, 'discount' => 1.12, 'product_id' => $product->id],
    ], discount: 1.12);

    $bron = $order->orderProducts->first();

    OrderModificationService::writeLines($order, [
        ['order_product_id' => $bron->id, 'product_id' => $product->id, 'name' => 'Waxinelichthouder', 'quantity' => 2, 'price' => 15.66, 'vat_rate' => 21],
    ]);

    OrderTotalsCalculator::recalculate($order);

    expect(round($order->total, 2))->toBe(15.66)
        ->and(round($order->discount, 2))->toBe(2.24)
        ->and(round($order->subtotal, 2))->toBe(17.90);
});

it('geeft een omgeruild product geen korting van zijn voorganger mee', function () {
    // Dezelfde regel, ander product: de korting hoorde bij het oude product en
    // heeft op het nieuwe geen betekenis meer.
    $oud = netProduct(37.95);
    $nieuw = netProduct(22.95);

    $order = netOrder(netCode(12.5), [
        ['price' => 33.21, 'discount' => 4.74, 'product_id' => $oud->id],
    ], discount: 4.74);

    $bron = $order->orderProducts->first();

    OrderModificationService::writeLines($order, [
        ['order_product_id' => $bron->id, 'product_id' => $nieuw->id, 'name' => 'Klein beeldje', 'quantity' => 1, 'price' => 20.08, 'vat_rate' => 21],
    ]);

    OrderTotalsCalculator::recalculate($order);

    expect(round($order->total, 2))->toBe(20.08)
        ->and(round($order->discount, 2))->toBe(0.0)
        ->and(round($order->subtotal, 2))->toBe(20.08);
});

it('trekt een vaste kortingscode wel van het totaal af', function () {
    // Een vast bedrag zit niet in de regelprijzen (zie
    // Product::getShoppingCartItemPrice(): alleen een procentuele code wordt
    // per regel verwerkt), dus die hoort er op orderniveau wél af te gaan.
    $order = netOrder(netAmountCode(15.0), [
        ['price' => 100.0, 'discount' => 0],
    ], discount: 15.0);

    OrderTotalsCalculator::recalculate($order);

    expect(round($order->subtotal, 2))->toBe(100.0)
        ->and(round($order->discount, 2))->toBe(15.0)
        ->and(round($order->total, 2))->toBe(85.0);
});

it('herberekent dezelfde totalen wanneer hij twee keer draait', function () {
    // recalculate() leest $order->discount terug voor de vaste korting op
    // orderniveau. Schreef hij daar de regelkortingen bij in, dan zou een
    // tweede run ze nog een keer aftrekken.
    $order = netOrder(netCode(12.5), [
        ['price' => 7.83, 'discount' => 1.12],
        ['price' => 33.21, 'discount' => 4.74],
    ], discount: 5.86);

    OrderTotalsCalculator::recalculate($order);
    $eerste = [$order->subtotal, $order->discount, $order->total, $order->btw];

    OrderTotalsCalculator::recalculate($order->fresh());

    expect([$order->fresh()->subtotal, $order->fresh()->discount, $order->fresh()->total, $order->fresh()->btw])
        ->toEqual($eerste);
});
