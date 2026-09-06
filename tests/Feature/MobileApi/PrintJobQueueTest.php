<?php

use Dashed\DashedCore\Models\User;
use Dashed\DashedEcommerceCore\Models\Order;
use Dashed\DashedEcommerceCore\Models\PrintJob;
use Dashed\DashedEcommerceCore\Enums\PrintJobStatus;

/**
 * Print-wachtrij in de app: lijst met statusfilter + retry/cancel per taak
 * (pariteit met de Filament PrintJobResource-acties).
 */
it('print-jobs: lijst toont de wachtrij met order en statusfilter', function () {
    $this->actingAs(User::factory()->create(['role' => 'admin']), 'sanctum');

    $order = Order::create([
        'site_id' => 'site',
        'email' => 'klant@example.com',
        'invoice_id' => 'INV-' . strtoupper(uniqid()),
        'status' => 'paid',
    ]);

    $pending = PrintJob::create(['order_id' => $order->id, 'type' => 'packing_slip', 'status' => PrintJobStatus::Pending]);
    $failed = PrintJob::create(['order_id' => $order->id, 'type' => 'shipping_label', 'status' => PrintJobStatus::Failed, 'error_message' => 'Printer offline']);

    $res = $this->getJson('/api/v1/print-jobs', ['X-Site-Id' => 'site']);
    $res->assertOk();
    $rows = collect($res->json('data'))->keyBy('id');
    expect($rows[$pending->id]['invoice_id'])->toBe($order->invoice_id)
        ->and($rows[$failed->id]['error_message'])->toBe('Printer offline');

    $onlyFailed = $this->getJson('/api/v1/print-jobs?status=failed', ['X-Site-Id' => 'site']);
    $ids = collect($onlyFailed->json('data'))->pluck('id');
    expect($ids)->toContain($failed->id)->not->toContain($pending->id);
});

it('print-jobs: retry zet een mislukte taak terug in de wachtrij, cancel annuleert', function () {
    $this->actingAs(User::factory()->create(['role' => 'admin']), 'sanctum');

    $failed = PrintJob::create(['type' => 'packing_slip', 'status' => PrintJobStatus::Failed, 'error_message' => 'x', 'attempts' => 2]);

    $res = $this->postJson("/api/v1/print-jobs/{$failed->id}/retry", [], ['X-Site-Id' => 'site']);
    $res->assertOk();
    expect($res->json('data.status'))->toBe('pending')
        ->and($res->json('data.error_message'))->toBeNull();

    $res = $this->postJson("/api/v1/print-jobs/{$failed->id}/cancel", [], ['X-Site-Id' => 'site']);
    $res->assertOk();
    expect($res->json('data.status'))->toBe('cancelled');
});
