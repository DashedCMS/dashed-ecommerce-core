<?php

use Dashed\DashedCore\Models\User;
use Dashed\DashedEcommerceCore\Models\Order;

/**
 * Order-zoek in de app matcht ook op adres/contact (verzend- én factuuradres),
 * niet alleen klantnaam/e-mail/factuurnummer.
 */
function seedOrder(array $attrs): Order
{
    return Order::create(array_merge([
        'site_id' => 'default',
        'email' => 'klant@example.com',
        'invoice_id' => 'INV-' . strtoupper(uniqid()),
        'status' => 'paid',
    ], $attrs));
}

function searchOrderIds(object $test, string $q): array
{
    return collect($test->getJson('/api/v1/orders?search=' . urlencode($q), ['X-Site-Id' => 'default'])->json('data'))
        ->pluck('id')->all();
}

it('vindt een order op straat, postcode en plaats', function () {
    $this->actingAs(User::factory()->create(['role' => 'admin']), 'sanctum');
    $hit = seedOrder(['first_name' => 'Anna', 'street' => 'Kerkstraat', 'house_nr' => '12', 'zip_code' => '2611AB', 'city' => 'Delft']);
    $miss = seedOrder(['first_name' => 'Bram', 'street' => 'Molenweg', 'zip_code' => '9999ZZ', 'city' => 'Assen']);

    expect(searchOrderIds($this, 'Kerkstraat'))->toContain($hit->id)->not->toContain($miss->id);
    expect(searchOrderIds($this, '2611'))->toContain($hit->id)->not->toContain($miss->id);
    expect(searchOrderIds($this, 'Delft'))->toContain($hit->id)->not->toContain($miss->id);
});

it('vindt een order op het factuuradres', function () {
    $this->actingAs(User::factory()->create(['role' => 'admin']), 'sanctum');
    $hit = seedOrder(['invoice_city' => 'Groningen', 'invoice_zip_code' => '9711LM']);

    expect(searchOrderIds($this, 'Groningen'))->toContain($hit->id);
    expect(searchOrderIds($this, '9711LM'))->toContain($hit->id);
});

it('houdt multi-term AND aan (adres + naam samen)', function () {
    $this->actingAs(User::factory()->create(['role' => 'admin']), 'sanctum');
    $hit = seedOrder(['first_name' => 'Anna', 'city' => 'Delft']);
    $miss = seedOrder(['first_name' => 'Anna', 'city' => 'Assen']);

    expect(searchOrderIds($this, 'Anna Delft'))->toContain($hit->id)->not->toContain($miss->id);
});
