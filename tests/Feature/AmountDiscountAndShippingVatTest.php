<?php

require_once __DIR__ . '/../Support/InvoiceHarness.php';

use Dashed\DashedCore\Classes\Sites;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Queue;
use Dashed\DashedCore\Models\Customsetting;
use Dashed\DashedEcommerceCore\Models\Cart;
use Dashed\DashedEcommerceCore\Models\Order;
use Dashed\DashedEcommerceCore\Models\POSCart;
use Dashed\DashedEcommerceCore\Models\Product;
use Dashed\DashedEcommerceCore\Models\CartItem;
use Dashed\DashedEcommerceCore\Classes\CartHelper;
use Dashed\DashedEcommerceCore\Models\DiscountCode;
use Dashed\DashedEcommerceCore\Models\OrderProduct;
use Dashed\DashedEcommerceCore\Models\ProductGroup;
use Dashed\DashedEcommerceCore\Models\ShippingZone;
use Dashed\DashedEcommerceCore\Models\ShippingMethod;
use Dashed\DashedEcommerceCore\Classes\OrderTotalsCalculator;
use Dashed\DashedEcommerceCore\Classes\InvoiceExport\VatBreakdown;
use Dashed\DashedEcommerceCore\Controllers\Api\PointOfSale\PointOfSaleApiController;

/*
 * Vaste korting en promobon: één bedrag, 21% btw eraf. Verkochte bon:
 * betaalmiddel. Verzendkosten-btw: naar verhouding van de omzet per tarief.
 * Btw-totaal = som van de afgeronde bedragen per tarief.
 */

beforeEach(function () {
    Mail::fake();
    Queue::fake();
    $this->actingAs(\Dashed\DashedCore\Models\User::factory()->create());
    Customsetting::set('taxes_prices_include_taxes', 1);

    CartHelper::$cart = null;
    CartHelper::$cartItemsInitialized = false;
    CartHelper::$cartItems = [];
    CartHelper::$cartProductsById = [];
});

function advCode(float $amount, string $kind = 'korting'): DiscountCode
{
    return DiscountCode::create(array_filter([
        'site_ids' => [Sites::getActive()],
        'name' => $kind,
        'code' => 'C-' . strtoupper(uniqid()),
        'is_giftcard' => $kind === 'korting' ? 0 : 1,
        'giftcard_type' => $kind === 'verkocht' ? 'verkocht' : null,
        'type' => 'amount',
        'discount_amount' => $amount,
        'use_stock' => 0,
    ], fn ($value) => $value !== null));
}

function advProduct(float $price, int $vatRate): Product
{
    $group = ProductGroup::create([
        'name' => ['nl' => 'Groep'],
        'slug' => ['nl' => 'groep-' . uniqid()],
        'short_description' => ['nl' => ''], 'description' => ['nl' => ''],
        'content' => ['nl' => ''], 'search_terms' => ['nl' => ''],
        'site_ids' => [Sites::getActive()],
    ]);

    return Product::withoutEvents(fn () => Product::create([
        'product_group_id' => $group->id,
        'name' => ['nl' => 'Product ' . $vatRate],
        'slug' => ['nl' => 'product-' . uniqid()],
        'site_ids' => [Sites::getActive()],
        'price' => $price, 'current_price' => $price, 'vat_rate' => $vatRate,
        'use_stock' => 0, 'in_stock' => 1, 'stock_status' => 'in_stock',
        'total_stock' => 0, 'public' => 1, 'images' => [],
    ]));
}

function advShipping(float $costs): ShippingMethod
{
    $zone = ShippingZone::create([
        'site_id' => Sites::getActive(), 'name' => ['nl' => 'NL'],
        'zones' => ['Nederland'], 'search_fields' => 'Nederland',
    ]);

    return ShippingMethod::create([
        'shipping_zone_id' => $zone->id, 'name' => ['nl' => 'PostNL'], 'costs' => $costs,
        'sort' => 'static_amount', 'minimum_order_value' => 0, 'maximum_order_value' => 100000, 'order' => 1,
    ]);
}

function advCart(array $products, ?DiscountCode $code = null, ?ShippingMethod $shipping = null): CartHelper
{
    $token = (string) \Illuminate\Support\Str::uuid();
    request()->cookies->set(config('dashed-ecommerce.cart_cookie', 'cart_token'), $token);

    $cart = Cart::create(['token' => $token, 'type' => 'default', 'discount_code_id' => $code?->id]);

    if ($shipping) {
        $cart->shipping_method_id = $shipping->id;
        $cart->shipping_zone_id = $shipping->shipping_zone_id;
        $cart->save();
    }

    foreach ($products as $product) {
        CartItem::create([
            'cart_id' => $cart->id, 'product_id' => $product->id, 'name' => 'Regel',
            'unit_price' => $product->price, 'quantity' => 1, 'options' => [], 'options_hash' => md5(uniqid()),
        ]);
    }

    $helper = new CartHelper();
    $helper->updateData();

    return $helper;
}

