<?php

use Illuminate\Support\Str;
use Dashed\DashedCore\Models\User;
use Dashed\DashedCore\Classes\Sites;
use Illuminate\Support\Facades\Queue;
use Dashed\DashedCore\Models\Customsetting;
use Dashed\DashedEcommerceCore\Models\Cart;
use Dashed\DashedEcommerceCore\Classes\OssVat;
use Dashed\DashedEcommerceCore\Models\Product;
use Dashed\DashedEcommerceCore\Models\CartItem;
use Dashed\DashedEcommerceCore\Classes\CartHelper;
use Dashed\DashedEcommerceCore\Models\DiscountCode;
use Dashed\DashedEcommerceCore\Models\OrderProduct;
use Dashed\DashedEcommerceCore\Models\ProductGroup;
use Dashed\DashedEcommerceCore\Models\ShippingZone;
use Dashed\DashedEcommerceCore\Models\PaymentMethod;
use Dashed\DashedEcommerceCore\Models\ShippingMethod;
use Dashed\DashedEcommerceCore\Filament\Resources\OrderResource\Pages\CreateOrder;

beforeEach(function () {
    Queue::fake();
    OssVat::flush();

    CartHelper::$cart = null;
    CartHelper::$initialized = false;
    CartHelper::$cartItemsInitialized = false;
    CartHelper::$cartItems = [];
    CartHelper::$cartProductsById = [];
    CartHelper::$vatCountry = null;
    CartHelper::$vatCountryInitialized = false;
    CartHelper::$vatReverseCharge = false;
    CartHelper::$vatReverseChargeInitialized = false;
    CartHelper::$shippingMethod = null;
    CartHelper::$shippingZone = null;
    CartHelper::$paymentMethod = null;
    CartHelper::$allPaymentMethodsInitialized = false;

    request()->cookies->set(config('dashed-ecommerce.cart_cookie', 'cart_token'), (string) Str::uuid());

    Customsetting::set('company_country', 'Nederland');
    Customsetting::set('oss_enabled', '1');
    // OSS werkt alleen bij prijzen inclusief btw; de testdatabase staat op exclusief.
    Customsetting::set('taxes_prices_include_taxes', 1);
});

function ossCartProduct(float $price = 119.00): Product
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
        'current_price' => $price,
        'price' => $price,
        'vat_rate' => 21,
        'public' => 1,
    ]));
}

function ossShippingMethod(string $country, float $costs, string $sort = 'static_amount'): ShippingMethod
{
    $zone = ShippingZone::create([
        'site_id' => Sites::getActive(),
        'name' => ['nl' => $country],
        'zones' => [$country],
        'search_fields' => $country,
    ]);

    return ShippingMethod::create([
        'shipping_zone_id' => $zone->id,
        'name' => ['nl' => $sort === 'take_away' ? 'Ophalen' : 'Verzenden'],
        'costs' => $costs,
        'sort' => $sort,
        'minimum_order_value' => 0,
        'maximum_order_value' => 100000,
        'order' => 1,
    ]);
}

/**
 * Een wagen met één product, rechtstreeks via cartHelper() opgebouwd zoals de
 * checkout dat doet in fillPrices().
 */
function ossCart(Product $product, string $country, ?ShippingMethod $shippingMethod = null, ?PaymentMethod $paymentMethod = null, bool $reverseCharge = false): CartHelper
{
    $cart = Cart::create([
        'token' => request()->cookies->get(config('dashed-ecommerce.cart_cookie', 'cart_token')),
        'type' => 'default',
    ]);
    CartItem::create([
        'cart_id' => $cart->id,
        'product_id' => $product->id,
        'name' => 'Vaas',
        'unit_price' => $product->price,
        'quantity' => 1,
        'options' => [],
        'options_hash' => '',
    ]);

    $helper = cartHelper();
    $helper->initialize('default');
    $helper->setShippingMethod($shippingMethod?->id);
    $helper->setShippingZone($shippingMethod?->shipping_zone_id);
    $helper->setPaymentMethod($paymentMethod?->id);
    $helper->setVatCountry($country);
    $helper->setVatReverseCharge($reverseCharge);
    $helper->updateData();

    return $helper;
}

/**
 * De regels moeten samen precies de order-btw geven.
 */
function ossExpectLinesMatchOrder($order): void
{
    $lines = OrderProduct::where('order_id', $order->id)->get();

    expect(round($lines->sum(fn ($line) => (float) $line->btw), 2))->toBe(round((float) $order->btw, 2));
}

function ossManualOrder(Product $product, string $country, ?string $discountCode = null, ?ShippingMethod $shippingMethod = null): array
{
    $page = new CreateOrder();
    $page->cartInstance = 'handorder';
    $page->orderOrigin = 'own';
    $page->products = [['id' => $product->id, 'quantity' => 1, 'extra' => []]];
    $page->email = 'klant@example.com';
    $page->last_name = 'Klant';
    $page->country = $country;
    $page->discount_code = $discountCode;
    $page->shipping_method_id = $shippingMethod?->id;

    return $page->createOrder();
}

