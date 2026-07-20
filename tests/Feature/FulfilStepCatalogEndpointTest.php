<?php

use Dashed\DashedCore\Models\User;

/**
 * Task 2: `GET order-actions/catalog` geeft de configureerbare, sequenceable
 * fulfilment-stappen terug — ongeacht hun `visible` (die is er juist om ze
 * uit de per-order actielijst te houden, niet uit de catalogus).
 */
it('lists the seven sequenceable fulfil steps regardless of visible, with a filled status select', function () {
    $this->actingAs(User::factory()->create(['role' => 'admin']), 'sanctum');

    $res = $this->getJson('/api/v1/order-actions/catalog', ['X-Site-Id' => 'site']);

    $res->assertOk();

    $data = collect($res->json('data'));

    foreach (['mark_packed', 'create_label', 'print_label', 'print_packing_slip', 'print_invoice', 'set_fulfillment_status', 'mark_paid'] as $key) {
        expect($data->firstWhere('key', $key))->not->toBeNull("ontbreekt: {$key}");
    }

    // Niet-sequenceable acties (zoals 'cancel') horen niet in de catalogus.
    expect($data->firstWhere('key', 'cancel'))->toBeNull();

    $statusAction = $data->firstWhere('key', 'set_fulfillment_status');
    expect($statusAction['fields'])->toBeArray()->not->toBeEmpty();

    $statusField = collect($statusAction['fields'])->firstWhere('name', 'status');
    expect($statusField)->not->toBeNull()
        ->and($statusField['type'])->toBe('select')
        ->and($statusField['options'])->toBeArray()->not->toBeEmpty();
});

it('exposes key/label/group/icon for every catalog entry', function () {
    $this->actingAs(User::factory()->create(['role' => 'admin']), 'sanctum');

    $res = $this->getJson('/api/v1/order-actions/catalog', ['X-Site-Id' => 'site']);

    $res->assertOk();

    foreach ($res->json('data') as $entry) {
        expect($entry)->toHaveKeys(['key', 'label', 'group', 'icon', 'fields']);
    }
});
