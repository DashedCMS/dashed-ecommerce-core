<?php

uses(\Illuminate\Foundation\Testing\RefreshDatabase::class);

use Dashed\DashedEcommerceCore\Models\ProductFinder;

it('persisteert questions en category_ids als arrays', function () {
    $finder = ProductFinder::create([
        'site_id' => 'default', 'name' => 'Cadeau-finder', 'is_active' => true,
        'questions' => [['label' => 'Voor wie?', 'options' => [['label' => 'Mezelf'], ['label' => 'Cadeau']]]],
        'category_ids' => [1, 2], 'only_in_stock' => true, 'result_count' => 3,
    ]);

    $fresh = $finder->fresh();
    expect($fresh->questions)->toBe([['label' => 'Voor wie?', 'options' => [['label' => 'Mezelf'], ['label' => 'Cadeau']]]]);
    expect($fresh->category_ids)->toBe([1, 2]);
    expect($fresh->only_in_stock)->toBeTrue();
    expect($fresh->result_count)->toBe(3);
});
