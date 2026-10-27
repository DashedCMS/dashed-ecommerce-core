<?php

declare(strict_types=1);

require_once __DIR__ . '/../../Support/InvoiceHarness.php';

use Illuminate\Support\Str;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Validator;
use Dashed\DashedCore\Classes\Sites;
use Dashed\DashedEcommerceCore\Models\Cart;
use Dashed\DashedCore\Models\Customsetting;
use Dashed\DashedEcommerceCore\Models\Order;
use Dashed\DashedEcommerceCore\Models\Product;
use Dashed\DashedEcommerceCore\Models\CartItem;
use Dashed\DashedEcommerceCore\Models\PaymentMethod;
use Dashed\DashedEcommerceCore\Classes\CartHelper;
use Dashed\DashedEcommerceCore\Models\ProductGroup;
use Dashed\DashedEcommerceCore\Models\ShippingZone;
use Dashed\DashedEcommerceCore\Classes\ShoppingCart;
use Dashed\DashedEcommerceCore\Models\ShippingMethod;
use Dashed\DashedEcommerceCore\Livewire\Frontend\Checkout\Checkout;

/**
 * Een wagen met alleen digitale producten (een digitale cadeaubon) vraagt
 * geen verzendmethode en geen afleveradres. Een gekochte bon telt nooit mee
 * voor de orderwaarde waarop verzendmethodes gekozen worden.
 */
beforeEach(function () {
    Customsetting::set('taxes_prices_include_taxes', 1);
    Bus::fake();
    Mail::fake();
    ShoppingCart::resolveShippingOrderValueUsing(null);
});

afterEach(fn () => ShoppingCart::resolveShippingOrderValueUsing(null));

class DigitalCheckoutProbe extends Checkout
{
    /** mount() draait niet buiten Livewire; de formulierinstellingen hier. */
    public function __construct()
    {
        $this->accountRequired = 2;
        $this->firstAndLastnameRequired = 0;
        $this->companyRequired = 2;
        $this->phoneNumberRequired = 0;
    }

    public function __get($property)
    {
        if ($property === 'cartItems') {
            return $this->getCartItemsProperty();
        }

        return parent::__get($property);
    }
}

function verplicht(Checkout $probe, string $veld): bool
{
    return Validator::make([$veld => null], [$veld => $probe->rules()[$veld]])->fails();
}

function digitaalProduct(float $prijs, bool $digitaal, bool $bon): Product
{
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
        'name' => ['nl' => $bon ? 'Cadeaubon' : 'Vaas'],
        'slug' => ['nl' => 'product-' . uniqid()],
        'site_ids' => [Sites::getActive()],
        'product_group_id' => $group->id,
        'use_stock' => 0,
        'stock' => 100,
        'total_stock' => 100,
        'in_stock' => 1,
        'stock_status' => 'in_stock',
        'price' => $prijs,
        'current_price' => $prijs,
        'vat_rate' => $bon ? 0 : 21,
        'public' => 1,
        'is_digital' => $digitaal,
        'is_giftcard_product' => $bon,
    ]));
}

function digitaleWagen(Product ...$products): Cart
{
    $token = (string) Str::uuid();
    request()->cookies->set(config('dashed-ecommerce.cart_cookie', 'cart_token'), $token);

    $cart = Cart::create(['token' => $token, 'type' => 'default']);
    foreach ($products as $product) {
        CartItem::create([
            'cart_id' => $cart->id,
            'product_id' => $product->id,
            'name' => 'Product',
            'unit_price' => $product->price,
            'quantity' => 1,
            'options' => [],
            'options_hash' => '',
        ]);
    }

    CartHelper::$cart = null;
    CartHelper::$cartItemsInitialized = false;
    CartHelper::$cartItems = [];
    CartHelper::$cartProductsById = [];
    CartHelper::$shippingMethod = null;
    CartHelper::$calculateInclusiveTax = true;
    cartHelper()->updateData();

    return $cart;
}

function digitaleZone(): ShippingZone
{
    $zone = ShippingZone::create([
        'site_id' => Sites::getActive(),
        'name' => ['nl' => 'Nederland'],
        'zones' => ['Nederland'],
        'search_fields' => 'Nederland',
    ]);

    foreach ([['Klein', 0, 20], ['Normaal', 20, 100000]] as [$naam, $min, $max]) {
        ShippingMethod::create([
            'shipping_zone_id' => $zone->id,
            'name' => ['nl' => $naam],
            'costs' => 5,
            'sort' => 'static_amount',
            'minimum_order_value' => $min,
            'maximum_order_value' => $max,
            'order' => 1,
        ]);
    }

    return $zone;
}

