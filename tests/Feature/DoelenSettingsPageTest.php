<?php

use Livewire\Livewire;
use Dashed\DashedCore\Models\User;
use Dashed\DashedCore\Models\Customsetting;
use Dashed\DashedEcommerceCore\Filament\Pages\Settings\DoelenSettingsPage;

it('persists revenue and orders targets for all periods', function () {
    $this->actingAs(User::factory()->create(['role' => 'superadmin']), 'sanctum');

    Livewire::test(DoelenSettingsPage::class)
        ->fillForm([
            'revenue_target_today' => 500,
            'orders_target_today' => 10,
            'revenue_target_week' => 3000,
            'orders_target_week' => 60,
            'revenue_target_month' => 12000,
            'orders_target_month' => 240,
            'revenue_target_year' => 150000,
            'orders_target_year' => 3000,
        ])
        ->call('submit');

    expect((float) Customsetting::get('dashboard_revenue_target_today'))->toBe(500.0)
        ->and((int) Customsetting::get('dashboard_orders_target_today'))->toBe(10)
        ->and((float) Customsetting::get('dashboard_revenue_target_month'))->toBe(12000.0)
        ->and((int) Customsetting::get('dashboard_orders_target_year'))->toBe(3000);
});

it('loads existing targets into the form and treats empty as zero', function () {
    $this->actingAs(User::factory()->create(['role' => 'superadmin']), 'sanctum');
    Customsetting::set('dashboard_revenue_target_month', 9999, 'site');

    Livewire::test(DoelenSettingsPage::class)
        ->assertFormSet(['revenue_target_month' => 9999.0]);

    Livewire::test(DoelenSettingsPage::class)
        ->fillForm(['revenue_target_month' => null])
        ->call('submit');

    expect((float) Customsetting::get('dashboard_revenue_target_month'))->toBe(0.0);
});
