<?php

use App\Models\User;
use Dashed\DashedCore\Classes\Sites;
use Dashed\DashedEcommerceCore\Models\Order;
use Dashed\DashedEcommerceCore\Models\POSCart;
use Dashed\DashedEcommerceCore\Models\Product;
use Dashed\DashedEcommerceCore\Models\OrderProduct;
use Dashed\DashedEcommerceCore\Models\ProductExtra;
use Dashed\DashedEcommerceCore\Classes\PosCartExtras;
use Dashed\DashedEcommerceCore\Models\ProductExtraOption;
use Dashed\DashedEcommerceCore\Classes\ConceptOrderService;
use Dashed\DashedEcommerceCore\Controllers\Api\PointOfSale\PointOfSaleApiController;

uses(\Illuminate\Foundation\Testing\RefreshDatabase::class);

/**
 * Een orderregel bewaart zijn extra's als lijst van optierijen
 * ([['id' => optieId, 'name', 'value', 'price'], ...]); de kassa bewaart ze
 * als map [extraId => optieId]. "Kopiëren naar kassa" zette de lijst
 * ongewijzigd op de kassaregel, waarna de eerstvolgende initialize van de
 * kassa op "Array to string conversion" klapte en de kassa stuk bleef tot
 * iemand de winkelwagen leegde (Lovora, 15 oktober 2026).
 */
function extrasPosCart(): POSCart
{
    $cart = new POSCart();
    $cart->user_id = User::factory()->create()->id;
    $cart->status = 'active';
    $cart->identifier = uniqid();
    $cart->products = [];
    $cart->save();

    return $cart;
}

function productWithExtraOption(): array
{
    $product = Product::withoutEvents(fn () => Product::create([
        'name' => ['nl' => 'Ketting'],
        'slug' => ['nl' => 'ketting-' . uniqid()],
        'site_ids' => [Sites::getActive()],
        'use_stock' => 0,
        'stock' => 0,
        'total_stock' => 0,
        'in_stock' => 1,
        'stock_status' => 'in_stock',
        'price' => 50,
        'current_price' => 50,
        'public' => 1,
    ]));
    $extra = ProductExtra::create(['product_id' => $product->id, 'name' => 'Gravure', 'type' => 'single', 'required' => false]);
    $option = ProductExtraOption::create(['product_extra_id' => $extra->id, 'value' => 'Initialen', 'price' => 5]);

    return [$product, $extra, $option];
}

function orderWithExtras(int $productId, array $productExtras): Order
{
    $order = Order::create(['status' => 'paid', 'invoice_id' => 'INV-' . strtoupper(uniqid()), 'first_name' => 'Jan', 'last_name' => 'Jansen', 'email' => 'jan@example.com']);

    OrderProduct::create(['order_id' => $order->id, 'product_id' => $productId, 'name' => 'Ketting', 'quantity' => 1, 'price' => 55.0, 'vat_rate' => 21, 'product_extras' => $productExtras]);

    return $order->fresh();
}

it('zet de extra\'s van een bestelling als kassa-map op de winkelwagen', function () {
    [$product, $extra, $option] = productWithExtraOption();
    $order = orderWithExtras($product->id, [
        ['id' => $option->id, 'name' => 'Gravure', 'value' => 'Initialen', 'path' => '', 'price' => 5],
        // Een invulveld heeft geen optie en kan de kassa niet in.
        ['id' => 'product-extra-99', 'name' => 'Tekst', 'value' => 'Hallo', 'path' => '', 'price' => 0],
    ]);
    $cart = extrasPosCart();

    ConceptOrderService::copyIntoCart($cart, $order);

    expect($cart->fresh()->products[0]['extra'])->toBe([$extra->id => $option->id]);
});

it('laat de kassa niet meer klappen op een winkelwagen die de oude lijstvorm nog draagt', function () {
    [$product, $extra, $option] = productWithExtraOption();
    $cart = extrasPosCart();
    $cart->products = [[
        'id' => $product->id,
        'identifier' => 'x',
        'name' => 'Ketting',
        'quantity' => 1,
        'singlePrice' => 55.0,
        'price' => 55.0,
        'vat_rate' => 21,
        'extra' => [['id' => $option->id, 'name' => 'Gravure', 'value' => 'Initialen', 'path' => '', 'price' => 5]],
        'customProduct' => false,
    ]];
    $cart->save();

    $totals = app(PointOfSaleApiController::class)->calculatePosCartTotals($cart->fresh());

    // Catalogusprijs 50 plus de optie van 5.
    expect((float) $totals['subtotal'])->toBe(55.0);
});

it('vertaalt beide vormen naar de kassa-map en slaat onbekende waarden over', function () {
    [$product, $extra, $option] = productWithExtraOption();

    expect(PosCartExtras::optionMap([$extra->id => $option->id]))->toBe([$extra->id => $option->id])
        ->and(PosCartExtras::optionMap([$extra->id => null, 7 => '']))->toBe([])
        ->and(PosCartExtras::optionMap([['id' => $option->id, 'name' => 'Gravure']]))->toBe([$extra->id => $option->id])
        ->and(PosCartExtras::optionMap([['id' => 'product-extra-3'], ['name' => 'zonder id'], 'tekst']))->toBe([]);
});
