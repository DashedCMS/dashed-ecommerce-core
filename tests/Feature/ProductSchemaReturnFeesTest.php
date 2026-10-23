<?php

declare(strict_types=1);

use Dashed\DashedCore\Models\Customsetting;
use Dashed\DashedEcommerceCore\Classes\Seo\ProductSchema;

/**
 * Google meldt "Ontbrekend veld 'returnFees'" zonder dit veld in het
 * retourbeleid van de productgegevens.
 */
it('says the customer pays return shipping by default', function () {
    $policy = ProductSchema::returnPolicy(['NL'])->toArray();

    expect($policy['returnFees'])->toBe('https://schema.org/ReturnFeesCustomerResponsibility');
});

it('marks returns as free when the shop pays', function () {
    Customsetting::set('returns_schema_return_fees', 'free');

    expect(ProductSchema::returnPolicy(['NL'])->toArray()['returnFees'])->toBe('https://schema.org/FreeReturn');
});
