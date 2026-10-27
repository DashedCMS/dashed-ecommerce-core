<?php

declare(strict_types=1);

use Livewire\Livewire;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Mail;
use Dashed\DashedCore\Classes\Sites;
use Dashed\DashedEcommerceCore\Models\Cart;
use Dashed\DashedEcommerceCore\Models\Order;
use Dashed\DashedEcommerceCore\Models\Product;
use Dashed\DashedEcommerceCore\Models\CartItem;
use Dashed\DashedEcommerceCore\Classes\CartHelper;
use Dashed\DashedEcommerceCore\Models\ProductGroup;
use Dashed\DashedEcommerceCore\Models\ShippingZone;
use Dashed\DashedEcommerceCore\Models\PaymentMethod;
use Dashed\DashedEcommerceCore\Models\ShippingMethod;
use Dashed\DashedEcommerceCore\Jobs\UpdateProductInformationJob;
use Dashed\DashedEcommerceCore\Livewire\Frontend\Checkout\Checkout;

function csResetCart(): void
{
    CartHelper::$cart = null;
    CartHelper::$cartItemsInitialized = false;
    CartHelper::$cartItems = [];
    CartHelper::$cartProductsById = [];
}

/** @return array{0: ShippingMethod, 1: PaymentMethod, 2: \App\Models\User} */
function csCheckoutSetup(): array
{
    $site = Sites::getActive();
    $group = ProductGroup::create([
        'name' => ['nl' => 'Groep'], 'slug' => ['nl' => 'groep-'.uniqid()],
        'short_description' => ['nl' => ''], 'description' => ['nl' => ''],
        'content' => ['nl' => ''], 'search_terms' => ['nl' => ''], 'site_ids' => [$site],
    ]);
    $product = Product::withoutEvents(fn () => Product::create([
        'name' => ['nl' => 'Vaas'], 'slug' => ['nl' => 'vaas-'.uniqid()],
        'site_ids' => [$site], 'product_group_id' => $group->id,
        'public' => 1, 'use_stock' => 0, 'in_stock' => 1, 'stock_status' => 'in_stock',
        // total_stock zet normaal UpdateProductInformationJob; zonder telt de
        // checkout het product als uitverkocht en haalt hij het weg.
        'total_stock' => 100000,
        'price' => 10, 'current_price' => 10,
    ]));

    // Een ingelogde klant: de winkelwagen wordt dan op user_id gevonden. Een
    // cookie haalt het vervolgverzoek van de Livewire-test niet, want
    // EncryptCookies kan een onversleutelde waarde niet lezen.
    $user = \App\Models\User::query()->findOrFail(\Dashed\DashedCore\Models\User::factory()->create()->id);
    test()->actingAs($user);
    $cart = Cart::create(['token' => (string) Str::uuid(), 'type' => 'default', 'user_id' => $user->id]);
    CartItem::create([
        'cart_id' => $cart->id, 'product_id' => $product->id, 'name' => 'Vaas',
        'unit_price' => 10, 'quantity' => 1, 'options' => [], 'options_hash' => '',
    ]);
    csResetCart();

    $zone = ShippingZone::create([
        'site_id' => $site, 'name' => ['nl' => 'Nederland'], 'zones' => ['Nederland'], 'search_fields' => 'Nederland',
    ]);
    $shipping = ShippingMethod::create([
        'shipping_zone_id' => $zone->id, 'name' => ['nl' => 'PostNL'], 'costs' => 5, 'sort' => 'static_amount',
        'minimum_order_value' => 0, 'maximum_order_value' => 100000, 'order' => 1,
    ]);
    $payment = PaymentMethod::create([
        'site_id' => $site, 'name' => ['nl' => 'Overboeking'], 'type' => 'online', 'active' => 1,
        'psp' => 'own', 'available_from_amount' => 0,
    ]);

    return [$shipping, $payment, $user];
}

function csFilledCheckout(ShippingMethod $shipping, PaymentMethod $payment)
{
    return Livewire::test(Checkout::class)
        ->set('email', 'klant@example.com')
        ->set('firstName', 'Jan')->set('lastName', 'Jansen')
        ->set('street', 'Straat')->set('houseNr', '1')
        ->set('zipCode', '1234AB')->set('city', 'Utrecht')->set('country', 'Nederland')
        ->set('shippingMethod', (string) $shipping->id)
        ->set('paymentMethod', (string) $payment->id)
        ->set('generalCondition', true);
}

beforeEach(function () {
    csResetCart();
    Mail::fake();
    // sqlite: de prijsherberekening in die job gebruikt GREATEST().
    Bus::fake([UpdateProductInformationJob::class]);
});

it('plaatst via submit een bestelling met de bedragen uit de winkelwagen', function () {
    [$shipping, $payment, $user] = csCheckoutSetup();

    csFilledCheckout($shipping, $payment)
        ->call('submit')
        ->assertRedirect();

    $order = Order::query()->latest('id')->firstOrFail();

    // De testomgeving rekent prijzen exclusief btw: (10 + 5 verzending) * 1,21.
    expect($order->email)->toBe('klant@example.com')
        ->and((float) $order->total)->toEqual(18.15)
        ->and($order->status)->toBe('waiting_for_confirmation')
        ->and($order->shipping_method_id)->toBe($shipping->id)
        ->and($order->orderProducts()->where('sku', 'shipping_costs')->count())->toBe(1)
        ->and($order->orderProducts()->whereNotNull('product_id')->sum('quantity'))->toEqual(1);
});

it('negeert bij submit bedragen en ordervelden die vanuit de browser zijn gezet', function () {
    [$shipping, $payment, $user] = csCheckoutSetup();

    csFilledCheckout($shipping, $payment)
        ->set('total', 0.01)
        ->set('subtotal', 0.01)
        ->set('shippingCosts', 0)
        ->set('customFieldValues', ['status' => 'paid', 'total' => '0.01', 'invoice_id' => 'X', 'user_id' => 1])
        ->call('submit')
        ->assertRedirect();

    $order = Order::query()->latest('id')->firstOrFail();

    expect((float) $order->total)->toEqual(18.15)
        ->and((float) $order->subtotal)->toBeGreaterThan(1.0)
        ->and($order->status)->toBe('waiting_for_confirmation')
        ->and($order->user_id)->toBe($user->id);
});

it('plaatst geen bestelling met een verzendmethode die niet bij het land hoort', function () {
    csCheckoutSetup();
    $payment = PaymentMethod::firstOrFail();
    $otherZone = ShippingZone::create([
        'site_id' => Sites::getActive(), 'name' => ['nl' => 'Belgie'], 'zones' => ['Belgie'], 'search_fields' => 'Belgie',
    ]);
    $foreign = ShippingMethod::create([
        'shipping_zone_id' => $otherZone->id, 'name' => ['nl' => 'Gratis'], 'costs' => 0, 'sort' => 'static_amount',
        'minimum_order_value' => 0, 'maximum_order_value' => 100000, 'order' => 1,
    ]);

    csFilledCheckout($foreign, $payment)->call('submit');

    expect(Order::query()->where('shipping_method_id', $foreign->id)->exists())->toBeFalse();
});

it('plaatst geen bestelling met een lege winkelwagen', function () {
    [$shipping, $payment] = csCheckoutSetup();
    CartItem::query()->delete();
    csResetCart();

    csFilledCheckout($shipping, $payment)->call('submit')->assertNoRedirect();

    expect(Order::query()->count())->toBe(0);
});
