<?php

use Dashed\DashedCore\Models\User;
use Dashed\DashedEcommerceCore\Models\Order;

function makeEditableOrder(array $attributes = [], string $siteId = 'site'): Order
{
    return Order::create(array_merge([
        'site_id' => $siteId,
        'email' => 'klant@example.com',
        'invoice_id' => 'INV-' . strtoupper(uniqid()),
        'status' => 'paid',
        'first_name' => 'Oud',
        'last_name' => 'Achternaam',
        'city' => 'Amsterdam',
    ], $attributes));
}

it('bewerkt klant- en adresgegevens van een order', function () {
    $this->actingAs(User::factory()->create(['role' => 'admin']), 'sanctum');
    $order = makeEditableOrder();

    $res = $this->patchJson("/api/v1/orders/{$order->id}/details", [
        'first_name' => 'Nieuw',
        'city' => 'Zwolle',
    ], ['X-Site-Id' => 'site']);

    $res->assertOk()
        ->assertJsonPath('data.first_name', 'Nieuw')
        ->assertJsonPath('data.city', 'Zwolle');

    $fresh = $order->fresh();
    expect($fresh->first_name)->toBe('Nieuw')
        ->and($fresh->city)->toBe('Zwolle')
        ->and($fresh->last_name)->toBe('Achternaam'); // niet meegestuurde velden blijven ongewijzigd
});

it('bewerkt factuuradres en bedrijfsgegevens onafhankelijk van het verzendadres', function () {
    $this->actingAs(User::factory()->create(['role' => 'admin']), 'sanctum');
    $order = makeEditableOrder();

    $res = $this->patchJson("/api/v1/orders/{$order->id}/details", [
        'company_name' => 'Acme B.V.',
        'btw_id' => 'NL123456789B01',
        'note' => 'Bellen voor levering',
        'invoice_street' => 'Factuurstraat',
        'invoice_house_nr' => '1',
        'invoice_zip_code' => '1000AA',
        'invoice_city' => 'Rotterdam',
        'invoice_country' => 'NL',
    ], ['X-Site-Id' => 'site']);

    $res->assertOk();

    $fresh = $order->fresh();
    expect($fresh->company_name)->toBe('Acme B.V.')
        ->and($fresh->btw_id)->toBe('NL123456789B01')
        ->and($fresh->note)->toBe('Bellen voor levering')
        ->and($fresh->invoice_street)->toBe('Factuurstraat')
        ->and($fresh->invoice_city)->toBe('Rotterdam');
});

it('valideert email en weigert onbekende velden niet stilzwijgend te accepteren als leeg', function () {
    $this->actingAs(User::factory()->create(['role' => 'admin']), 'sanctum');
    $order = makeEditableOrder();

    $this->patchJson("/api/v1/orders/{$order->id}/details", [
        'email' => 'niet-een-email',
    ], ['X-Site-Id' => 'site'])->assertStatus(422);
});

it('returns 404 voor een order op een andere site', function () {
    $this->actingAs(User::factory()->create(['role' => 'admin']), 'sanctum');
    $other = makeEditableOrder([], 'other');

    $this->patchJson("/api/v1/orders/{$other->id}/details", [
        'first_name' => 'Nieuw',
    ], ['X-Site-Id' => 'site'])->assertNotFound();
});

it('weigert bewerken zonder de orders.write ability', function () {
    $this->actingAs(User::factory()->create(['role' => 'customer']), 'sanctum');
    $order = makeEditableOrder();

    $this->patchJson("/api/v1/orders/{$order->id}/details", [
        'first_name' => 'Nieuw',
    ], ['X-Site-Id' => 'site'])->assertStatus(403);
});
