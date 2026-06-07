<?php

declare(strict_types=1);

use Dashed\DashedCore\Models\Customsetting;
use Dashed\DashedEcommerceCore\Models\Order;
use Dashed\DashedEcommerceCore\Models\Printer;
use Dashed\DashedEcommerceCore\Models\PrintJob;
use Dashed\DashedEcommerceCore\Enums\PrinterType;
use Dashed\DashedEcommerceCore\Enums\PrintJobType;
use Dashed\DashedEcommerceMyParcel\Models\MyParcelOrder;
use Dashed\DashedEcommerceCore\Jobs\PrintQueue\SyncShippingLabelPrintJobsJob;

beforeEach(function () {
    Customsetting::set('print_queue.auto_print_label_on_generated', true);
    Printer::factory()->create(['type' => PrinterType::ShippingLabel, 'is_active' => true]);
});

it('creates a print job for MyParcelOrder with label_pdf_path and label_printed=false', function () {
    $order = Order::factory()->create();
    $mpo = MyParcelOrder::create([
        'order_id' => $order->id,
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
        ->and($job->pdf_disk)->toBe('public')
        ->and($job->pdf_path)->toBe('dashed/orders/my-parcel/label-1234.pdf');
});

it('is idempotent: re-running sync does not create duplicates', function () {
    $order = Order::factory()->create();
    MyParcelOrder::create([
        'order_id' => $order->id,
        'label_pdf_path' => 'dashed/orders/my-parcel/label-1234.pdf',
        'label_printed' => false,
    ]);

    (new SyncShippingLabelPrintJobsJob())->handle();
    (new SyncShippingLabelPrintJobsJob())->handle();

    expect(PrintJob::count())->toBe(1);
});

it('skips when label_printed=true', function () {
    $order = Order::factory()->create();
    MyParcelOrder::create([
        'order_id' => $order->id,
        'label_pdf_path' => 'dashed/orders/my-parcel/label-1234.pdf',
        'label_printed' => true,
    ]);

    (new SyncShippingLabelPrintJobsJob())->handle();

    expect(PrintJob::count())->toBe(0);
});

it('respects auto_print_label_on_generated setting', function () {
    Customsetting::set('print_queue.auto_print_label_on_generated', false);
    $order = Order::factory()->create();
    MyParcelOrder::create([
        'order_id' => $order->id,
        'label_pdf_path' => 'dashed/orders/my-parcel/label-1234.pdf',
        'label_printed' => false,
    ]);

    (new SyncShippingLabelPrintJobsJob())->handle();

    expect(PrintJob::count())->toBe(0);
});
