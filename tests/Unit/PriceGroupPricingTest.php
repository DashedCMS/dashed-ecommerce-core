<?php

use Dashed\DashedCore\Models\User;
use Dashed\DashedEcommerceCore\Models\PriceGroup;

it('creates a price group with defaults', function () {
    $group = PriceGroup::create(['name' => 'B2B Standaard']);

    expect($group->name)->toBe('B2B Standaard')
        ->and((bool) $group->show_prices_ex_vat)->toBeFalse();
});

it('links a user to a price group', function () {
    $group = PriceGroup::create(['name' => 'Groothandel']);
    $user = User::factory()->create(['price_group_id' => $group->id]);

    expect($user->priceGroup->id)->toBe($group->id)
        ->and($group->fresh()->users)->toHaveCount(1);
});
