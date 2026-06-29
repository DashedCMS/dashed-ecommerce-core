<?php

declare(strict_types=1);

use function Pest\Laravel\artisan;

use Dashed\DashedEcommerceCore\Models\Order;
use Dashed\DashedEcommerceCore\Models\PrintJob;
use Dashed\DashedEcommerceCore\Enums\PrintJobType;
use Dashed\DashedEcommerceCore\Enums\PrintJobStatus;
use Dashed\DashedEcommerceMyParcel\Models\MyParcelOrder;

/**
 * Met terugwerkende kracht: een verzendlabel dat al via de app/printer is geprint
 * (er bestaat een ShippingLabel-PrintJob) moet uit de "Download labels"-wachtrij
 * verdwijnen door label_printed op 1 te zetten.
 */
function makeOrderWithLabel(bool $printed = false): array
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

it('marks label_printed for a row directly linked by a shipping-label print job', function () {
    [$order, $mpo] = makeOrderWithLabel();

    PrintJob::create([
        'type' => PrintJobType::ShippingLabel,
        'order_id' => $order->id,
        'printable_type' => MyParcelOrder::class,
        'printable_id' => $mpo->id,
        'status' => PrintJobStatus::Done,
    ]);

    artisan('dashed-ecommerce:backfill-printed-shipping-labels')->assertSuccessful();

    expect($mpo->fresh()->label_printed)->toBeTrue();
});

it('marks label_printed when the order has an order-level shipping-label print job', function () {
    [$order, $mpo] = makeOrderWithLabel();

    // Geen printable-koppeling: de app printte "het label" voor de order.
    PrintJob::create([
        'type' => PrintJobType::ShippingLabel,
        'order_id' => $order->id,
        'printable_type' => null,
        'printable_id' => null,
        'status' => PrintJobStatus::Pending,
    ]);

    artisan('dashed-ecommerce:backfill-printed-shipping-labels')->assertSuccessful();

    expect($mpo->fresh()->label_printed)->toBeTrue();
});

it('does not touch a row whose order has no shipping-label print job', function () {
    [$order, $mpo] = makeOrderWithLabel();

    artisan('dashed-ecommerce:backfill-printed-shipping-labels')->assertSuccessful();

    expect($mpo->fresh()->label_printed)->toBeFalse();
});

it('ignores failed and cancelled print jobs', function () {
    [$orderFailed, $mpoFailed] = makeOrderWithLabel();
    PrintJob::create([
        'type' => PrintJobType::ShippingLabel,
        'order_id' => $orderFailed->id,
        'printable_type' => MyParcelOrder::class,
        'printable_id' => $mpoFailed->id,
        'status' => PrintJobStatus::Failed,
    ]);

    [$orderCancelled, $mpoCancelled] = makeOrderWithLabel();
    PrintJob::create([
        'type' => PrintJobType::ShippingLabel,
        'order_id' => $orderCancelled->id,
        'printable_type' => MyParcelOrder::class,
        'printable_id' => $mpoCancelled->id,
        'status' => PrintJobStatus::Cancelled,
    ]);

    artisan('dashed-ecommerce:backfill-printed-shipping-labels')->assertSuccessful();

    expect($mpoFailed->fresh()->label_printed)->toBeFalse()
        ->and($mpoCancelled->fresh()->label_printed)->toBeFalse();
});

it('ignores non shipping-label print jobs (e.g. packing slip)', function () {
    [$order, $mpo] = makeOrderWithLabel();

    PrintJob::create([
        'type' => PrintJobType::PackingSlip,
        'order_id' => $order->id,
        'status' => PrintJobStatus::Done,
    ]);

    artisan('dashed-ecommerce:backfill-printed-shipping-labels')->assertSuccessful();

    expect($mpo->fresh()->label_printed)->toBeFalse();
});

it('is idempotent and leaves already-printed rows untouched', function () {
    [$order, $mpo] = makeOrderWithLabel(printed: true);
    PrintJob::create([
        'type' => PrintJobType::ShippingLabel,
        'order_id' => $order->id,
        'printable_type' => MyParcelOrder::class,
        'printable_id' => $mpo->id,
        'status' => PrintJobStatus::Done,
    ]);

    artisan('dashed-ecommerce:backfill-printed-shipping-labels')->assertSuccessful();
    artisan('dashed-ecommerce:backfill-printed-shipping-labels')->assertSuccessful();

    expect($mpo->fresh()->label_printed)->toBeTrue();
});