it('rekent een order naar Duitsland met 19% en dezelfde prijs', function () {
    $this->actingAs(User::factory()->create(['role' => 'admin']));
    $product = ossCartProduct(119.00);

    $response = ossManualOrder($product, 'Duitsland');

    expect($response['success'])->toBeTrue();
    $order = $response['order']->fresh();
    $line = OrderProduct::where('order_id', $order->id)->where('product_id', $product->id)->first();

    expect((float) $order->total)->toBe(119.00);
    expect(round((float) $order->btw, 2))->toBe(19.00);
    expect(array_map('floatval', $order->vat_percentages))->toEqual(['19' => 19.00]);
    expect((float) $line->vat_rate)->toBe(19.0);
    expect((float) $line->price)->toBe(119.00);
});

it('rekent een order naar Nederland met 21%', function () {
    $this->actingAs(User::factory()->create(['role' => 'admin']));
    $product = ossCartProduct(121.00);

    $order = ossManualOrder($product, 'Nederland')['order']->fresh();

    expect(round((float) $order->btw, 2))->toBe(21.00);
    expect(array_map('floatval', $order->vat_percentages))->toEqual(['21' => 21.00]);
});

it('rekent naar Duitsland met 21% als de schakelaar uit staat', function () {
    Customsetting::set('oss_enabled', '0');
    $this->actingAs(User::factory()->create(['role' => 'admin']));
    $product = ossCartProduct(121.00);

    $order = ossManualOrder($product, 'Duitsland')['order']->fresh();

    expect(round((float) $order->btw, 2))->toBe(21.00);
});

it('houdt bij een bedragkorting de btw-uitsplitsing gelijk aan de order-btw', function () {
    $this->actingAs(User::factory()->create(['role' => 'admin']));
    $product = ossCartProduct(119.00);

    $helper = cartHelper();
    $helper->initialize('handorder');
    $helper->setVatCountry('Duitsland');

    expect($helper->ossCountryCode())->toBe('DE');
    expect($helper->vatRateFor(21))->toBe(19.0);
    expect($helper->vatRateFor(9))->toBe(9.0);

    $helper->setVatCountry('Nederland');
    expect($helper->ossCountryCode())->toBeNull();
    expect($helper->vatRateFor(21))->toBe(21.0);

    DiscountCode::create([
        'site_ids' => [Sites::getActive()],
        'name' => 'Test',
        'code' => 'OSS1190',
        'type' => 'amount',
        'discount_amount' => 11.90,
        'use_stock' => 0,
        'is_giftcard' => 0,
        'is_global_discount' => false,
    ]);

    $order = ossManualOrder($product, 'Duitsland', 'OSS1190')['order']->fresh();

    // 119,00 - 11,90 = 107,10 inclusief 19%: 107,10 / 119 * 19 = 17,10.
    expect((float) $order->total)->toBe(107.10);
    expect(round((float) $order->btw, 2))->toBe(17.10);
    expect(array_keys($order->vat_percentages))->toEqual([19]);
    expect(round(array_sum($order->vat_percentages), 2))->toBe(round((float) $order->btw, 2));
});

it('rekent de verzendkosten naar Duitsland met 19% onder sleutel 19', function () {
    $this->actingAs(User::factory()->create(['role' => 'admin']));
    $product = ossCartProduct(119.00);
    $shippingMethod = ossShippingMethod('Duitsland', 11.90);

    $helper = ossCart($product, 'Duitsland', $shippingMethod);

    // Product 119,00 en verzending 11,90, allebei inclusief 19%.
    expect($helper->getVatRateForShippingMethod())->toBe(19.0);
    expect($helper->getVatForShippingMethod())->toBe(1.90);
    expect($helper->getTax())->toBe(20.90);
    expect($helper->getTotal())->toBe(130.90);
    expect($helper->getTaxPercentages())->toEqual([19 => 20.90]);

    $order = ossManualOrder($product, 'Duitsland', shippingMethod: $shippingMethod)['order']->fresh();
    $shippingLine = OrderProduct::where('order_id', $order->id)->where('sku', 'shipping_costs')->first();

    expect((float) $shippingLine->vat_rate)->toBe(19.0);
    expect(round((float) $shippingLine->btw, 2))->toBe(1.90);
    expect(round((float) $order->btw, 2))->toBe(20.90);
    expect(array_map('floatval', $order->vat_percentages))->toEqual(['19' => 20.90]);
    ossExpectLinesMatchOrder($order);
});

