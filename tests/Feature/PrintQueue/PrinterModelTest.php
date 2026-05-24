<?php

declare(strict_types=1);

use Dashed\DashedEcommerceCore\Enums\PrinterType;
use Dashed\DashedEcommerceCore\Models\Printer;

it('auto fills ulid on creation', function () {
    $printer = Printer::create([
        'name' => 'Pi 1',
        'type' => PrinterType::PackingSlip,
    ]);

    expect($printer->ulid)->toBeString()->toHaveLength(26);
});

it('casts type to enum', function () {
    $printer = Printer::create([
        'name' => 'Pi 1',
        'type' => PrinterType::Both,
    ]);

    expect($printer->fresh()->type)->toBe(PrinterType::Both);
});

it('reports online when last_ping_at is recent', function () {
    $printer = Printer::factory()->create(['last_ping_at' => now()->subSeconds(30)]);

    expect($printer->isOnline())->toBeTrue();
});

it('reports offline when last_ping_at is stale', function () {
    $printer = Printer::factory()->create(['last_ping_at' => now()->subMinutes(5)]);

    expect($printer->isOnline())->toBeFalse();
});

it('scopes active', function () {
    Printer::factory()->create(['is_active' => true]);
    Printer::factory()->create(['is_active' => false]);

    expect(Printer::active()->count())->toBe(1);
});
