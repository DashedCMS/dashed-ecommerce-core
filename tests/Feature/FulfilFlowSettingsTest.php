<?php

use Dashed\DashedCore\Models\User;

/**
 * Task 3: `GET|PUT /settings/fulfil-flow` — de per-site geconfigureerde
 * stap-reeks voor de "Afronden"-knop. Zonder opgeslagen reeks geeft GET de
 * standaard-reeks terug; PUT valideert dat elke `key` in de registry bestaat
 * én sequenceable is (dezelfde notie als het catalog-endpoint, Task 2).
 */
it('returns the default sequence when nothing is stored', function () {
    $this->actingAs(User::factory()->create(['role' => 'admin']), 'sanctum');

    $keys = array_column($this->getJson('/api/v1/settings/fulfil-flow', ['X-Site-Id' => 'site'])->json('steps'), 'key');

    expect($keys)->toBe(['mark_packed', 'create_label', 'set_fulfillment_status']);
});

it('stores a valid sequence and reads it back', function () {
    $this->actingAs(User::factory()->create(['role' => 'admin']), 'sanctum');

    $steps = [['key' => 'print_label', 'params' => []], ['key' => 'mark_packed', 'params' => []]];
    $this->putJson('/api/v1/settings/fulfil-flow', ['steps' => $steps], ['X-Site-Id' => 'site'])->assertOk();

    $keys = array_column($this->getJson('/api/v1/settings/fulfil-flow', ['X-Site-Id' => 'site'])->json('steps'), 'key');
    expect($keys)->toBe(['print_label', 'mark_packed']);
});

it('rejects a non-sequenceable or unknown key with 422', function () {
    $this->actingAs(User::factory()->create(['role' => 'admin']), 'sanctum');

    $this->putJson('/api/v1/settings/fulfil-flow', ['steps' => [['key' => 'cancel']]], ['X-Site-Id' => 'site'])->assertStatus(422);
    $this->putJson('/api/v1/settings/fulfil-flow', ['steps' => [['key' => 'zzz']]], ['X-Site-Id' => 'site'])->assertStatus(422);
});

it('accepts an empty steps array (the button then does nothing)', function () {
    $this->actingAs(User::factory()->create(['role' => 'admin']), 'sanctum');

    $res = $this->putJson('/api/v1/settings/fulfil-flow', ['steps' => []], ['X-Site-Id' => 'site']);

    $res->assertOk()->assertJson(['steps' => []]);
});
