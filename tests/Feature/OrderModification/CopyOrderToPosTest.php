<?php

use App\Models\User;
use Dashed\DashedEcommerceCore\Models\Order;
use Dashed\DashedEcommerceCore\Models\POSCart;
use Dashed\DashedEcommerceCore\Models\OrderProduct;
use Dashed\DashedEcommerceCore\Classes\ConceptOrderService;

uses(\Illuminate\Foundation\Testing\RefreshDatabase::class);

/**
 * "Kopiëren naar kassa" nam alleen producten, de ex-btw-vlag en de
 * verzendmethode mee, dus moest de kassamedewerker de klantgegevens overtypen.
 * Het vinkje "Klantgegevens meekopiëren" (standaard aan) stuurt ze mee.
 */
function copyPosCart(?int $cashierId = null): POSCart
{
    $cart = new POSCart();
    $cart->user_id = $cashierId ?: User::factory()->create()->id;
    $cart->status = 'active';
    $cart->identifier = uniqid();
    $cart->products = [];
    $cart->save();

    return $cart;
}

function copyPosOrder(array $attributes = []): Order
{
    $order = Order::create(array_merge([
        'status' => 'paid',
        'invoice_id' => 'INV-' . strtoupper(uniqid()),
        'first_name' => 'Jan',
        'last_name' => 'Jansen',
        'email' => 'jan@example.com',
        'phone_number' => '0612345678',
        'street' => 'Dorpsstraat',
        'house_nr' => '12A',
        'zip_code' => '1234 AB',
        'city' => 'Amsterdam',
        'country' => 'NL',
        'company_name' => 'Jansen BV',
        'btw_id' => 'NL123456789B01',
        'invoice_street' => 'Factuurweg',
        'invoice_house_nr' => '99',
        'invoice_zip_code' => '9999 ZZ',
        'invoice_city' => 'Rotterdam',
        'invoice_country' => 'NL',
    ], $attributes));

    OrderProduct::create(['order_id' => $order->id, 'product_id' => null, 'name' => 'Los product', 'quantity' => 2, 'price' => 20.0, 'vat_rate' => 21]);

    return $order->fresh();
}

it('zet de klantgegevens van de bestelling op de kassa-winkelwagen', function () {
    $customer = User::factory()->create();
    $order = copyPosOrder(['user_id' => $customer->id]);
    $cart = copyPosCart();

    ConceptOrderService::copyIntoCart($cart, $order, copyCustomerDetails: true);

    $cart = $cart->fresh();

    expect($cart->first_name)->toBe('Jan')
        ->and($cart->last_name)->toBe('Jansen')
        ->and($cart->email)->toBe('jan@example.com')
        ->and($cart->phone_number)->toBe('0612345678')
        ->and($cart->street)->toBe('Dorpsstraat')
        ->and($cart->house_nr)->toBe('12A')
        ->and($cart->zip_code)->toBe('1234 AB')
        ->and($cart->city)->toBe('Amsterdam')
        ->and($cart->country)->toBe('NL')
        // company_name op de order heet company op de winkelwagen.
        ->and($cart->company)->toBe('Jansen BV')
        ->and($cart->btw_id)->toBe('NL123456789B01')
        ->and($cart->invoice_street)->toBe('Factuurweg')
        ->and($cart->invoice_house_nr)->toBe('99')
        ->and($cart->invoice_zip_code)->toBe('9999 ZZ')
        ->and($cart->invoice_city)->toBe('Rotterdam')
        ->and($cart->invoice_country)->toBe('NL')
        ->and((int) $cart->customer_user_id)->toBe($customer->id)
        // De producten gaan mee zoals altijd.
        ->and($cart->products)->toHaveCount(1)
        ->and($cart->products[0]['name'])->toBe('Los product');
});

it('laat de klantvelden van de kassa leeg wanneer het vinkje uit staat', function () {
    $customer = User::factory()->create();
    $order = copyPosOrder(['user_id' => $customer->id]);
    $cart = copyPosCart();

    ConceptOrderService::copyIntoCart($cart, $order, copyCustomerDetails: false);

    $cart = $cart->fresh();

    expect($cart->first_name)->toBeNull()
        ->and($cart->last_name)->toBeNull()
        ->and($cart->email)->toBeNull()
        ->and($cart->phone_number)->toBeNull()
        ->and($cart->street)->toBeNull()
        ->and($cart->house_nr)->toBeNull()
        ->and($cart->zip_code)->toBeNull()
        ->and($cart->city)->toBeNull()
        ->and($cart->country)->toBeNull()
        ->and($cart->company)->toBeNull()
        ->and($cart->btw_id)->toBeNull()
        ->and($cart->invoice_street)->toBeNull()
        ->and($cart->invoice_city)->toBeNull()
        ->and($cart->customer_user_id)->toBeNull()
        // ... maar de bestelling zelf komt gewoon over.
        ->and($cart->products)->toHaveCount(1)
        ->and($cart->products[0]['name'])->toBe('Los product')
        ->and((int) $cart->products[0]['quantity'])->toBe(2);
});

it('kopieert de klantgegevens niet zonder dat er om gevraagd wordt', function () {
    // Bestaande aanroepers (geen derde argument) horen precies te blijven doen
    // wat ze deden: alleen de bestelling zelf overzetten.
    $order = copyPosOrder(['user_id' => User::factory()->create()->id]);
    $cart = copyPosCart();

    ConceptOrderService::copyIntoCart($cart, $order);

    expect($cart->fresh()->first_name)->toBeNull()
        ->and($cart->fresh()->products)->toHaveCount(1);
});

it('zet de kassamedewerker niet als klant op zijn eigen winkelwagen', function () {
    // saveAsConcept() valt voor user_id terug op de medewerker wanneer er geen
    // klant aan de winkelwagen hing. Die terugval terugkopiëren zou de
    // medewerker als klant op de nieuwe verkoop zetten.
    $cashier = User::factory()->create();
    $order = copyPosOrder(['user_id' => $cashier->id, 'order_origin' => 'pos']);
    $cart = copyPosCart($cashier->id);

    ConceptOrderService::copyIntoCart($cart, $order, copyCustomerDetails: true);

    expect($cart->fresh()->customer_user_id)->toBeNull()
        // De rest van de gegevens komt wél mee: die staan los van user_id.
        ->and($cart->fresh()->first_name)->toBe('Jan');
});
