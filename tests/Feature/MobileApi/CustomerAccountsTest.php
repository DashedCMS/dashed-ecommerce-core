<?php

use Dashed\DashedCore\Models\User;

/**
 * Account-zoek voor het koppelen van een klant aan de kassa-bon: geeft échte
 * user-accounts met hun id terug (i.t.t. de e-mail-aggregatie van /customers).
 */
it('zoekt klant-accounts op naam en geeft de user-id terug', function () {
    $this->actingAs(User::factory()->create(['role' => 'admin']), 'sanctum');
    $klant = User::factory()->create(['first_name' => 'Vera', 'last_name' => 'Jansen', 'email' => 'vera@example.com']);
    User::factory()->create(['first_name' => 'Piet', 'last_name' => 'Klaassen', 'email' => 'piet@example.com']);

    $data = $this->getJson('/api/v1/customers/accounts?search=Vera', ['X-Site-Id' => 'default'])
        ->assertOk()
        ->json('data');

    expect(collect($data)->pluck('id'))->toContain($klant->id)
        ->and(collect($data)->firstWhere('id', $klant->id)['email'])->toBe('vera@example.com');
});

it('geeft een lege lijst bij een te korte zoekterm', function () {
    $this->actingAs(User::factory()->create(['role' => 'admin']), 'sanctum');

    $this->getJson('/api/v1/customers/accounts?search=a', ['X-Site-Id' => 'default'])
        ->assertOk()
        ->assertJsonPath('data', []);
});
