<?php

use Livewire\Livewire;
use Dashed\DashedCore\Models\User;
use Dashed\DashedCore\Models\Customsetting;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Dashed\DashedEcommerceCore\Filament\Pages\Settings\POSSettingsPage;

it('stores the pos_allow_proforma setting', function () {
    Customsetting::set('pos_allow_proforma', true);
    expect((bool) Customsetting::get('pos_allow_proforma', null, false))->toBeTrue();
});

it('defaults pos_allow_proforma to false', function () {
    expect((bool) Customsetting::get('pos_allow_proforma', null, false))->toBeFalse();
});

it('pos_allow_proforma is wired into the POSSettingsPage form schema', function () {
    $this->actingAs(User::factory()->create(['role' => 'superadmin']), 'sanctum');

    // The form loads pos_allow_proforma from Customsetting (default false).
    // assertFormSet verifies the component key is present and bound correctly -
    // a source-string check would pass even if the field were commented out.
    Livewire::test(POSSettingsPage::class)
        ->assertFormSet(['pos_allow_proforma' => false]);
});

it('submit persists pos_allow_proforma when pos_enabled is true', function () {
    $this->actingAs(User::factory()->create(['role' => 'superadmin']), 'sanctum');

    Livewire::test(POSSettingsPage::class)
        ->fillForm([
            'pos_enabled' => true,
            'pos_allow_proforma' => true,
        ])
        ->call('submit');

    expect((bool) Customsetting::get('pos_allow_proforma', null, false))->toBeTrue();
});
