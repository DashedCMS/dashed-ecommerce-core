<?php

declare(strict_types=1);

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Cache;
use Dashed\DashedEcommerceCore\Helpers\FreeShippingHelper;

beforeEach(function () {
    Cache::forget('free-shipping-method');
    $this->helper = new FreeShippingHelper();
});

function createFreeDeliveryMethod(float $minimumOrderValue): void
{
    DB::table('dashed__shipping_zones')->insertOrIgnore([
        'id' => 1,
        'site_id' => 'default',
        'name' => json_encode(['nl' => 'Test zone']),
        'zones' => json_encode(['NL']),
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    DB::table('dashed__shipping_methods')->insert([
        'shipping_zone_id' => 1,
        'name' => json_encode(['nl' => 'Gratis verzending']),
        'sort' => 'free_delivery',
        'minimum_order_value' => $minimumOrderValue,
        'maximum_order_value' => 9999,
        'costs' => 0,
        'order' => 1,
        'created_at' => now(),
        'updated_at' => now(),
    ]);
}

it('reads threshold from free_delivery shipping method', function () {
    createFreeDeliveryMethod(75.00);

    expect($this->helper->threshold())->toBe(75.0);
});

it('returns 0.0 when no free_delivery method and no translation', function () {
    expect($this->helper->threshold())->toBe(0.0);
});

it('progress returns 0% and full gap when far under threshold', function () {
    createFreeDeliveryMethod(100.00);

    $progress = $this->helper->progress(0.0);

    expect($progress['gap'])->toBe(100.0);
    expect($progress['percentage'])->toBe(0);
    expect($progress['reached'])->toBeFalse();
});

it('progress returns correct values when partway under', function () {
    createFreeDeliveryMethod(100.00);

    $progress = $this->helper->progress(75.00);

    expect($progress['gap'])->toBe(25.0);
    expect($progress['percentage'])->toBe(75);
    expect($progress['reached'])->toBeFalse();
});

it('progress returns 100% reached when exactly at threshold', function () {
    createFreeDeliveryMethod(100.00);

    $progress = $this->helper->progress(100.00);

    expect($progress['gap'])->toBe(0.0);
    expect($progress['percentage'])->toBe(100);
    expect($progress['reached'])->toBeTrue();
});

it('progress returns 100% reached when over threshold', function () {
    createFreeDeliveryMethod(100.00);

    $progress = $this->helper->progress(150.00);

    expect($progress['gap'])->toBe(0.0);
    expect($progress['percentage'])->toBe(100);
    expect($progress['reached'])->toBeTrue();
});

it('progress treats threshold of 0 as always reached', function () {
    $progress = $this->helper->progress(0.0);

    expect($progress['gap'])->toBe(0.0);
    expect($progress['reached'])->toBeTrue();
});
