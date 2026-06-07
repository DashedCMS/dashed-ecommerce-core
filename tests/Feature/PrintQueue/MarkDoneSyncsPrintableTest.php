<?php

declare(strict_types=1);

use Dashed\DashedEcommerceCore\Models\Order;
use Dashed\DashedEcommerceCore\Models\Printer;
use Dashed\DashedEcommerceCore\Models\PrintJob;
use Dashed\DashedEcommerceCore\Enums\PrintJobType;
use Dashed\DashedEcommerceCore\Enums\PrintJobStatus;
use Dashed\DashedEcommerceMyParcel\Models\MyParcelOrder;

it('flips label_printed=true on linked MyParcelOrder when shipping_label job is marked done', function () {
    $order = Order::factory()->create();
    $mpo = MyParcelOrder::create([
        'order_id' => $order->id,
        'label_pdf_path' => 'dashed/orders/my-parcel/label-x.pdf',
        'label_printed' => false,
    ]);

    $printer = Printer::factory()->create(['is_active' => true]);
    $job = PrintJob::factory()->create([
        'type' => PrintJobType::ShippingLabel,
        'order_id' => $order->id,
        'printable_type' => MyParcelOrder::class,
        'printable_id' => $mpo->id,
        'status' => PrintJobStatus::Claimed,
        'printer_id' => $printer->id,
    ]);

    $token = $printer->createToken('t')->plainTextToken;

    $this->withHeaders(['Authorization' => "Bearer {$token}"])
        ->postJson("/api/print/{$job->ulid}/done")
        ->assertOk();

    expect($mpo->fresh()->label_printed)->toBeTrue();
    expect($job->fresh()->status)->toBe(PrintJobStatus::Done);
});

it('does not crash when printable_type does not exist', function () {
    $order = Order::factory()->create();
    $printer = Printer::factory()->create(['is_active' => true]);
    $job = PrintJob::factory()->create([
        'type' => PrintJobType::ShippingLabel,
        'order_id' => $order->id,
        'printable_type' => 'NonExistentClass',
        'printable_id' => 999,
        'status' => PrintJobStatus::Claimed,
        'printer_id' => $printer->id,
    ]);

    $token = $printer->createToken('t')->plainTextToken;

    $this->withHeaders(['Authorization' => "Bearer {$token}"])
        ->postJson("/api/print/{$job->ulid}/done")
        ->assertOk();
});
