<?php

declare(strict_types=1);

use Dashed\DashedCore\Models\Customsetting;
use Dashed\DashedEcommerceCore\Models\Order;
use Dashed\DashedEcommerceCore\Models\Printer;
use Dashed\DashedEcommerceCore\Models\PrintJob;
use Dashed\DashedEcommerceCore\Enums\PrinterType;
use Dashed\DashedEcommerceCore\Enums\PrintJobType;
use Dashed\DashedEcommerceCore\Enums\PrintJobStatus;
use Dashed\DashedEcommerceMyParcel\Models\MyParcelOrder;
use Dashed\DashedEcommerceCore\Jobs\PrintQueue\SyncShippingLabelPrintJobsJob;

beforeEach(function () {
    Customsetting::set('print_queue.auto_print_label_on_generated', true);
    Printer::factory()->create(['type' => PrinterType::ShippingLabel, 'is_active' => true]);
});

it('creates a print job for a MyParcelOrder with a shipment_id', function () {
    $order = Order::create(['email' => 'klant@example.com', 'status' => 'paid']);
    $mpo = MyParcelOrder::create([
        'order_id' => $order->id,
        'shipment_id' => 'shp-1234',
        'label_pdf_path' => 'dashed/orders/my-parcel/label-1234.pdf',
        'label_printed' => false,
    ]);

    (new SyncShippingLabelPrintJobsJob())->handle();

    expect(PrintJob::count())->toBe(1);
    $job = PrintJob::first();
    expect($job->type)->toBe(PrintJobType::ShippingLabel)
        ->and($job->order_id)->toBe($order->id)
        ->and($job->printable_type)->toBe(MyParcelOrder::class)
        ->and($job->printable_id)->toBe($mpo->id)
        ->and($job->status)->toBe(PrintJobStatus::Pending);
});

it('is idempotent: re-running sync does not create duplicates', function () {
    $order = Order::create(['email' => 'klant@example.com', 'status' => 'paid']);
    MyParcelOrder::create([
        'order_id' => $order->id,
        'shipment_id' => 'shp-1234',
        'label_pdf_path' => 'dashed/orders/my-parcel/label-1234.pdf',
        'label_printed' => false,
    ]);

    (new SyncShippingLabelPrintJobsJob())->handle();
    (new SyncShippingLabelPrintJobsJob())->handle();

    expect(PrintJob::count())->toBe(1);
});

it('skips a MyParcelOrder without a shipment_id', function () {
    $order = Order::create(['email' => 'klant@example.com', 'status' => 'paid']);
    MyParcelOrder::create([
        'order_id' => $order->id,
        'shipment_id' => null,
        'label_pdf_path' => 'dashed/orders/my-parcel/label-1234.pdf',
        'label_printed' => false,
    ]);

    (new SyncShippingLabelPrintJobsJob())->handle();

    expect(PrintJob::count())->toBe(0);
});

it('respects auto_print_label_on_generated setting', function () {
    Customsetting::set('print_queue.auto_print_label_on_generated', false);
    $order = Order::create(['email' => 'klant@example.com', 'status' => 'paid']);
    MyParcelOrder::create([
        'order_id' => $order->id,
        'shipment_id' => 'shp-1234',
        'label_pdf_path' => 'dashed/orders/my-parcel/label-1234.pdf',
        'label_printed' => false,
    ]);

    (new SyncShippingLabelPrintJobsJob())->handle();

    expect(PrintJob::count())->toBe(0);
});
