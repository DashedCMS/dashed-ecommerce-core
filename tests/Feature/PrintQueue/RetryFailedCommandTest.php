<?php

declare(strict_types=1);

use Dashed\DashedEcommerceCore\Models\Printer;
use Dashed\DashedEcommerceCore\Models\PrintJob;
use Dashed\DashedEcommerceCore\Enums\PrintJobStatus;
use Dashed\DashedEcommerceCore\Commands\PrintQueue\RetryFailedPrintJobsCommand;

it('promotes failed job back to pending when attempts < max_retries and cooldown passed', function () {
    $printer = Printer::factory()->create(['max_retries' => 3]);
    $job = PrintJob::factory()->create([
        'status' => PrintJobStatus::Failed,
        'printer_id' => $printer->id,
        'attempts' => 1,
        'failed_at' => now()->subMinutes(3),
        'error_message' => 'boom',
    ]);

    $this->artisan(RetryFailedPrintJobsCommand::class)->assertSuccessful();

    $job->refresh();
    expect($job->status)->toBe(PrintJobStatus::Pending)
        ->and($job->printer_id)->toBeNull()
        ->and($job->error_message)->toBeNull();
});

it('does not retry when attempts >= max_retries', function () {
    $printer = Printer::factory()->create(['max_retries' => 3]);
    $job = PrintJob::factory()->create([
        'status' => PrintJobStatus::Failed,
        'printer_id' => $printer->id,
        'attempts' => 3,
        'failed_at' => now()->subMinutes(3),
    ]);

    $this->artisan(RetryFailedPrintJobsCommand::class)->assertSuccessful();

    expect($job->fresh()->status)->toBe(PrintJobStatus::Failed);
});

it('does not retry within cooldown window', function () {
    $printer = Printer::factory()->create(['max_retries' => 3]);
    $job = PrintJob::factory()->create([
        'status' => PrintJobStatus::Failed,
        'printer_id' => $printer->id,
        'attempts' => 1,
        'failed_at' => now()->subSeconds(30),
    ]);

    $this->artisan(RetryFailedPrintJobsCommand::class)->assertSuccessful();

    expect($job->fresh()->status)->toBe(PrintJobStatus::Failed);
});
