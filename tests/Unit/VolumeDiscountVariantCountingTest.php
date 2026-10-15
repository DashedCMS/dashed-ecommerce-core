<?php

use Dashed\DashedEcommerceCore\Models\ProductGroupVolumeDiscount;

function volumeTier(array $attributes): ProductGroupVolumeDiscount
{
    return (new ProductGroupVolumeDiscount())->forceFill(array_merge([
        'type' => 'percentage',
        'discount_percentage' => 10,
        'discount_price' => 0,
        'min_quantity' => 2,
        'apply_per_set' => false,
        'count_variants_separately' => false,
    ], $attributes));
}

it('telt varianten standaard samen voor de staffel', function () {
    $tier = volumeTier(['min_quantity' => 2]);

    // 1x maat M in een wagen met in totaal 2 boxen (M + L)
    expect(ProductGroupVolumeDiscount::bestFor([$tier], 1, 2))->toBe($tier);
});

it('telt alleen de eigen regel met varianten apart tellen', function () {
    $tier = volumeTier(['min_quantity' => 2, 'count_variants_separately' => true]);

    expect(ProductGroupVolumeDiscount::bestFor([$tier], 1, 2))->toBeNull();
    expect(ProductGroupVolumeDiscount::bestFor([$tier], 2, 2))->toBe($tier);
});

it('kiest de hoogste staffel op basis van het groepstotaal', function () {
    $five = volumeTier(['min_quantity' => 5, 'discount_percentage' => 5]);
    $ten = volumeTier(['min_quantity' => 10, 'discount_percentage' => 11]);

    expect(ProductGroupVolumeDiscount::bestFor([$five, $ten], 3, 6))->toBe($five);
    expect(ProductGroupVolumeDiscount::bestFor([$five, $ten], 1, 10))->toBe($ten);
    expect(ProductGroupVolumeDiscount::bestFor([$five, $ten], 1, 4))->toBeNull();
});

it('valt zonder groepstotaal terug op de regel zelf', function () {
    $tier = volumeTier(['min_quantity' => 2]);

    expect(ProductGroupVolumeDiscount::bestFor([$tier], 1))->toBeNull();
    expect(ProductGroupVolumeDiscount::bestFor([$tier], 2))->toBe($tier);
});

it('geeft elke regel de percentagekorting over het groepstotaal', function () {
    $tier = volumeTier(['min_quantity' => 2, 'discount_percentage' => 10]);

    expect($tier->getLinePrice(50.0, 1, 2))->toEqualWithDelta(45.0, 0.001);
});

it('geeft een vast bedrag per stuk over het groepstotaal', function () {
    $tier = volumeTier(['type' => 'amount', 'discount_price' => 5, 'min_quantity' => 2]);

    expect($tier->getLinePrice(50.0, 1, 2))->toEqualWithDelta(45.0, 0.001);
});

it('rekent per set naar rato over het groepstotaal', function () {
    // 1+1: 50% vanaf 2, per volle set. 3 stuks verdeeld over 2 regels:
    // 2 van de 3 stuks krijgen korting, dus elke regel krijgt 2/3 van de korting.
    $tier = volumeTier(['min_quantity' => 2, 'discount_percentage' => 50, 'apply_per_set' => true]);

    expect($tier->getLinePrice(20.0, 2, 3))->toEqualWithDelta(20.0 - 10.0 * 2 / 3, 0.01);
    expect($tier->getLinePrice(10.0, 1, 3))->toEqualWithDelta(10.0 - 5.0 * 2 / 3, 0.01);
});

it('houdt de oude per-set berekening voor een losse regel', function () {
    $tier = volumeTier(['min_quantity' => 2, 'discount_percentage' => 50, 'apply_per_set' => true]);

    // 3 stuks op één regel: korting op 2, het derde betaalt de volle prijs
    expect($tier->getLinePrice(30.0, 3))->toEqualWithDelta(20.0, 0.001);

    $fixed = volumeTier(['type' => 'amount', 'discount_price' => 5, 'min_quantity' => 2, 'apply_per_set' => true]);
    expect($fixed->getLinePrice(30.0, 3))->toEqualWithDelta(20.0, 0.001);
});
