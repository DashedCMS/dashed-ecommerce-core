<?php

use App\Models\User;
use Dashed\DashedCore\Classes\Sites;
use Dashed\DashedEcommerceCore\Models\Cart;
use Dashed\DashedEcommerceCore\Models\POSCart;
use Dashed\DashedEcommerceCore\Models\Product;
use Dashed\DashedEcommerceCore\Models\CartItem;
use Dashed\DashedEcommerceCore\Models\DiscountCode;
use Dashed\DashedEcommerceCore\Models\ProductExtra;
use Dashed\DashedEcommerceCore\Classes\CartToPosService;
use Dashed\DashedEcommerceCore\Models\ProductExtraOption;
use Dashed\DashedEcommerceCore\Controllers\Api\PointOfSale\PointOfSaleApiController;

uses(\Illuminate\Foundation\Testing\RefreshDatabase::class);

function cartToPosProduct(float $price = 50): Product
{
    return Product::withoutEvents(fn () => Product::create([
        'name' => ['nl' => 'Ketting'],
        'slug' => ['nl' => 'ketting-' . uniqid()],
        'site_ids' => [Sites::getActive()],
        'use_stock' => 0,
        'stock' => 0,
        'total_stock' => 0,
        'in_stock' => 1,
        'stock_status' => 'in_stock',
        'price' => $price,
        'current_price' => $price,
        'public' => 1,
    ]));
}

function cartToPosUser(?string $email = null): User
{
    return User::forceCreate([
        'first_name' => 'Test',
        'last_name' => 'Gebruiker',
        'email' => $email ?: uniqid() . '@example.com',
        'password' => 'geheim',
    ]);
}

function cartToPosCashier(): User
{
    return cartToPosUser();
}

it('zet producten, gekozen opties en aantallen uit een webshopwagen in de kassa', function () {
    $product = cartToPosProduct();
    $extra = ProductExtra::create(['product_id' => $product->id, 'name' => 'Gravure', 'type' => 'single', 'required' => false]);
    $option = ProductExtraOption::create(['product_extra_id' => $extra->id, 'value' => 'Initialen', 'price' => 5]);

    $cart = Cart::create(['type' => 'default']);
    CartItem::create(['cart_id' => $cart->id, 'product_id' => $product->id, 'name' => 'Ketting', 'unit_price' => 55, 'quantity' => 2, 'options' => [
        'options' => [(string) $option->id => ['name' => 'Gravure', 'value' => 'Initialen', 'price' => 5]],
        'hiddenOptions' => [],
    ]]);

    $cashier = cartToPosCashier();
    $posCart = CartToPosService::activePosCart($cashier->id);
    CartToPosService::copyIntoCart($posCart, $cart->fresh());

    $row = $posCart->fresh()->products[0];
    expect($row['id'])->toBe($product->id)
        ->and($row['quantity'])->toBe(2)
        ->and($row['extra'])->toBe([$extra->id => $option->id])
        ->and($row['customProduct'])->toBeFalse();

    $totals = app(PointOfSaleApiController::class)->calculatePosCartTotals($posCart->fresh());
    expect((float) $totals['subtotal'])->toBe(110.0);

    // De wagen van de klant blijft zoals hij was.
    expect($cart->items()->count())->toBe(1);
});

it('houdt de tekst en de meerprijs van een invulveld vast', function () {
    $product = cartToPosProduct(12);
    $extra = ProductExtra::create(['product_id' => $product->id, 'name' => 'Andere kleur', 'type' => 'input', 'required' => false, 'price' => 5]);

    $cart = Cart::create(['type' => 'default']);
    CartItem::create(['cart_id' => $cart->id, 'product_id' => $product->id, 'name' => 'Ketting', 'unit_price' => 17, 'quantity' => 1, 'options' => [
        'options' => ['product-extra-input-' . $extra->id => ['name' => 'Andere kleur', 'value' => 'Zwart', 'price' => 5]],
    ]]);

    $posCart = CartToPosService::activePosCart(cartToPosCashier()->id);
    CartToPosService::copyIntoCart($posCart, $cart->fresh());
    $posCart = $posCart->fresh();

    expect($posCart->products[0]['name'])->toBe('Ketting (Andere kleur: Zwart)')
        ->and($posCart->products[0]['extra'])->toBe([])
        ->and($posCart->note)->toBe('Ketting - Andere kleur: Zwart');

    $totals = app(PointOfSaleApiController::class)->calculatePosCartTotals($posCart);
    expect((float) $totals['subtotal'])->toBe(17.0);
});

