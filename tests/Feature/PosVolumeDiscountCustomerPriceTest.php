<?php

use Dashed\DashedCore\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;
use Dashed\DashedEcommerceCore\Models\POSCart;
use Dashed\DashedEcommerceCore\Models\Product;
use Dashed\DashedEcommerceCore\Models\PriceGroup;
use Dashed\DashedEcommerceCore\Models\ProductGroup;
use Dashed\DashedEcommerceCore\Models\ProductGroupVolumeDiscount;
use Dashed\DashedEcommerceCore\Controllers\Api\PointOfSale\PointOfSaleApiController;

uses(\Illuminate\Foundation\Testing\RefreshDatabase::class);

/** Product of €100 with a 10% volume discount from 3 pieces. */
function staffelProduct(): Product
{
    Queue::fake();

    $group = ProductGroup::create([
        'name' => ['en' => 'Groep'],
        'slug' => ['en' => 'groep-' . uniqid()],
        'short_description' => ['en' => ''], 'description' => ['en' => ''],
        'content' => ['en' => ''], 'search_terms' => ['en' => ''],
        'site_ids' => ['site'],
    ]);

    $product = Product::withoutEvents(fn () => Product::create([
        'product_group_id' => $group->id,
        'name' => ['en' => 'Testproduct'],
        'slug' => ['en' => 'test-' . uniqid()],
        'site_ids' => ['site'],
        'price' => 100.0, 'current_price' => 100.0, 'vat_rate' => 21,
        'images' => [],
    ]));

    $discount = ProductGroupVolumeDiscount::create([
        'product_group_id' => $group->id,
        'type' => 'percentage',
        'discount_percentage' => 10,
        'min_quantity' => 3,
    ]);
    DB::table('dashed__product_group_volume_discount_product')->insert([
        'product_group_volume_discount_id' => $discount->id,
        'product_id' => $product->id,
    ]);

    return $product;
}

function staffelCart(Product $product, int $quantity, ?int $customerUserId): POSCart
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
        'quantity' => $quantity,
        'singlePrice' => 100.0,
        'price' => 100.0 * $quantity,
        'vat_rate' => 21,
    ]];
    $cart->save();

    return $cart;
}

function prijsgroepKlant(Product $product, float $price): User
{
    $priceGroup = PriceGroup::create(['name' => 'B2B']);
    DB::table('dashed__product_price_group')->insert([
        'price_group_id' => $priceGroup->id,
        'product_id' => $product->id,
        'price' => $price,
    ]);

    return User::factory()->create(['price_group_id' => $priceGroup->id]);
}

// De kassaberekening met een klant op de bon draait in Lovora
// (tests/Feature/Kassa/StaffelOpKlantprijsTest): het harnas hier levert
// een andere User-klasse dan Product::priceForUser() verwacht.
it('geeft in de kassa wel staffelkorting zonder klant op de bon', function () {
    $product = staffelProduct();

    $totals = (new PointOfSaleApiController())->calculatePosCartTotals(staffelCart($product, 3, null));

    expect(round((float) $totals['subtotal'], 2))->toBe(270.0);
});

it('telt een prijsgroepprijs als eigen prijs', function () {
    $product = staffelProduct();
    $customer = prijsgroepKlant($product, 60.0);

    expect($product->hasCustomPriceForUser($customer))->toBeTrue()
        ->and($product->hasCustomPriceForUser(User::factory()->create()))->toBeFalse();
});