function advPosCart(array $lines, array $giftCards = []): POSCart
{
    $cart = new POSCart();
    $cart->user_id = auth()->id();
    $cart->status = 'active';
    $cart->identifier = uniqid();
    $cart->products = array_map(fn (array $line, int $i) => [
        'identifier' => 'regel-' . $i, 'id' => $line[0]->id, 'name' => 'Product',
        'quantity' => 1, 'singlePrice' => $line[1], 'price' => $line[1], 'vat_rate' => $line[2],
    ], $lines, array_keys($lines));
    $cart->applied_gift_cards = array_map(fn (DiscountCode $card) => [
        'code' => $card->code, 'discount_code_id' => $card->id, 'balance' => (float) $card->discount_amount,
    ], $giftCards);
    $cart->save();

    return $cart;
}

/** Order met regels 109 (9%), 121 (21%) en 10 verzendkosten, en een vaste korting van 23. */
function advOrder(?int $vatCalculation = Order::VAT_CALCULATION): Order
{
    $order = Order::create([
        'email' => 'a@b.nl', 'status' => 'paid', 'invoice_id' => 'INV-' . uniqid(), 'fulfillment_status' => 'handled',
        'subtotal' => 240, 'discount' => 23, 'total' => 217, 'btw' => 0, 'vat_percentages' => [],
    ]);

    foreach ([['A', 109.0, 9, 'A'], ['B', 121.0, 21, 'B'], ['PostNL', 10.0, 15, 'shipping_costs']] as [$name, $price, $rate, $sku]) {
        OrderProduct::withoutEvents(fn () => OrderProduct::create([
            'order_id' => $order->id, 'name' => $name, 'quantity' => 1, 'price' => $price, 'vat_rate' => $rate, 'sku' => $sku, 'discount' => 0,
        ]));
    }

    DB::table('dashed__orders')->where('id', $order->id)->update(['vat_calculation' => $vatCalculation]);

    return $order->fresh(['orderProducts']);
}

// ---------------------------------------------------------------- webshop

it('webshop: rekent een vaste korting met 21% weg, de 9%-btw blijft staan', function () {
    $helper = advCart([advProduct(109.0, 9), advProduct(121.0, 21)], advCode(23.0));
    $percentages = $helper->getTaxPercentages();

    expect(round((float) $percentages['9'], 2))->toBe(9.0)
        // 21 - 23/121*21 = 21 - 3,99
        ->and(round((float) $percentages['21'], 2))->toBe(17.01)
        ->and($helper->getTax())->toBe(26.01)
        ->and(round(array_sum($percentages), 2))->toBe($helper->getTax())
        ->and($helper->getTotal())->toBe(207.0);
});

it('webshop: rekent een promobon met 21% weg, ook als alles 9% is', function () {
    $helper = advCart([advProduct(109.0, 9)], advCode(50.0, 'promo'));
    $percentages = $helper->getTaxPercentages();

    expect($helper->getTax())->toBe(0.32)
        ->and(round((float) $percentages['9'], 2))->toBe(9.0)
        ->and(round((float) $percentages['21'], 2))->toBe(-8.68);
});

it('webshop: rekent alleen het gebruikte deel van een te grote promobon weg', function () {
    $helper = advCart([advProduct(121.0, 21)], advCode(500.0, 'promo'));

    // Gebruikt is 120,99 (setDiscount laat 1 cent staan): 21,00 - 21,00 = 0
    expect($helper->getTax())->toBe(0.0);
});

it('webshop: laat een negatieve btw staan', function () {
    $helper = advCart([advProduct(21.80, 9)], advCode(20.0));

    expect($helper->getTax())->toBe(-1.67)
        ->and(round((float) $helper->getTaxPercentages()['21'], 2))->toBe(-3.47);
});

it('webshop: prijzen exclusief btw rekenen de korting niet dubbel van het totaal af', function () {
    Customsetting::set('taxes_prices_include_taxes', 0);

    $helper = advCart([advProduct(100.0, 9)], advCode(12.10));

    // 100 + 9 btw = 109; korting 12,10 incl. 2,10 btw -> totaal 96,90, btw 6,90
    expect($helper->getTax())->toBe(6.9)
        ->and($helper->getTotal())->toBe(96.9);
});

