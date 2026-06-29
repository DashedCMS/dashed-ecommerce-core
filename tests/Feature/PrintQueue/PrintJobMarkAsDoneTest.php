<?php

declare(strict_types=1);

use Dashed\DashedEcommerceCore\Models\Order;
use Dashed\DashedEcommerceCore\Models\PrintJob;
use Dashed\DashedEcommerceCore\Enums\PrintJobType;
use Dashed\DashedEcommerceCore\Enums\PrintJobStatus;
use Dashed\DashedEcommerceMyParcel\Models\MyParcelOrder;

function makeMpOrder(bool $printed = false): array
{
    $order = Order::create(['email' => 'klant@example.com', 'status' => 'paid']);
    $mpo = MyParcelOrder::create([
        'order_id' => $order->id,
        'shipment_id' => 'shp-' . $order->id,
        'label_pdf_path' => 'dashed/orders/my-parcel/label-' . $order->id . '.pdf',
        'label_printed' => $printed,
    ]);

    return [$order, $mpo];
}

it('marks the linked printable as printed when a shipping-label job is done', function () {
    [$order, $mpo] = makeMpOrder();

    $job = PrintJob::create([
        'type' => PrintJobType::ShippingLabel,
        'order_id' => $order->id,
        'printable_type' => MyParcelOrder::class,
        'printable_id' => $mpo->id,
        'status' => PrintJobStatus::Pending,
    ]);

    $job->markAsDone();

    expect($mpo->fresh()->label_printed)->toBeTrue();
});

it('marks the order shipping labels as printed when the job has no specific printable', function () {
    [$order, $mpo] = makeMpOrder();

    $job = PrintJob::create([
        'type' => PrintJobType::ShippingLabel,
        'order_id' => $order->id,
        'printable_type' => null,
        'printable_id' => null,
        'status' => PrintJobStatus::Pending,
    ]);

    $job->markAsDone();

    expect($mpo->fresh()->label_printed)->toBeTrue();
});

it('does not touch shipping labels when a packing slip is done', function () {
    [$order, $mpo] = makeMpOrder();

    $job = PrintJob::create([
        'type' => PrintJobType::PackingSlip,
        'order_id' => $order->id,
        'status' => PrintJobStatus::Pending,
    ]);

    $job->markAsDone();

    expect($mpo->fresh()->label_printed)->toBeFalse();
});
