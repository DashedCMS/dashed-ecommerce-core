<?php

declare(strict_types=1);

// tests/Feature/Proforma/ProformaPosShippingCostTest.php

use Livewire\Livewire;
use Dashed\DashedCore\Models\User;
use Dashed\DashedCore\Classes\Sites;
use Illuminate\Support\Facades\Mail;
use Dashed\DashedEcommerceCore\Models\POSCart;
use Dashed\DashedEcommerceCore\Models\ShippingZone;
use Dashed\DashedEcommerceCore\Models\ShippingMethod;
use Dashed\DashedEcommerceCore\Classes\ProformaOrderService;
use Dashed\DashedEcommerceCore\Livewire\Frontend\Checkout\ProformaCheckout;

/**
 * Borgt dat een in de POS gekozen verzendmethode ook echt doorberekend wordt in
 * de proforma: de kosten komen in het ordertotaal EN als losse verzendregel.
 * Faalde eerder omdat saveAsConcept alleen shipping_method_id bewaarde en de
 * kosten nergens verrekende, waardoor de klant bij afrekenen geen verzending betaalde.
 */
beforeEach(function () {
    Mail::fake();
});

it('charges the POS-selected shipping cost on the proforma total and adds a shipping line', function () {
    $cashier = User::factory()->create();

    $zone = ShippingZone::create([
        'site_id' => Sites::getActive(),
        'name' => ['nl' => 'Nederland'],
        'zones' => ['Netherlands'],
        'search_fields' => 'Nederland,NL',
    ]);

    $shippingMethod = ShippingMethod::create([
        'shipping_zone_id' => $zone->id,
        'name' => ['nl' => 'PostNL'],
        'costs' => 6.95,
        'sort' => 'static_amount',
        'minimum_order_value' => 0,
        'maximum_order_value' => 1000,
        'order' => 1,
    ]);

    $posCart = POSCart::create([
        'user_id' => $cashier->id,
        'identifier' => 'pos-shipping',
        'email' => 'klant@example.com',
        'first_name' => 'Jan',
        'country' => 'NL',
        'shipping_method_id' => $shippingMethod->id,
        'products' => [[
            'id' => null,
            'name' => 'Maatwerk dienst',
            'price' => 121.0,
            'vat_rate' => 21,
            'quantity' => 1,
            'extra' => [],
            'customProduct' => true,
            'identifier' => 'abc',
        ]],
    ]);

    // allowShipping = false: de klant kiest niets, maar de door de kassa gekozen
    // verzending moet toch doorberekend worden.
    $order = ProformaOrderService::createAndSend($posCart, $cashier, allowShipping: false);

    $shippingLine = $order->orderProducts()->where('sku', 'shipping_costs')->first();

    expect($order->shipping_method_id)->toBe($shippingMethod->id)
        ->and($shippingLine)->not->toBeNull()
        ->and(round((float) $shippingLine->price, 2))->toBe(6.95)
        ->and(round((float) $order->total, 2))->toBe(127.95)
        ->and(round((float) $order->subtotal, 2))->toBe(127.95);
});

/**
 * Anti-dubbeltelling: als de kassa de verzending al vastlegde (kosten staan al in
 * het totaal), mag de checkout GEEN verzendkeuze tonen, ook niet met "Verzending
 * toestaan" aan. Anders zou submit() de verzendkosten een tweede keer optellen.
 */
it('does not show the customer shipping selector when the POS already fixed shipping', function () {
    $cashier = User::factory()->create();

    $zone = ShippingZone::create([
        'site_id' => Sites::getActive(),
        'name' => ['nl' => 'Nederland'],
        'zones' => ['Netherlands'],
        'search_fields' => 'Nederland,NL',
    ]);

    $shippingMethod = ShippingMethod::create([
        'shipping_zone_id' => $zone->id,
        'name' => ['nl' => 'PostNL'],
        'costs' => 6.95,
        'sort' => 'static_amount',
        'minimum_order_value' => 0,
        'maximum_order_value' => 1000,
        'order' => 1,
    ]);

    $posCart = POSCart::create([
        'user_id' => $cashier->id,
        'identifier' => 'pos-shipping-2',
        'email' => 'klant@example.com',
        'first_name' => 'Jan',
        'country' => 'NL',
        'shipping_method_id' => $shippingMethod->id,
        'products' => [[
            'id' => null, 'name' => 'Maatwerk dienst', 'price' => 121.0, 'vat_rate' => 21,
            'quantity' => 1, 'extra' => [], 'customProduct' => true, 'identifier' => 'abc',
        ]],
    ]);

    // allowShipping = true én de kassa koos al verzending: de klant mag niet nóg
    // een keer kiezen.
    $order = ProformaOrderService::createAndSend($posCart, $cashier, allowShipping: true);

    $component = Livewire::test(ProformaCheckout::class, ['orderHash' => $order->hash]);

    expect($component->get('shippingEnabled'))->toBeFalse();
});