it('webshop: verdeelt de verzendkosten-btw naar verhouding van de omzet per tarief', function () {
    $helper = advCart([advProduct(109.0, 9), advProduct(121.0, 21)], null, advShipping(10.0));
    $percentages = $helper->getTaxPercentages();

    // 10 * 109/230 tegen 9% = 0,39; 10 * 121/230 tegen 21% = 0,91
    expect(round((float) $percentages['9'], 2))->toBe(9.39)
        ->and(round((float) $percentages['21'], 2))->toBe(21.91)
        ->and($helper->getTax())->toBe(31.3)
        ->and($helper->getVatForShippingMethod())->toBe(1.3)
        ->and($helper->getTotal())->toBe(240.0);
});

it('webshop: verzendkosten en vaste korting samen tellen exact op', function () {
    $helper = advCart([advProduct(109.0, 9), advProduct(121.0, 21)], advCode(24.20), advShipping(10.0));
    $percentages = $helper->getTaxPercentages();

    expect(round((float) $percentages['9'], 2))->toBe(9.39)
        ->and(round((float) $percentages['21'], 2))->toBe(17.71)
        ->and($helper->getTax())->toBe(27.1)
        ->and(round(array_sum($percentages), 2))->toBe($helper->getTax())
        ->and($helper->getTotal())->toBe(215.8);
});

// ---------------------------------------------------------------- kassa

it('kassa: zet een vaste korting als één bedrag en rekent 21% btw weg', function () {
    $product = advProduct(109.0, 9);
    $totals = (new PointOfSaleApiController())->calculatePosCartTotals(advPosCart([[$product, 109.0, 9]]), collect([advCode(12.10)]));

    expect((float) $totals['lines'][0]['line_total'])->toBe(109.0)
        ->and((float) $totals['subtotal'])->toBe(96.9)
        ->and((float) $totals['discount'])->toBe(12.1)
        ->and((float) $totals['vat'])->toBe(6.9)
        ->and($totals['vatPercentages'])->toEqual(['9' => 9.0, '21' => -2.1]);
});

it('kassa: een promobon haalt 21% btw eraf, een verkochte bon niet', function () {
    $product = advProduct(109.0, 9);

    $promo = (new PointOfSaleApiController())->calculatePosCartTotals(advPosCart([[$product, 109.0, 9]], [advCode(50.0, 'promo')]));
    $sold = (new PointOfSaleApiController())->calculatePosCartTotals(advPosCart([[$product, 109.0, 9]], [advCode(50.0, 'verkocht')]));

    expect((float) $promo['vat'])->toBe(9.0)
        ->and((float) $promo['giftCardsTotal'])->toBe(50.0)
        ->and((float) $promo['giftCardsVat'])->toBe(8.68)
        ->and((float) $sold['giftCardsTotal'])->toBe(50.0)
        ->and((float) $sold['giftCardsVat'])->toBe(0.0);
});

// ---------------------------------------------------------------- order wijzigen

it('order wijzigen: verzendregel naar verhouding, korting met 21% eraf', function () {
    $order = advOrder();

    OrderTotalsCalculator::recalculate($order);
    $order->refresh();

    // 9: 9,00 + 0,39; 21: 21,00 + 0,91 - 3,99
    expect($order->vat_percentages)->toEqual(['9' => 9.39, '21' => 17.92])
        ->and(round((float) $order->btw, 2))->toBe(27.31)
        ->and(round((float) $order->total, 2))->toBe(217.0)
        ->and((int) $order->vat_calculation)->toBe(Order::VAT_CALCULATION);
});

it('order wijzigen: een verkochte bon als korting op orderniveau raakt de btw niet', function () {
    $order = Order::create(['email' => 'a@b.nl', 'status' => 'pending', 'discount' => 50.0, 'discount_code_id' => advCode(50.0, 'verkocht')->id]);
    OrderProduct::create(['order_id' => $order->id, 'name' => 'A', 'quantity' => 1, 'price' => 109.0, 'vat_rate' => 9]);

    OrderTotalsCalculator::recalculate($order->fresh());

    expect(round((float) $order->fresh()->btw, 2))->toBe(9.0);
});

// ---------------------------------------------------------------- verzamelfactuur

it('verzamelfactuur: nieuwe order volgt dezelfde verdeling als de order zelf', function () {
    $order = advOrder();

    $perRate = VatBreakdown::inclPerRate($order);

    // 9: 109 + 4,74 verzend; 21: 121 + 5,26 verzend - 23 korting
    expect(round($perRate['9'], 2))->toBe(113.74)
        ->and(round($perRate['21'], 2))->toBe(103.26)
        ->and(array_keys($perRate))->not->toContain('15')
        ->and(VatBreakdown::vatFromIncl($perRate['9'], 9))->toBe(9.39)
        ->and(VatBreakdown::vatFromIncl($perRate['21'], 21))->toBe(17.92);
});

