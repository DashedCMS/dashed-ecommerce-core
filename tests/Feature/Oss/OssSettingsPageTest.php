<?php

use Dashed\DashedCore\Models\Customsetting;
use Dashed\DashedEcommerceCore\Classes\OssVat;
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

it('bewaart bij alleen standaardtarieven geen afwijkingen', function () {
    $rates = array_map(fn ($rate) => (string) $rate, OssVat::STANDARD_RATES);

    expect(VATSettingsPage::overridesOnly($rates))->toBe([]);
});

it('bewaart alleen de tarieven die van de standaard afwijken', function () {
    $rates = array_map(fn ($rate) => (string) $rate, OssVat::STANDARD_RATES);
    $rates['DE'] = '20';

    expect(VATSettingsPage::overridesOnly($rates))->toBe(['DE' => 20.0]);
});

it('laat een nieuw standaardtarief doorwerken na opslaan van alleen afwijkingen', function () {
    OssVat::flush();
    $rates = array_map(fn ($rate) => (string) $rate, OssVat::STANDARD_RATES);
    $rates['DE'] = '20';

    Customsetting::set('oss_vat_rates', json_encode(VATSettingsPage::overridesOnly($rates), JSON_FORCE_OBJECT));

    expect(OssVat::rates()['DE'])->toBe(20.0)
        ->and(OssVat::rates()['FR'])->toBe((float) OssVat::STANDARD_RATES['FR']);

    // Terugzetten op de standaard wist de afwijking.
    $rates['DE'] = (string) OssVat::STANDARD_RATES['DE'];
    Customsetting::set('oss_vat_rates', json_encode(VATSettingsPage::overridesOnly($rates), JSON_FORCE_OBJECT));

    expect(OssVat::rates())->toEqual(array_map('floatval', OssVat::STANDARD_RATES));
});
