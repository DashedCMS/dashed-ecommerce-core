<?php

require_once __DIR__ . '/../../Support/SpilOrder.php';
require_once __DIR__ . '/../../Support/FreeShippingReversalFixtures.php';

use Dashed\DashedCore\Classes\Sites;
use Dashed\DashedCore\Models\Customsetting;
use Dashed\DashedEcommerceCore\Models\Order;
use Dashed\DashedEcommerceCore\Models\OrderProduct;
use Dashed\DashedEcommerceCore\Services\OrderReturn\FreeShippingReversal;

it('stelt het verzendtarief voor als de rest onder de drempel zakt', function () {
    $f = reversalFixture();

    $proposal = proposeReversal($f, [$f['broek']->id => 1, $f['shirt']->id => 1]);

    expect($proposal)->not->toBeNull()
        ->and($proposal->amount)->toBe(7.95)
        ->and($proposal->threshold)->toBe(60.0)
        ->and($proposal->valueBefore)->toBe(100.0)
        ->and($proposal->valueAfter)->toBe(40.0)
        ->and($proposal->methodName)->toBe('Verzenden');
});

it('stelt niets voor als de rest op of boven de drempel blijft', function () {
    $f = reversalFixture();

    expect(proposeReversal($f, [$f['broek']->id => 1]))->toBeNull()   // rest precies 60
        ->and(proposeReversal($f, [$f['shirt']->id => 1]))->toBeNull(); // rest 80
});

it('stelt niets voor bij een volledige retour', function () {
    $f = reversalFixture();

    expect(proposeReversal($f, [$f['broek']->id => 1, $f['shirt']->id => 3]))->toBeNull();
});

it('stelt niets voor als de instelling uit staat, de datum leeg is of de order ouder is', function () {
    $f = reversalFixture();
    $q = [$f['broek']->id => 1, $f['shirt']->id => 1];

    Customsetting::set('returns_charge_shipping_below_threshold', '0');
    expect(proposeReversal($f, $q))->toBeNull();

    Customsetting::set('returns_charge_shipping_below_threshold', '1');
    Customsetting::reset('returns_charge_shipping_from');
    expect(proposeReversal($f, $q))->toBeNull();

    Customsetting::set('returns_charge_shipping_from', now()->addDay()->toDateString());
    expect(proposeReversal($f, $q))->toBeNull();
});

it('stelt niets voor als de order verzendkosten betaalde of geen gratis methode had', function () {
    $f = reversalFixture();
    $q = [$f['broek']->id => 1, $f['shirt']->id => 1];

    OrderProduct::create(['order_id' => $f['order']->id, 'name' => 'Verzendkosten', 'sku' => 'shipping_costs', 'quantity' => 1, 'price' => 7.95, 'vat_rate' => 21]);
    expect(proposeReversal($f, $q))->toBeNull();

    OrderProduct::where('order_id', $f['order']->id)->where('sku', 'shipping_costs')->delete();
    $f['order']->update(['shipping_method_id' => $f['paid']->id]);
    expect(proposeReversal($f, $q))->toBeNull();
});

// Review Focus 3
it('geeft geen voorstel en geen fout bij een order zonder verzendmethode of met onbekend land', function () {
    $f = reversalFixture();
    $q = [$f['broek']->id => 1, $f['shirt']->id => 1];

    $f['order']->update(['shipping_method_id' => null]);
    expect(proposeReversal($f, $q))->toBeNull();

    $f['order']->update(['shipping_method_id' => $f['free']->id, 'country' => 'Atlantis', 'invoice_country' => null]);
    expect(proposeReversal($f, $q))->toBeNull();
});

// Review Focus 4
it('houdt nooit meer in dan het creditbedrag van de producten', function () {
    $f = reversalFixture();
    // Shirt 3 x 2 = 6, Broek 59: order 65. Alle shirts terug: rest 59, credit 6.
    $f['shirt']->update(['price' => 6]);
    $f['broek']->update(['price' => 59]);
    expect(proposeReversal($f, [$f['shirt']->id => 3])->amount)->toBe(6.0);

    // Shirt 3 x 9 = 27, Broek 50: order 77. Alle shirts terug: rest 50, credit 27.
    $f['shirt']->update(['price' => 27]);
    $f['broek']->update(['price' => 50]);
    expect(proposeReversal($f, [$f['shirt']->id => 3])->amount)->toBe(7.95);
});

// Review Focus 1
it('toetst tegen de regelprijs na korting', function () {
    $f = reversalFixture();
    // 20% kortingscode: Shirt 48, Broek 32, totaal 80. Eén shirt terug: rest 64.
    $f['shirt']->update(['price' => 48, 'discount' => 12]);
    $f['broek']->update(['price' => 32, 'discount' => 8]);

    expect(proposeReversal($f, [$f['shirt']->id => 1]))->toBeNull();          // rest 64
    expect(proposeReversal($f, [$f['shirt']->id => 2])->valueAfter)->toBe(48.0); // rest 48
});

// Review Focus 2
it('telt een eerdere credit via de annuleerknop mee en houdt maar één keer in', function () {
    $f = reversalFixture();

    // Eerdere creditorder voor één shirt, zonder returned_quantity op te hogen.
    $credit = $f['order']->replicate();
    $credit->status = 'return';
    $credit->credit_for_order_id = $f['order']->id;
    $credit->invoice_id = 'CRED-'.uniqid();
    $credit->save();
    OrderProduct::create(['order_id' => $credit->id, 'product_id' => $f['shirt']->product_id, 'name' => $f['shirt']->name, 'sku' => $f['shirt']->sku, 'quantity' => -1, 'price' => -20, 'vat_rate' => 21]);

    // Nu de broek terug: 100 - 20 - 40 = 40, onder de drempel.
    $proposal = proposeReversal($f, [$f['broek']->id => 1]);
    expect($proposal->valueBefore)->toBe(80.0)
        ->and($proposal->valueAfter)->toBe(40.0);

    OrderProduct::create(['order_id' => $credit->id, 'name' => 'Verzendkosten', 'sku' => FreeShippingReversal::SKU, 'quantity' => 1, 'price' => 7.95, 'vat_rate' => 21]);
    expect(app(FreeShippingReversal::class)->alreadyCharged($f['order']->fresh()))->toBeTrue()
        ->and(proposeReversal($f, [$f['broek']->id => 1]))->toBeNull();
});

it('gebruikt de bewaarde drempel, niet een later gewijzigde', function () {
    $f = reversalFixture();
    $f['free']->update(['minimum_order_value' => 30]);

    expect(proposeReversal($f, [$f['broek']->id => 1, $f['shirt']->id => 1])->threshold)->toBe(60.0);
});
