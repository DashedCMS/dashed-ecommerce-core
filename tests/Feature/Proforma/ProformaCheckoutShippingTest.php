<?php

declare(strict_types=1);

// tests/Feature/Proforma/ProformaCheckoutShippingTest.php

use Livewire\Livewire;
use Dashed\DashedCore\Classes\Sites;
use Illuminate\Support\Facades\Mail;
use Dashed\DashedEcommerceCore\Models\Order;
use Dashed\DashedEcommerceCore\Models\ShippingZone;
use Dashed\DashedEcommerceCore\Models\ShippingMethod;
use Dashed\DashedEcommerceCore\Livewire\Frontend\Checkout\ProformaCheckout;

function makeShippingProforma(float $total): Order
{
    $order = Order::create([
        'email' => 'klant@example.com',
        'status' => Order::STATUS_CONCEPT,
        'is_proforma' => true,
        'proforma_allow_shipping' => true,
        'country' => 'NL',
        'total' => $total,
        'subtotal' => $total,
        'btw' => round($total - ($total / 1.21), 2),
    ]);
    $order->orderProducts()->create([
        'product_id' => null, 'name' => 'Maatwerk', 'quantity' => 1, 'price' => $total, 'vat_rate' => 21,
    ]);

    return $order;
}

function makeProformaNlShippingMethod(float $costs): ShippingMethod
{
    $zone = ShippingZone::create([
        'site_id' => Sites::getActive(),
        'name' => ['nl' => 'Nederland'],
        'zones' => ['Netherlands'],
        'search_fields' => 'Nederland,NL',
    ]);

    // minimum_order_value boven 0 zodat de methode alleen verschijnt wanneer er
    // tegen de echte proforma-total (121) gefilterd wordt en niet tegen een lege
    // sessie-cart (0). Zo bewijst de test de cartless filtering.
    return ShippingMethod::create([
        'shipping_zone_id' => $zone->id,
        'name' => ['nl' => 'PostNL'],
        'costs' => $costs,
        'sort' => 'static_amount',
        'minimum_order_value' => 100,
        'maximum_order_value' => 200,
        'order' => 1,
    ]);
}

beforeEach(function () {
    Mail::fake();
});

it('lists shipping methods for the proforma total without a session cart', function () {
    $order = makeShippingProforma(121.0);
    makeProformaNlShippingMethod(6.95);

    $component = Livewire::test(ProformaCheckout::class, ['orderHash' => $order->hash]);

    expect(collect($component->get('shippingMethods')))->toHaveCount(1);
});

it('charges the customer for shipping by raising the order total on submit', function () {
    $order = makeShippingProforma(121.0);
    $method = makeProformaNlShippingMethod(6.95);

    Livewire::test(ProformaCheckout::class, ['orderHash' => $order->hash])
        ->set('firstName', 'Jan')->set('lastName', 'Jansen')
        ->set('street', 'Straat')->set('houseNr', '1')
        ->set('zipCode', '1234AB')->set('city', 'Stad')->set('country', 'NL')
        ->set('shippingMethod', (string) $method->id)
        ->call('submit');

    $order->refresh();

    expect((float) $order->total)->toEqual(127.95)
        ->and((float) $order->subtotal)->toEqual(127.95)
        ->and($order->orderProducts()->where('sku', 'shipping_costs')->count())->toBe(1);
});
