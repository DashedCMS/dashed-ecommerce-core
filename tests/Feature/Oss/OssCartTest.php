<?php

use Illuminate\Support\Str;
use Dashed\DashedCore\Models\User;
use Dashed\DashedCore\Classes\Sites;
use Illuminate\Support\Facades\Queue;
use Dashed\DashedCore\Models\Customsetting;
use Dashed\DashedEcommerceCore\Classes\OssVat;
use Dashed\DashedEcommerceCore\Models\Product;
use Dashed\DashedEcommerceCore\Classes\CartHelper;
use Dashed\DashedEcommerceCore\Models\DiscountCode;
use Dashed\DashedEcommerceCore\Models\OrderProduct;
use Dashed\DashedEcommerceCore\Models\ProductGroup;
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

    request()->cookies->set(config('dashed-ecommerce.cart_cookie', 'cart_token'), (string) Str::uuid());

    Customsetting::set('company_country', 'Nederland');
    Customsetting::set('oss_enabled', '1');
    // Prijzen zijn inclusief btw, zoals in de shops; de testdatabase staat op exclusief.
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

function ossManualOrder(Product $product, string $country, ?string $discountCode = null): array
{
    $page = new CreateOrder();
    $page->cartInstance = 'handorder';
    $page->orderOrigin = 'own';
    $page->products = [['id' => $product->id, 'quantity' => 1, 'extra' => []]];
    $page->email = 'klant@example.com';
    $page->last_name = 'Klant';
    $page->country = $country;
    $page->discount_code = $discountCode;

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
