<?php

use App\Models\User;
use Livewire\Livewire;
use Dashed\DashedEcommerceCore\Models\ProductGroup;
use Dashed\DashedEcommerceCore\Filament\Widgets\Product\ProductGroupOpenOrdersWidget;

uses(\Illuminate\Foundation\Testing\RefreshDatabase::class);

it('polls the open-orders widget so the count updates live', function () {
    $this->actingAs(User::factory()->create(['role' => 'admin']));

    $group = ProductGroup::create([
        'name' => ['en' => 'Groep'],
        'slug' => ['en' => 'groep-' . uniqid()],
        'short_description' => ['en' => ''],
        'description' => ['en' => ''],
        'content' => ['en' => ''],
        'search_terms' => ['en' => ''],
        'site_ids' => ['default'],
    ]);

    $widget = Livewire::test(ProductGroupOpenOrdersWidget::class, ['record' => $group]);

    expect($widget->instance()->getTable()->getPollingInterval())->toBe('10s');
});
