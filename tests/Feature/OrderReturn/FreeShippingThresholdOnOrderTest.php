<?php

use Dashed\DashedEcommerceCore\Classes\SKUs;
use Illuminate\Support\Facades\Schema;

it('heeft een kolom voor de drempel en bewaart hem als getal', function () {
    expect(Schema::hasColumn('dashed__orders', 'free_shipping_threshold'))->toBeTrue();

    $f = spilOrder();
    $f['order']->free_shipping_threshold = 60;
    $f['order']->save();

    expect((float) $f['order']->fresh()->free_shipping_threshold)->toBe(60.0);
});

it('behandelt de inhoudregel als niet retourneerbaar en verbergt hem op de pakbon', function () {
    expect(SKUs::nonReturnable())->toContain('shipping_costs_reversal')
        ->and(SKUs::hideOnPackingSlip())->toContain('shipping_costs_reversal');
});
