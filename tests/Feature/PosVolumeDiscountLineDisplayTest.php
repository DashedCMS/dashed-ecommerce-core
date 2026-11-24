<?php

use Dashed\DashedEcommerceCore\Controllers\Api\PointOfSale\PointOfSaleApiController;

it('toont de regelprijs na staffelkorting met de oude prijs doorgestreept', function () {
    $products = [
        ['identifier' => 'a', 'price' => 31.82, 'vat_rate' => 21, 'priceFormattedPrimary' => 'oud'],
        ['identifier' => 'b', 'price' => 16.22, 'vat_rate' => 21, 'priceFormattedPrimary' => 'ongewijzigd'],
    ];
    $lines = [
        ['source_identifier' => 'a', 'line_total_without_discount' => 28.64],
        ['source_identifier' => 'b', 'line_total_without_discount' => 16.22],
    ];

    $result = (new PointOfSaleApiController())->applyEffectiveLinePrices($products, $lines, 'incl');

    expect($result[0]['priceFormattedPrimary'])->toContain('28,64')
        ->and($result[0]['priceFormattedOriginal'])->toContain('31,82')
        ->and($result[1]['priceFormattedPrimary'])->toBe('ongewijzigd')
        ->and($result[1]['priceFormattedOriginal'])->toBeNull();
});

it('toont in ex-modus het ex-bedrag na staffelkorting', function () {
    $products = [['identifier' => 'a', 'price' => 121.0, 'vat_rate' => 21]];
    $lines = [['source_identifier' => 'a', 'line_total_without_discount' => 108.9]];

    $result = (new PointOfSaleApiController())->applyEffectiveLinePrices($products, $lines, 'ex');

    expect($result[0]['priceFormattedPrimary'])->toContain('90')
        ->and($result[0]['priceFormattedOriginal'])->toContain('100');
});
