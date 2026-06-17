<?php

use Dashed\DashedCore\Models\User;
use Dashed\DashedEcommerceCore\Models\DiscountCode;

/**
 * Mobile-api kortingscode-CRUD. Auth via Sanctum (acting-as) + de actieve site
 * via de X-Site-Id-header ('site'), zoals de overige mobile-api feature-tests.
 * Een admin-rol krijgt alle abilities (waaronder discounts.read/write).
 */
function makeDiscountCode(array $attributes = []): DiscountCode
{
    return DiscountCode::withoutEvents(fn () => DiscountCode::create(array_merge([
        'site_ids' => ['site'],
        'name' => 'Korting',
        'code' => 'CODE' . strtoupper(uniqid()),
        'type' => 'percentage',
        'discount_percentage' => 10,
        'use_stock' => false,
        'stock_used' => 0,
    ], $attributes)));
}

it('lists only discount codes for the active site, newest first', function () {
    $this->actingAs(User::factory()->create(['role' => 'admin']), 'sanctum');

    $first = makeDiscountCode(['name' => 'Eerste']);
    $second = makeDiscountCode(['name' => 'Tweede']);
    makeDiscountCode(['name' => 'Andere site', 'site_ids' => ['other']]);

    $response = $this->getJson('/api/v1/discounts', ['X-Site-Id' => 'site']);

    $response->assertOk();
    $ids = collect($response->json('data'))->pluck('id')->all();

    expect($ids)->toBe([$second->id, $first->id]);
});

it('filters the list by search on name and code', function () {
    $this->actingAs(User::factory()->create(['role' => 'admin']), 'sanctum');

    makeDiscountCode(['name' => 'Zomeractie', 'code' => 'ZOMER']);
    makeDiscountCode(['name' => 'Winteractie', 'code' => 'WINTER']);

    $response = $this->getJson('/api/v1/discounts?search=zomer', ['X-Site-Id' => 'site']);

    $response->assertOk();
    expect($response->json('data'))->toHaveCount(1)
        ->and($response->json('data.0.code'))->toBe('ZOMER');
});

it('creates a percentage discount code', function () {
    $this->actingAs(User::factory()->create(['role' => 'admin']), 'sanctum');

    $response = $this->postJson('/api/v1/discounts', [
        'name' => 'Nieuwe korting',
        'code' => 'NIEUW10',
        'type' => 'percentage',
        'discount_percentage' => 10,
    ], ['X-Site-Id' => 'site']);

    $response->assertStatus(201)
        ->assertJsonPath('data.name', 'Nieuwe korting')
        ->assertJsonPath('data.code', 'NIEUW10')
        ->assertJsonPath('data.type', 'percentage')
        ->assertJsonPath('data.is_active', true);

    expect((float) $response->json('data.discount_percentage'))->toBe(10.0);

    $model = DiscountCode::where('code', 'NIEUW10')->first();
    expect($model)->not->toBeNull()
        ->and($model->site_ids)->toBe(['site']);
});

it('creates an amount discount code', function () {
    $this->actingAs(User::factory()->create(['role' => 'admin']), 'sanctum');

    $response = $this->postJson('/api/v1/discounts', [
        'name' => 'Tientje',
        'code' => 'TIENTJE',
        'type' => 'amount',
        'discount_amount' => 10,
    ], ['X-Site-Id' => 'site']);

    $response->assertStatus(201)
        ->assertJsonPath('data.discount_percentage', null);

    expect((float) $response->json('data.discount_amount'))->toBe(10.0);
});

it('rejects a duplicate code', function () {
    $this->actingAs(User::factory()->create(['role' => 'admin']), 'sanctum');
    makeDiscountCode(['code' => 'DUPLICATE']);

    $this->postJson('/api/v1/discounts', [
        'name' => 'Dubbel',
        'code' => 'DUPLICATE',
        'type' => 'percentage',
        'discount_percentage' => 5,
    ], ['X-Site-Id' => 'site'])
        ->assertStatus(422)
        ->assertJsonValidationErrors(['code']);
});

it('rejects a percentage above 100', function () {
    $this->actingAs(User::factory()->create(['role' => 'admin']), 'sanctum');

    $this->postJson('/api/v1/discounts', [
        'name' => 'Teveel',
        'code' => 'TEVEEL',
        'type' => 'percentage',
        'discount_percentage' => 150,
    ], ['X-Site-Id' => 'site'])
        ->assertStatus(422)
        ->assertJsonValidationErrors(['discount_percentage']);
});

it('requires stock when use_stock is true', function () {
    $this->actingAs(User::factory()->create(['role' => 'admin']), 'sanctum');

    $this->postJson('/api/v1/discounts', [
        'name' => 'Voorraad',
        'code' => 'VOORRAAD',
        'type' => 'percentage',
        'discount_percentage' => 5,
        'use_stock' => true,
    ], ['X-Site-Id' => 'site'])
        ->assertStatus(422)
        ->assertJsonValidationErrors(['stock']);
});

it('updates a discount code', function () {
    $this->actingAs(User::factory()->create(['role' => 'admin']), 'sanctum');
    $code = makeDiscountCode(['name' => 'Oud', 'code' => 'OUD']);

    $response = $this->putJson("/api/v1/discounts/{$code->id}", [
        'name' => 'Bijgewerkt',
        'code' => 'OUD',
        'type' => 'percentage',
        'discount_percentage' => 25,
    ], ['X-Site-Id' => 'site']);

    $response->assertOk()
        ->assertJsonPath('data.name', 'Bijgewerkt');

    expect((float) $response->json('data.discount_percentage'))->toBe(25.0)
        ->and((float) $code->fresh()->discount_percentage)->toBe(25.0);
});

it('deletes a discount code', function () {
    $this->actingAs(User::factory()->create(['role' => 'admin']), 'sanctum');
    $code = makeDiscountCode();

    $this->deleteJson("/api/v1/discounts/{$code->id}", [], ['X-Site-Id' => 'site'])
        ->assertOk()
        ->assertJson(['success' => true]);

    expect(DiscountCode::find($code->id))->toBeNull();
});

it('does not find a discount code from another site', function () {
    $this->actingAs(User::factory()->create(['role' => 'admin']), 'sanctum');
    $code = makeDiscountCode(['site_ids' => ['other']]);

    $this->getJson("/api/v1/discounts/{$code->id}", ['X-Site-Id' => 'site'])
        ->assertStatus(404);
});

it('rejects discount writes without the discounts.write ability', function () {
    $this->actingAs(User::factory()->create(['role' => 'customer']), 'sanctum');

    $this->postJson('/api/v1/discounts', [
        'name' => 'Nope',
        'code' => 'NOPE',
        'type' => 'percentage',
        'discount_percentage' => 5,
    ], ['X-Site-Id' => 'site'])
        ->assertStatus(403);
});