it('rekent bij ophalen alles met 21%, ook naar Duitsland', function () {
    $this->actingAs(User::factory()->create(['role' => 'admin']));
    $product = ossCartProduct(121.00);
    $pickUp = ossShippingMethod('Duitsland', 12.10, 'take_away');

    $helper = ossCart($product, 'Duitsland', $pickUp);

    expect($helper->ossCountryCode())->toBeNull();
    expect($helper->getVatRateForShippingMethod())->toBe(21.0);
    expect($helper->getVatForShippingMethod())->toBe(2.10);
    expect($helper->getTax())->toBe(23.10);
    expect($helper->getTaxPercentages())->toEqual([21 => 23.10]);

    // Wisselen zonder updateData(): de btw-basis moet het nieuwe tarief volgen.
    // Verzenden naar Duitsland: 121,00 / 119 * 19 = 19,32 plus 11,90 / 119 * 19 = 1,90.
    $helper->setShippingMethod(ossShippingMethod('Duitsland', 11.90)->id);
    expect($helper->getTax())->toBe(21.22);
    $helper->setShippingMethod($pickUp->id);
    expect($helper->getTax())->toBe(23.10);
    $helper->setShippingMethod(ossShippingMethod('Duitsland', 11.90)->id);
    $helper->setVatReverseCharge(true);
    expect($helper->getTax())->toBe(0.0);
    $helper->setVatReverseCharge(false);
    expect($helper->getTax())->toBe(21.22);

    $order = ossManualOrder($product, 'Duitsland', shippingMethod: $pickUp)['order']->fresh();
    $shippingLine = OrderProduct::where('order_id', $order->id)->where('sku', 'shipping_costs')->first();
    $productLine = OrderProduct::where('order_id', $order->id)->where('product_id', $product->id)->first();

    expect((float) $productLine->vat_rate)->toBe(21.0);
    expect((float) $shippingLine->vat_rate)->toBe(21.0);
    expect(round((float) $order->btw, 2))->toBe(23.10);
    ossExpectLinesMatchOrder($order);
});

it('rekent betaalkosten naar Duitsland met 19% onder sleutel 19', function () {
    $product = ossCartProduct(119.00);
    $paymentMethod = PaymentMethod::create([
        'site_id' => Sites::getActive(),
        'name' => ['nl' => 'Achteraf betalen'],
        'type' => 'online',
        'active' => 1,
        'psp' => 'own',
        'available_from_amount' => 0,
        'extra_costs' => 2.38,
    ]);

    $helper = ossCart($product, 'Duitsland', paymentMethod: $paymentMethod);

    // Betaalkosten 2,38 inclusief 19% = 0,38 btw.
    expect($helper->getPaymentCosts())->toBe(2.38);
    expect($helper->getVatForPaymentMethod())->toBe(0.38);
    expect($helper->getTax())->toBe(19.38);
    expect($helper->getTaxPercentages())->toEqual([19 => 19.38]);
});

it('rekent naar Finland met 25,5% onder sleutel 25.5', function () {
    $this->actingAs(User::factory()->create(['role' => 'admin']));
    $product = ossCartProduct(125.50);
    $shippingMethod = ossShippingMethod('Finland', 12.55);

    $helper = ossCart($product, 'Finland', $shippingMethod);

    // 125,50 / 125,5 * 25,5 = 25,50 en 12,55 / 125,5 * 25,5 = 2,55.
    expect($helper->ossCountryCode())->toBe('FI');
    expect($helper->getVatRateForShippingMethod())->toBe(25.5);
    expect($helper->getVatForShippingMethod())->toBe(2.55);
    expect($helper->getTax())->toBe(28.05);
    expect($helper->getTaxPercentages())->toEqual(['25.5' => 28.05]);

    $order = ossManualOrder($product, 'Finland', shippingMethod: $shippingMethod)['order']->fresh();
    $productLine = OrderProduct::where('order_id', $order->id)->where('product_id', $product->id)->first();
    $shippingLine = OrderProduct::where('order_id', $order->id)->where('sku', 'shipping_costs')->first();

    expect((float) $productLine->vat_rate)->toBe(25.5);
    expect((float) $shippingLine->vat_rate)->toBe(25.5);
    expect(round((float) $order->btw, 2))->toBe(28.05);
    expect(array_map('floatval', $order->vat_percentages))->toEqual(['25.5' => 28.05]);
    ossExpectLinesMatchOrder($order);
});

it('rekent bij verlegde btw geen OSS, ook naar Duitsland', function () {
    $product = ossCartProduct(119.00);

    $helper = ossCart($product, 'Duitsland', reverseCharge: true);

    expect($helper->ossCountryCode())->toBeNull();
    expect($helper->vatRateFor(21.0))->toBe(21.0);
    expect($helper->getTax())->toBe(0.0);
    expect($helper->getTaxPercentages())->toBe([]);
});
