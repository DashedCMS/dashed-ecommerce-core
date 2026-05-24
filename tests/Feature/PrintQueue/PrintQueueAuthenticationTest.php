<?php

declare(strict_types=1);

use Dashed\DashedEcommerceCore\Models\Printer;

it('returns 401 without bearer token', function () {
    $this->getJson('/api/print/pending')->assertStatus(401);
});

it('returns 403 for inactive printer', function () {
    $printer = Printer::factory()->create(['is_active' => false]);
    $token = $printer->createToken('test')->plainTextToken;

    $this->withHeaders(['Authorization' => "Bearer {$token}"])
        ->getJson('/api/print/pending')
        ->assertStatus(403);
});

it('returns 200 for active printer with valid token', function () {
    $printer = Printer::factory()->create(['is_active' => true]);
    $token = $printer->createToken('test')->plainTextToken;

    $this->withHeaders(['Authorization' => "Bearer {$token}"])
        ->getJson('/api/print/pending')
        ->assertStatus(200);
});

it('updates last_ping_at on authenticated request', function () {
    $printer = Printer::factory()->create(['is_active' => true, 'last_ping_at' => null]);
    $token = $printer->createToken('test')->plainTextToken;

    $this->withHeaders(['Authorization' => "Bearer {$token}"])
        ->getJson('/api/print/pending');

    expect($printer->fresh()->last_ping_at)->not->toBeNull();
});
