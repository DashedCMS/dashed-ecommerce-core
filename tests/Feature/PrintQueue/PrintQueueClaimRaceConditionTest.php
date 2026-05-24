<?php

declare(strict_types=1);

use Dashed\DashedEcommerceCore\Enums\PrintJobStatus;
use Dashed\DashedEcommerceCore\Models\Printer;
use Dashed\DashedEcommerceCore\Models\PrintJob;

it('only one printer wins on parallel claim', function () {
    $printerA = Printer::factory()->create(['is_active' => true]);
    $printerB = Printer::factory()->create(['is_active' => true]);
    $job = PrintJob::factory()->create(['status' => PrintJobStatus::Pending, 'printer_id' => null]);

    $tokenA = $printerA->createToken('a')->plainTextToken;
    $tokenB = $printerB->createToken('b')->plainTextToken;

    $responseA = $this->withHeaders(['Authorization' => "Bearer {$tokenA}"])
        ->postJson("/api/print/{$job->ulid}/claim");

    $responseB = $this->withHeaders(['Authorization' => "Bearer {$tokenB}"])
        ->postJson("/api/print/{$job->ulid}/claim");

    $statusCodes = [$responseA->status(), $responseB->status()];
    sort($statusCodes);

    expect($statusCodes)->toBe([200, 409]);
    expect($job->fresh()->status)->toBe(PrintJobStatus::Claimed);
});

it('returns 409 when claiming already-claimed job', function () {
    $printer = Printer::factory()->create(['is_active' => true]);
    $job = PrintJob::factory()->create([
        'status' => PrintJobStatus::Claimed,
        'printer_id' => $printer->id,
    ]);

    $otherPrinter = Printer::factory()->create(['is_active' => true]);
    $token = $otherPrinter->createToken('x')->plainTextToken;

    $this->withHeaders(['Authorization' => "Bearer {$token}"])
        ->postJson("/api/print/{$job->ulid}/claim")
        ->assertStatus(409);
});