test('cart with only digital products needs no shipping', function () {
    digitaleWagen(digitaalProduct(50, true, true), digitaalProduct(25, true, true));

    expect(cartHelper()->requiresShipping())->toBeFalse();
});

test('mixed cart requires shipping', function () {
    digitaleWagen(digitaalProduct(50, true, true), digitaalProduct(15, false, false));

    expect(cartHelper()->requiresShipping())->toBeTrue();
});

test('a giftcard sent by post requires shipping', function () {
    digitaleWagen(digitaalProduct(50, false, true));

    expect(cartHelper()->requiresShipping())->toBeTrue();
});

test('giftcard lines do not count for shipping order value', function () {
    digitaleZone();
    digitaleWagen(digitaalProduct(50, true, true), digitaalProduct(15, false, false));

    $ontvangen = null;
    ShoppingCart::resolveShippingOrderValueUsing(function (float $totaal) use (&$ontvangen): float {
        $ontvangen = $totaal;

        return $totaal;
    });

    $namen = collect(ShoppingCart::getAvailableShippingMethods('Nederland'))
        ->map(fn ($m) => $m->getTranslation('name', 'nl'))
        ->values()
        ->all();

    expect($namen)->toBe(['Klein'])
        ->and($ontvangen)->toBe(15.0);
});

test('digital-only checkout has no shipping method and no address requirements', function () {
    digitaleZone();
    digitaleWagen(digitaalProduct(50, true, true));

    $probe = new DigitalCheckoutProbe();
    $probe->country = 'Nederland';
    $probe->shippingMethod = ShippingMethod::first()->id;
    $probe->fillPrices();

    expect($probe->requiresShipping)->toBeFalse()
        ->and($probe->shippingMethod)->toBeNull()
        ->and(collect($probe->shippingMethods)->all())->toBe([])
        ->and((float) $probe->shippingCosts)->toBe(0.0)
        ->and($probe->country)->toBe('Nederland')
        ->and(verplicht($probe, 'shippingMethod'))->toBeFalse()
        ->and(verplicht($probe, 'street'))->toBeFalse()
        ->and(verplicht($probe, 'houseNr'))->toBeFalse()
        ->and(verplicht($probe, 'zipCode'))->toBeFalse()
        ->and(verplicht($probe, 'city'))->toBeFalse();
});

test('digital-only checkout uses the invoice country, Nederland by default', function () {
    digitaleWagen(digitaalProduct(50, true, true));

    $probe = new DigitalCheckoutProbe();
    $probe->country = 'België';
    $probe->fillPrices();

    expect($probe->country)->toBe('Nederland')
        ->and(cartHelper()->getVatCountry())->toBe('Nederland');
});

test('a checkout with a physical product still requires shipping', function () {
    digitaleZone();
    digitaleWagen(digitaalProduct(50, true, true), digitaalProduct(25, false, false));

    $probe = new DigitalCheckoutProbe();
    $probe->country = 'Nederland';
    $probe->fillPrices();

    expect($probe->requiresShipping)->toBeTrue()
        ->and($probe->shippingMethod)->not->toBeNull()
        ->and(verplicht($probe, 'shippingMethod'))->toBeTrue()
        ->and(verplicht($probe, 'street'))->toBeTrue();
});

test('a digital-only order is created without shipping method and shipping line', function () {
    // De eigen betaalmethode maakt de factuur meteen; die view heeft de aliassen nodig.
    bootInvoiceHarness();
    digitaleZone();
    digitaleWagen(digitaalProduct(50, true, true));

    $betaalmethode = PaymentMethod::create([
        'site_id' => Sites::getActive(),
        'name' => ['nl' => 'Overboeking'],
        'type' => 'online',
        'active' => 1,
        'psp' => 'own',
        'available_from_amount' => 0,
    ]);

    $probe = new DigitalCheckoutProbe();
    $probe->country = 'Nederland';
    $probe->paymentMethod = $betaalmethode->id;
    $probe->firstName = 'Kim';
    $probe->lastName = 'Jansen';
    $probe->email = 'kim@example.com';
    $probe->generalCondition = true;
    $probe->submit();

    $order = Order::latest('id')->first();

    expect($order)->not->toBeNull()
        ->and($order->shipping_method_id)->toBeNull()
        ->and($order->country)->toBe('Nederland')
        ->and($order->orderProducts()->where('sku', 'shipping_costs')->count())->toBe(0)
        ->and((float) $order->total)->toBe(50.0);
});
