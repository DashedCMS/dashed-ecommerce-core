<?php

use Illuminate\Support\Str;
use Dashed\DashedCore\Models\User;
use Illuminate\Support\Facades\Queue;
use Dashed\DashedEcommerceCore\Models\Product;
use Dashed\DashedEcommerceCore\Classes\CartHelper;
use Dashed\DashedEcommerceCore\Models\OrderProduct;
use Dashed\DashedEcommerceCore\Models\ProductGroup;
use Dashed\DashedEcommerceCore\Filament\Resources\OrderResource\Pages\CreateOrder;

/**
 * Sinds de DB-cart zijn winkelwagenregels kale stdClass-objecten zonder de
 * `taxRate` van hardevine/shoppingcart. Een handmatige bestelling in het CMS
 * viel daardoor om met "Undefined property: stdClass::$taxRate" zodra er een
 * gewoon (niet-custom) product in zat: dat heeft geen `vat_rate` in de opties.
 */
beforeEach(function () {
    Queue::fake();

    CartHelper::$cart = null;
    CartHelper::$initialized = false;
    CartHelper::$cartItemsInitialized = false;
    CartHelper::$cartItems = [];
    CartHelper::$cartProductsById = [];

    request()->cookies->set(config('dashed-ecommerce.cart_cookie', 'cart_token'), (string) Str::uuid());
});

function manualOrderProduct(float $vatRate): Product
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
        'name' => ['en' => 'Lamp'],
        'slug' => ['en' => 'lamp-' . uniqid()],
        'site_ids' => ['default'],
        'current_price' => 10.00,
        'price' => 10.00,
        'vat_rate' => $vatRate,
        'public' => 1,
    ]));
}

it('neemt het btw-tarief van het product over op een handmatige bestelling', function () {
    $this->actingAs(User::factory()->create(['role' => 'admin']));
    $product = manualOrderProduct(9);

    // Livewire::test() werkt in deze Testbench-suite niet (geen livewire-
    // binding); de pagina direct instantiëren volstaat om createOrder() te
    // doorlopen zoals het CMS dat doet.
    $page = new CreateOrder();
    $page->cartInstance = 'handorder';
    $page->orderOrigin = 'own';
    $page->products = [[
        'id' => $product->id,
        'quantity' => 2,
        'extra' => [],
    ]];
    $page->email = 'klant@example.com';
    $page->last_name = 'Klant';
    $page->country = 'Nederland';

    $response = $page->createOrder();

    expect($response['success'])->toBeTrue();

    $line = OrderProduct::where('order_id', $response['order']->id)
        ->where('product_id', $product->id)
        ->first();

    expect($line)->not->toBeNull()
        ->and((float) $line->vat_rate)->toBe(9.0)
        ->and($line->quantity)->toBe(2);
});

it('houdt het opgegeven btw-tarief van een custom product aan', function () {
    $this->actingAs(User::factory()->create(['role' => 'admin']));

    $page = new CreateOrder();
    $page->cartInstance = 'handorder';
    $page->orderOrigin = 'own';
    $page->products = [[
        'id' => null,
        'customProduct' => true,
        'name' => 'Montage',
        'quantity' => 1,
        'singlePrice' => 50,
        'vat_rate' => 9,
        'extra' => [],
    ]];
    $page->email = 'klant@example.com';
    $page->last_name = 'Klant';
    $page->country = 'Nederland';

    $response = $page->createOrder();

    expect($response['success'])->toBeTrue();

    $line = OrderProduct::where('order_id', $response['order']->id)->where('name', 'Montage')->first();

    expect($line)->not->toBeNull()
        ->and((float) $line->vat_rate)->toBe(9.0);
});
