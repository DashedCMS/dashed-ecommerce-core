<?php

use Dashed\DashedEcommerceCore\Models\PriceGroup;

it('creates a price group with defaults', function () {
    $group = PriceGroup::create(['name' => 'B2B Standaard']);

    expect($group->name)->toBe('B2B Standaard')
        ->and((bool) $group->show_prices_ex_vat)->toBeFalse();
});
