<?php

use Dashed\DashedCore\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;
use Dashed\DashedEcommerceCore\Models\POSCart;
use Dashed\DashedEcommerceCore\Models\Product;
use Dashed\DashedEcommerceCore\Models\PriceGroup;
use Dashed\DashedEcommerceCore\Models\ProductGroup;
use Dashed\DashedEcommerceCore\Controllers\Api\PointOfSale\PointOfSaleApiController;

uses(\Illuminate\Foundation\Testing\RefreshDatabase::class);

function posPriceProduct(float $price = 100.0): Product
{
    Queue::fake();

    $group = ProductGroup::create([
        'name' => ['en' => 'Groep'],
        'slug' => ['en' => 'groep-' . uniqid()],
        'short_description' => ['en' => ''], 'description' => ['en' => ''],
        'content' => ['en' => ''], 'search_terms' => ['en' => ''],
        'site_ids' => ['site'],
    ]);

    return Product::withoutEvents(fn () => Product::create([
        'product_group_id' => $group->id,
        'name' => ['en' => 'Testproduct'],
        'slug' => ['en' => 'test-' . uniqid()],
        'site_ids' => ['site'],
        'price' => $price, 'current_price' => $price, 'vat_rate' => 21,
        'images' => [],
    ]));
}

function posCartWithLine(Product $product, float $singlePrice, bool $isCustomPrice, ?int $customerUserId): POSCart
{
    $cart = new POSCart();
    $cart->user_id = User::factory()->create()->id;
    $cart->customer_user_id = $customerUserId;
    $cart->status = 'active';
    $cart->identifier = uniqid();
    $cart->products = [[
        'identifier' => 'regel-1',
        'id' => $product->id,
        'name' => 'Testproduct',
        'quantity' => 1,
        'singlePrice' => $singlePrice,
        'price' => $singlePrice,
        'vat_rate' => 21,
        'isCustomPrice' => $isCustomPrice,
    ]];
    $cart->save();

    return $cart;
}

it('houdt een handmatig gezette prijs aan wanneer er een klant aan de kassabon hangt', function () {
    $product = posPriceProduct(100.0);
    $customer = User::factory()->create();

    // Kassamedewerker zet de regel op 1 cent.
    $cart = posCartWithLine($product, singlePrice: 0.01, isCustomPrice: true, customerUserId: $customer->id);

    $totals = (new PointOfSaleApiController())->calculatePosCartTotals($cart);

    expect(round((float) $totals['subtotal'], 2))->toBe(0.01);
});

it('gebruikt de klantprijs wanneer de regel geen handmatige prijs draagt', function () {
    $product = posPriceProduct(100.0);

    $priceGroup = PriceGroup::create(['name' => 'B2B']);
    $customer = User::factory()->create(['price_group_id' => $priceGroup->id]);

    // Een prijsgroepprijs van 60 voor dit product.
    DB::table('dashed__product_price_group')->insert([
        'price_group_id' => $priceGroup->id,
        'product_id' => $product->id,
        'price' => 60.0,
    ]);

    $cart = posCartWithLine($product, singlePrice: 100.0, isCustomPrice: false, customerUserId: $customer->id);

    $totals = (new PointOfSaleApiController())->calculatePosCartTotals($cart);

    expect(round((float) $totals['subtotal'], 2))->toBe(60.0);
});

it('houdt een handmatig gezette prijs aan zonder klant', function () {
    $product = posPriceProduct(100.0);

    $cart = posCartWithLine($product, singlePrice: 0.01, isCustomPrice: true, customerUserId: null);

    $totals = (new PointOfSaleApiController())->calculatePosCartTotals($cart);

    expect(round((float) $totals['subtotal'], 2))->toBe(0.01);
});
