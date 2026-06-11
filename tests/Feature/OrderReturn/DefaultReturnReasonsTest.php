<?php

use Dashed\DashedEcommerceCore\Models\ReturnReason;
use Dashed\DashedEcommerceCore\Support\DefaultReturnReasons;

it('seeds default reasons idempotently', function () {
    DefaultReturnReasons::seed();
    $afterFirst = ReturnReason::count();

    DefaultReturnReasons::seed();
    $afterSecond = ReturnReason::count();

    expect($afterFirst)->toBeGreaterThanOrEqual(count(DefaultReturnReasons::defaults()))
        ->and($afterSecond)->toBe($afterFirst);

    $labels = ReturnReason::get()->map(fn ($r) => $r->getTranslation('label', 'nl'))->all();
    expect($labels)->toContain('Te klein');
});
