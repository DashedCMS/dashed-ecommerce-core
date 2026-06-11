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
    $a = ReturnReason::create(['label' => ['nl' => 'B'], 'is_active' => true, 'sort_order' => 2]);
    $b = ReturnReason::create(['label' => ['nl' => 'A'], 'is_active' => true, 'sort_order' => 1]);
    ReturnReason::create(['label' => ['nl' => 'Uit'], 'is_active' => false, 'sort_order' => 0]);

    $ids = ReturnReason::active()->pluck('id')->all();

    expect($ids)->toBe([$b->id, $a->id]);
});
