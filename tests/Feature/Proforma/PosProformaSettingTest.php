<?php

use Dashed\DashedCore\Models\Customsetting;
use Dashed\DashedEcommerceCore\Filament\Pages\Settings\POSSettingsPage;

it('stores the pos_allow_proforma setting', function () {
    Customsetting::set('pos_allow_proforma', true);
    expect((bool) Customsetting::get('pos_allow_proforma', null, false))->toBeTrue();
});

it('defaults pos_allow_proforma to false', function () {
    expect((bool) Customsetting::get('pos_allow_proforma', null, false))->toBeFalse();
});

it('pos_allow_proforma toggle is present in the POSSettingsPage form schema', function () {
    $page = new POSSettingsPage();
    $reflection = new ReflectionClass($page);
    $source = file_get_contents($reflection->getFileName());

    expect($source)->toContain("Toggle::make(\"pos_allow_proforma\")")
        ->and($source)->toContain('pos_allow_proforma')
        ->and($source)->toContain('Proforma vanuit POS toestaan');
});

it('submit method persists pos_allow_proforma', function () {
    $reflection = new ReflectionClass(POSSettingsPage::class);
    $source = file_get_contents($reflection->getFileName());

    expect($source)->toContain("Customsetting::set('pos_allow_proforma'");
});