it('verzamelfactuur: een order van vóór de update houdt de oude verdeling', function () {
    $order = advOrder(null);

    $perRate = VatBreakdown::inclPerRate($order);

    // Oud: verzendregel tegen zijn eigen tarief, korting evenredig geschaald.
    expect($perRate)->toHaveKey('15')
        ->and(round(array_sum($perRate), 2))->toBe(217.0)
        ->and(round($perRate['9'], 2))->toBe(round(109 * 217 / 240, 2));
});

it('rapportage: de btw in een vaste korting is 21% van die korting', function () {
    expect(advOrder()->discountTax)->toBe(3.99)
        // oud: naar verhouding, 23 * 109/230 tegen 9% + 23 * 121/230 tegen 21%
        ->and(advOrder(null)->discountTax)->toBe(round(10.9 / 109 * 9 + 12.1 / 121 * 21, 2));
});

// ---------------------------------------------------------------- creditorder

function advCredit(Order $order): Order
{
    bootInvoiceHarness();

    $lines = $order->orderProducts->map(fn ($line) => $line->setAttribute('refundQuantity', 1))->all();

    return $order->markAsCancelledWithCredit(
        sendCustomerEmail: false,
        productsMustBeReturned: false,
        restock: false,
        refundDiscountCosts: true,
        extraOrderLineName: '',
        extraOrderLinePrice: 0,
        chosenOrderProducts: $lines,
        fulfillmentStatus: $order->fulfillment_status,
        paymentMethodId: null,
        sendAdminEmail: false,
        refillGiftcard: false,
    );
}

it('creditorder: spiegelt de verzend- en kortingsbtw van een nieuwe order', function () {
    $credit = advCredit(advOrder());

    expect($credit->vat_percentages)->toEqual(['9' => -9.39, '21' => -17.92])
        ->and(round((float) $credit->btw, 2))->toBe(-27.31)
        ->and(round((float) $credit->total, 2))->toBe(-217.0)
        ->and((int) $credit->vat_calculation)->toBe(Order::VAT_CALCULATION);
});

it('creditorder: een order van vóór de update wordt op de oude manier teruggedraaid', function () {
    $credit = advCredit(advOrder(null));

    // korting naar verhouding: 0,90 tegen 9%, 2,10 tegen 21%; verzendregel tegen 15%
    expect($credit->vat_percentages)->toEqual(['9' => -8.1, '21' => -18.9, '15' => -1.3])
        ->and($credit->vat_calculation)->toBeNull();
});

// ---------------------------------------------------------------- verkochte bon in de kassa

it('kassa: voegt een verkochte bon en een promobon niet samen en boekt beide af', function () {
    $product = advProduct(109.0, 9);
    $promo = advCode(20.0, 'promo');
    $sold = advCode(30.0, 'verkocht');

    $response = app(PointOfSaleApiController::class)->createOrder('pos', advPosCart([[$product, 109.0, 9]], [$promo, $sold]), null, 'pos', auth()->id());

    expect($response['success'] ?? false)->toBeTrue();

    $order = Order::latest('id')->first();

    expect(collect($order->applied_gift_cards)->pluck('discount_code_id')->all())->toEqual([$promo->id, $sold->id])
        ->and(round((float) $promo->fresh()->discount_amount, 2))->toBe(0.0)
        ->and(round((float) $sold->fresh()->discount_amount, 2))->toBe(0.0)
        ->and(round((float) $order->total, 2))->toBe(59.0)
        // 9,00 productbtw, alleen de promobon haalt 20/121*21 = 3,47 eraf
        ->and($order->vat_percentages)->toEqual(['9' => 9.0, '21' => -3.47])
        ->and(round((float) $order->btw, 2))->toBe(5.53);
});

it('verzamelfactuur: een in de kassa verzilverde verkochte bon blijft omzet en is geen korting', function () {
    $sold = advCode(50.0, 'verkocht');

    $order = Order::create([
        'email' => 'a@b.nl', 'status' => 'paid', 'order_origin' => 'pos', 'invoice_id' => 'INV-' . uniqid(),
        'subtotal' => 109, 'discount' => 50, 'total' => 59, 'btw' => 9, 'vat_percentages' => ['9' => 9.0],
        'discount_code_id' => $sold->id,
        'applied_gift_cards' => [['code' => $sold->code, 'discount_code_id' => $sold->id, 'redeemed' => 50.0]],
    ]);
    OrderProduct::withoutEvents(fn () => OrderProduct::create([
        'order_id' => $order->id, 'name' => 'A', 'quantity' => 1, 'price' => 109.0, 'vat_rate' => 9, 'sku' => 'A', 'discount' => 0,
    ]));
    $order = $order->fresh(['orderProducts']);

    $result = VatBreakdown::calculate([$order]);

    expect(VatBreakdown::inclPerRate($order))->toEqual(['9' => 109.0])
        ->and((float) $result['discount'])->toBe(0.0)
        ->and((float) $result['redeemedGiftcards'])->toBe(50.0);
});
