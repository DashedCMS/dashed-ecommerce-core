<?php

// tests/Feature/Proforma/ProformaCheckoutComponentTest.php

use Livewire\Livewire;
use Dashed\DashedCore\Classes\Sites;
use Illuminate\Support\Facades\Mail;
use Dashed\DashedEcommerceCore\Models\Order;
use Dashed\DashedEcommerceCore\Models\PaymentMethod;
use Dashed\DashedEcommerceCore\Livewire\Frontend\Checkout\ProformaCheckout;

function makeProforma(array $attrs = []): Order
{
    $order = Order::create(array_merge([
        'email' => 'klant@example.com',
        'status' => Order::STATUS_CONCEPT,
        'is_proforma' => true,
        'proforma_allow_shipping' => false,
        'total' => 121.0,
        'subtotal' => 121.0,
        'btw' => round(121.0 - (121.0 / 1.21), 2),
    ], $attrs));
    $order->orderProducts()->create([
        'product_id' => null, 'name' => 'Maatwerk', 'quantity' => 1, 'price' => 121.0, 'vat_rate' => 21,
    ]);

    return $order;
}

// Echte DB-betaalmethode (net als iDEAL): actief, online, beschikbaar vanaf 0.
// psp = 'mollie' zodat het GEEN 'own' handmatige methode is en de gewone
// PSP-startflow wordt geraakt (die in de testomgeving netjes faalt/flasht).
function makeProformaPaymentMethod(string $psp = 'mollie'): PaymentMethod
{
    return PaymentMethod::create([
        'site_id' => Sites::getActive(),
        'name' => ['nl' => 'iDEAL'],
        'type' => 'online',
        'active' => 1,
        'psp' => $psp,
        'available_from_amount' => 0,
    ]);
}

beforeEach(function () {
    Mail::fake();
});

it('prefills the customer email from the proforma', function () {
    $order = makeProforma();
    Livewire::test(ProformaCheckout::class, ['orderHash' => $order->hash])
        ->assertSet('email', 'klant@example.com');
});

it('validates required customer fields and payment method on submit', function () {
    $order = makeProforma();
    Livewire::test(ProformaCheckout::class, ['orderHash' => $order->hash])
        ->set('lastName', '')
        ->call('submit')
        ->assertHasErrors(['lastName', 'paymentMethod']);
});

it('requires a payment method even when all address fields are valid', function () {
    $order = makeProforma();
    Livewire::test(ProformaCheckout::class, ['orderHash' => $order->hash])
        ->set('firstName', 'Jan')->set('lastName', 'Jansen')
        ->set('street', 'Straat')->set('houseNr', '1')
        ->set('zipCode', '1234AB')->set('city', 'Stad')->set('country', 'NL')
        ->call('submit')
        ->assertHasErrors(['paymentMethod']);

    expect($order->refresh()->status)->toBe('concept');
});

it('only requires invoice-address fields when an invoice street is filled', function () {
    $order = makeProforma();

    // Geen factuurstraat: de factuurvelden zijn niet verplicht.
    Livewire::test(ProformaCheckout::class, ['orderHash' => $order->hash])
        ->set('invoiceStreet', '')
        ->call('submit')
        ->assertHasNoErrors(['invoiceHouseNr', 'invoiceZipCode', 'invoiceCity', 'invoiceCountry']);

    // Wel een factuurstraat: nu zijn de overige factuurvelden verplicht.
    Livewire::test(ProformaCheckout::class, ['orderHash' => $order->hash])
        ->set('invoiceStreet', 'Factuurstraat')
        ->call('submit')
        ->assertHasErrors(['invoiceHouseNr', 'invoiceZipCode', 'invoiceCity', 'invoiceCountry']);
});

it('lists real database payment methods and records the chosen method on submit', function () {
    $order = makeProforma();
    $method = makeProformaPaymentMethod();

    $component = Livewire::test(ProformaCheckout::class, ['orderHash' => $order->hash]);

    // De echte betaalmethode staat in de lijst.
    expect(collect($component->get('paymentMethods'))->pluck('id'))->toContain($method->id);

    $component
        ->set('firstName', 'Jan')->set('lastName', 'Jansen')
        ->set('street', 'Straat')->set('houseNr', '1')
        ->set('zipCode', '1234AB')->set('city', 'Stad')->set('country', 'NL')
        ->set('paymentMethod', (string) $method->id)
        ->call('submit');

    $order->refresh();
    expect($order->status)->toBe('pending')
        ->and((int) $order->payment_method_id)->toBe($method->id)
        ->and($order->orderPayments()->where('payment_method_id', $method->id)->count())->toBe(1);
});

it('writes details, company/taxId, invoice address and transitions to pending on submit', function () {
    $order = makeProforma();
    $method = makeProformaPaymentMethod();

    Livewire::test(ProformaCheckout::class, ['orderHash' => $order->hash])
        ->set('firstName', 'Jan')->set('lastName', 'Jansen')
        ->set('street', 'Straat')->set('houseNr', '1')
        ->set('zipCode', '1234AB')->set('city', 'Stad')->set('country', 'NL')
        ->set('isCompany', true)
        ->set('company', 'Dashed BV')->set('taxId', 'NL123456789B01')
        ->set('note', 'Graag netjes inpakken')
        ->set('marketing', true)
        ->set('invoiceStreet', 'Factuurstraat')->set('invoiceHouseNr', '9')
        ->set('invoiceZipCode', '4321BA')->set('invoiceCity', 'Factuurstad')->set('invoiceCountry', 'NL')
        ->set('paymentMethod', (string) $method->id)
        ->call('submit');

    $order->refresh();
    expect($order->first_name)->toBe('Jan')
        ->and($order->status)->toBe('pending')
        ->and($order->company_name)->toBe('Dashed BV')
        ->and($order->btw_id)->toBe('NL123456789B01')
        ->and($order->note)->toBe('Graag netjes inpakken')
        ->and((bool) $order->marketing)->toBeTrue()
        ->and($order->invoice_street)->toBe('Factuurstraat')
        ->and($order->invoice_city)->toBe('Factuurstad')
        ->and($order->invoice_id)->not->toBeNull();
});
