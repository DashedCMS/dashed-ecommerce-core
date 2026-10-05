<?php

use Dashed\DashedEcommerceCore\Filament\Pages\Settings\VATSettingsPage;

it('bewaart alleen geldige EU-landcodes met een numeriek tarief', function () {
    $rates = VATSettingsPage::normalizeRates([
        'de' => '19',
        'FI' => '25,5',
        'XX' => '10',
        'BE' => 'abc',
        'FR' => 20,
    ]);

    expect($rates)->toBe(['DE' => 19.0, 'FI' => 25.5, 'FR' => 20.0]);
});
