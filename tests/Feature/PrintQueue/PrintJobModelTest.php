<?php

declare(strict_types=1);

use Dashed\DashedEcommerceCore\Enums\PrintJobStatus;
use Dashed\DashedEcommerceCore\Enums\PrintJobType;
use Dashed\DashedEcommerceCore\Models\Printer;
use Dashed\DashedEcommerceCore\Models\PrintJob;

it('auto fills ulid on creation', function () {
    $job = PrintJob::factory()->create();

    expect($job->ulid)->toBeString()->toHaveLength(26);
});

it('marks as claimed', function () {
    $printer = Printer::factory()->create();
    $job = PrintJob::factory()->create(['status' => PrintJobStatus::Pending]);

    $job->markAsClaimed($printer);
    $job->refresh();

    expect($job->status)->toBe(PrintJobStatus::Claimed)
        ->and($job->printer_id)->toBe($printer->id)
        ->and($job->attempts)->toBe(1)
        ->and($job->claimed_at)->not->toBeNull();
});

it('marks as done and sets printed_at', function () {
    $job = PrintJob::factory()->create(['status' => PrintJobStatus::Claimed]);

    $job->markAsDone();
    $job->refresh();

    expect($job->status)->toBe(PrintJobStatus::Done)
        ->and($job->printed_at)->not->toBeNull();
});

it('marks as failed with error message', function () {
    $job = PrintJob::factory()->create(['status' => PrintJobStatus::Claimed]);

    $job->markAsFailed('CUPS error 42');
    $job->refresh();

    expect($job->status)->toBe(PrintJobStatus::Failed)
        ->and($job->error_message)->toBe('CUPS error 42')
        ->and($job->failed_at)->not->toBeNull();
});

it('retry resets to pending and clears printer + timestamps', function () {
    $printer = Printer::factory()->create();
    $job = PrintJob::factory()->create([
        'status' => PrintJobStatus::Failed,
        'printer_id' => $printer->id,
        'error_message' => 'boom',
        'failed_at' => now(),
        'claimed_at' => now()->subMinutes(5),
    ]);

    $job->retry();
    $job->refresh();

    expect($job->status)->toBe(PrintJobStatus::Pending)
        ->and($job->printer_id)->toBeNull()
        ->and($job->error_message)->toBeNull()
        ->and($job->failed_at)->toBeNull()
        ->and($job->claimed_at)->toBeNull();
});