it('maakt van een regel zonder bestaand product een losse kassaregel met de wagenprijs', function () {
    $cart = Cart::create(['type' => 'default']);
    CartItem::create(['cart_id' => $cart->id, 'product_id' => null, 'name' => 'Maatwerk', 'unit_price' => 30, 'quantity' => 3, 'options' => ['customProduct' => true]]);

    $posCart = CartToPosService::activePosCart(cartToPosCashier()->id);
    CartToPosService::copyIntoCart($posCart, $cart->fresh());

    $row = $posCart->fresh()->products[0];
    expect($row['customProduct'])->toBeTrue()
        ->and($row['name'])->toBe('Maatwerk')
        ->and((float) $row['singlePrice'])->toBe(30.0);

    $totals = app(PointOfSaleApiController::class)->calculatePosCartTotals($posCart->fresh());
    expect((float) $totals['subtotal'])->toBe(90.0);
});

it('neemt klant en kortingscode mee en vervangt wat er in de kassa lag', function () {
    $product = cartToPosProduct();
    $customer = cartToPosUser('klant@example.com');
    $code = DiscountCode::create(['name' => 'Tien', 'code' => 'TIEN', 'type' => 'percentage', 'discount_percentage' => 10, 'site_ids' => [Sites::getActive()]]);

    $cart = Cart::create(['type' => 'default', 'user_id' => $customer->id, 'abandoned_email' => 'ander@example.com', 'discount_code_id' => $code->id]);
    CartItem::create(['cart_id' => $cart->id, 'product_id' => $product->id, 'name' => 'Ketting', 'unit_price' => 50, 'quantity' => 1, 'options' => []]);

    $cashier = cartToPosCashier();
    $posCart = CartToPosService::activePosCart($cashier->id);
    $posCart->products = [['id' => null, 'identifier' => 'oud', 'name' => 'Oud', 'quantity' => 1, 'singlePrice' => 1, 'price' => 1, 'customProduct' => true, 'extra' => []]];
    $posCart->discount_code = 'OUD';
    $posCart->save();

    expect(CartToPosService::activePosCartHasProducts($cashier->id))->toBeTrue();

    CartToPosService::copyIntoCart($posCart, $cart->fresh());
    $posCart = $posCart->fresh();

    expect($posCart->products)->toHaveCount(1)
        ->and($posCart->products[0]['name'])->toBe('Ketting')
        ->and($posCart->customer_user_id)->toBe($customer->id)
        ->and($posCart->first_name)->toBe('Test')
        ->and($posCart->email)->toBe('ander@example.com')
        ->and($posCart->discount_code)->toBe('TIEN')
        ->and($posCart->applied_discount_codes[0]['code'])->toBe('TIEN');
});

it('laat klant en kortingscode weg als dat is uitgevinkt', function () {
    $product = cartToPosProduct();
    $customer = cartToPosUser();
    $code = DiscountCode::create(['name' => 'Tien', 'code' => 'TIEN2', 'type' => 'percentage', 'discount_percentage' => 10, 'site_ids' => [Sites::getActive()]]);

    $cart = Cart::create(['type' => 'default', 'user_id' => $customer->id, 'discount_code_id' => $code->id]);
    CartItem::create(['cart_id' => $cart->id, 'product_id' => $product->id, 'name' => 'Ketting', 'unit_price' => 50, 'quantity' => 1, 'options' => []]);

    $posCart = CartToPosService::activePosCart(cartToPosCashier()->id);
    CartToPosService::copyIntoCart($posCart, $cart->fresh(), copyCustomerDetails: false, copyDiscountCode: false);
    $posCart = $posCart->fresh();

    expect($posCart->customer_user_id)->toBeNull()
        ->and($posCart->discount_code)->toBeNull()
        ->and($posCart->applied_discount_codes)->toBe([]);
});
