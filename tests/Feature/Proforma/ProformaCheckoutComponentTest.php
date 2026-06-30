<?php

// tests/Feature/Proforma/ProformaCheckoutComponentTest.php

use Livewire\Livewire;
use Illuminate\Support\Facades\Mail;
use Dashed\DashedEcommerceCore\Models\Order;
use Dashed\DashedEcommerceCore\Livewire\Frontend\Checkout\ProformaCheckout;

function makeProforma(array $attrs = []): Order
{
    $order = Order::create(array_merge([
        'email' => 'klant@example.com',
        'status' => Order::STATUS_CONCEPT,
        'is_proforma' => true,
        'proforma_allow_shipping' => false,
    ], $attrs));
    $order->orderProducts()->create([
        'product_id' => null, 'name' => 'Maatwerk', 'quantity' => 1, 'price' => 121.0, 'vat_rate' => 21,
    ]);

    return $order;
}

beforeEach(function () {
    Mail::fake();
});

it('prefills the customer email from the proforma', function () {
    $order = makeProforma();
    Livewire::test(ProformaCheckout::class, ['orderHash' => $order->hash])
        ->assertSet('email', 'klant@example.com');
});

it('validates required customer fields on submit', function () {
    $order = makeProforma();
    Livewire::test(ProformaCheckout::class, ['orderHash' => $order->hash])
        ->set('firstName', '')
        ->set('lastName', '')
        ->call('submit')
        ->assertHasErrors(['firstName', 'lastName']);
});

it('writes details and transitions the order to pending with an invoice on submit', function () {
    $order = makeProforma();
    Livewire::test(ProformaCheckout::class, ['orderHash' => $order->hash])
        ->set('firstName', 'Jan')->set('lastName', 'Jansen')
        ->set('street', 'Straat')->set('houseNr', '1')
        ->set('zipCode', '1234AB')->set('city', 'Stad')->set('country', 'NL')
        ->call('submit');

    $order->refresh();
    expect($order->first_name)->toBe('Jan')
        ->and($order->status)->toBe('pending')
        ->and($order->invoice_id)->not->toBeNull();
});
