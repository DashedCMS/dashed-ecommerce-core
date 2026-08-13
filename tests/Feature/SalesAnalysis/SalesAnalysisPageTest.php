<?php

use Livewire\Livewire;
use Dashed\DashedAi\Facades\Ai;
use Dashed\DashedCore\Models\User;
use Illuminate\Support\Facades\Cache;
use Dashed\DashedEcommerceCore\Tests\Support\AnalysisFixtures;
use Dashed\DashedEcommerceCore\Filament\Pages\Statistics\SalesAnalysisPage;

beforeEach(function () {
    Cache::flush();
    $this->actingAs(User::factory()->create(['role' => 'superadmin']));
    Ai::shouldReceive('hasProvider')->andReturnFalse();
});

it('meldt de acht analyses van fase 1 aan', function () {
    $keys = array_keys(\Dashed\DashedEcommerceCore\Support\Analysis\SalesAnalysisRegistry::map());

    expect($keys)->toContain(
        'kerncijfers',
        'toppers',
        'stijgers-en-dalers',
        'groepen',
        'verloop',
        'niets-verkocht',
        'concentratie',
        'varianten',
    );
});

it('rekent de gekozen periode door en toont de kerncijfers', function () {
    $product = AnalysisFixtures::product('Product A');
    AnalysisFixtures::paidOrder('2026-03-15', [['product' => $product, 'quantity' => 2, 'price' => 80.0]]);

    Livewire::test(SalesAnalysisPage::class)
        ->set('data.startDate', '2026-03-01')
        ->set('data.endDate', '2026-03-31')
        ->call('submit')
        ->assertSet('sections.verkoop.kerncijfers.facts.current.revenue', 80.0);
});

it('toont de kale signalenlijst zonder AI-provider', function () {
    AnalysisFixtures::product('Dure plank', price: 500.0, stock: 10);

    Livewire::test(SalesAnalysisPage::class)
        ->set('data.startDate', '2026-03-01')
        ->set('data.endDate', '2026-03-31')
        ->call('submit')
        ->assertSet('narrative', null)
        ->assertCount('signals', 1);
});

it('laat een gast er niet in', function () {
    auth()->logout();

    expect(SalesAnalysisPage::canAccess())->toBeFalse();
});
