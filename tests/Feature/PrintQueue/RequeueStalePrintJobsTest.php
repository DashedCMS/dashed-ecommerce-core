<?php

use Dashed\DashedEcommerceCore\Models\PrintJob;
use Dashed\DashedEcommerceCore\Enums\PrintJobType;
use Dashed\DashedEcommerceCore\Enums\PrintJobStatus;

function makePrintJob(PrintJobStatus $status, ?\Carbon\Carbon $claimedAt = null): PrintJob
{
    return PrintJob::create([
        'type' => PrintJobType::PackingSlip,
        'status' => $status,
        'claimed_at' => $claimedAt,
    ]);
}

it('requeues claimed jobs older than the threshold', function () {
    $stale = makePrintJob(PrintJobStatus::Claimed, now()->subMinutes(20));

    $this->artisan('dashed-ecommerce:requeue-stale-print-jobs')->assertSuccessful();

    expect($stale->fresh()->status)->toBe(PrintJobStatus::Pending)
        ->and($stale->fresh()->claimed_at)->toBeNull();
});

it('leaves recently claimed jobs alone', function () {
    $fresh = makePrintJob(PrintJobStatus::Claimed, now()->subMinutes(2));

    $this->artisan('dashed-ecommerce:requeue-stale-print-jobs')->assertSuccessful();

    expect($fresh->fresh()->status)->toBe(PrintJobStatus::Claimed);
});

it('does not touch done or pending jobs', function () {
    $done = makePrintJob(PrintJobStatus::Done, now()->subMinutes(30));
    $pending = makePrintJob(PrintJobStatus::Pending, null);

    $this->artisan('dashed-ecommerce:requeue-stale-print-jobs')->assertSuccessful();

    expect($done->fresh()->status)->toBe(PrintJobStatus::Done)
        ->and($pending->fresh()->status)->toBe(PrintJobStatus::Pending);
});
