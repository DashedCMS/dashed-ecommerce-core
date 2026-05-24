<?php

declare(strict_types=1);

use Dashed\DashedCore\Models\Customsetting;
use Dashed\DashedEcommerceCore\Enums\PrinterType;
use Dashed\DashedEcommerceCore\Enums\PrintJobStatus;
use Dashed\DashedEcommerceCore\Enums\PrintJobType;
use Dashed\DashedEcommerceCore\Events\Orders\OrderCreatedEvent;
use Dashed\DashedEcommerceCore\Listeners\PrintQueue\CreatePackingSlipPrintJobListener;
use Dashed\DashedEcommerceCore\Models\Order;
use Dashed\DashedEcommerceCore\Models\Printer;
use Dashed\DashedEcommerceCore\Models\PrintJob;

beforeEach(function () {
    Customsetting::set('print_queue.auto_print_on_new_order', true);
});

it('creates a packing_slip PrintJob when active printer exists', function () {
    Printer::factory()->create(['type' => PrinterType::PackingSlip, 'is_active' => true]);
    $order = Order::factory()->create();

    (new CreatePackingSlipPrintJobListener())->handle(new OrderCreatedEvent($order));

    expect(PrintJob::count())->toBe(1);
    $job = PrintJob::first();
    expect($job->type)->toBe(PrintJobType::PackingSlip)
        ->and($job->order_id)->toBe($order->id)
        ->and($job->status)->toBe(PrintJobStatus::Pending);
});

it('does nothing when auto_print_on_new_order is false', function () {
    Customsetting::set('print_queue.auto_print_on_new_order', false);
    Printer::factory()->create(['type' => PrinterType::PackingSlip, 'is_active' => true]);
    $order = Order::factory()->create();

    (new CreatePackingSlipPrintJobListener())->handle(new OrderCreatedEvent($order));

    expect(PrintJob::count())->toBe(0);
});

it('does nothing when no matching active printer exists', function () {
    Printer::factory()->create(['type' => PrinterType::ShippingLabel, 'is_active' => true]);
    $order = Order::factory()->create();

    (new CreatePackingSlipPrintJobListener())->handle(new OrderCreatedEvent($order));

    expect(PrintJob::count())->toBe(0);
});

it('counts "both" printer as a matching packing_slip target', function () {
    Printer::factory()->create(['type' => PrinterType::Both, 'is_active' => true]);
    $order = Order::factory()->create();

    (new CreatePackingSlipPrintJobListener())->handle(new OrderCreatedEvent($order));

    expect(PrintJob::count())->toBe(1);
});
