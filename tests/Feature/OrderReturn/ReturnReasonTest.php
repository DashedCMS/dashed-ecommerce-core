<?php

use Dashed\DashedEcommerceCore\Models\ReturnReason;

it('stores a translatable label and casts is_active', function () {
    $reason = new ReturnReason();
    $reason->setTranslation('label', 'nl', 'Te klein');
    $reason->setTranslation('label', 'en', 'Too small');
    $reason->is_active = true;
    $reason->sort_order = 5;
    $reason->save();

    $fresh = ReturnReason::find($reason->id);
    expect($fresh->getTranslation('label', 'nl'))->toBe('Te klein')
        ->and($fresh->getTranslation('label', 'en'))->toBe('Too small')
        ->and($fresh->is_active)->toBeTrue();
});

it('active scope returns only active reasons ordered by sort_order', function () {
    // Use high sort_order values so the two test rows sort after any seeded defaults.
    $a = ReturnReason::create(['label' => ['nl' => 'B_test'], 'is_active' => true, 'sort_order' => 100]);
    $b = ReturnReason::create(['label' => ['nl' => 'A_test'], 'is_active' => true, 'sort_order' => 99]);
    $inactive = ReturnReason::create(['label' => ['nl' => 'Uit_test'], 'is_active' => false, 'sort_order' => 98]);

    $ids = ReturnReason::active()->pluck('id')->all();

    // The inactive reason must not appear.
    expect($ids)->not->toContain($inactive->id);

    // $b (sort_order 99) must come before $a (sort_order 100).
    $posB = array_search($b->id, $ids);
    $posA = array_search($a->id, $ids);
    expect($posB)->toBeLessThan($posA);
});
