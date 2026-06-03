<?php

use Illuminate\Support\Str;
use Dashed\DashedEcommerceCore\Models\Order;
use Dashed\DashedEcommerceVeloyd\Models\VeloydOrder;
use Dashed\DashedEcommerceVeloyd\Livewire\Orders\ShowVeloydOrders;

function makeLabelsOrder(): Order
{
    return Order::create([
        'email' => 'k@example.com',
        'hash' => Str::random(32),
        'total' => 10,
        'site_id' => 'default',
        'ip' => '127.0.0.1',
    ]);
}

function makeLabel(Order $order, array $attributes = []): VeloydOrder
{
    return $order->veloydOrders()->create(array_merge([
        'carrier' => 'PostNL',
        'is_return' => false,
    ], $attributes));
}

function showVeloydWidget(Order $order): ShowVeloydOrders
{
    $component = new ShowVeloydOrders();
    $component->order = $order;

    return $component;
}

it('downloadableLabels returns only non-return labels with a pdf', function () {
    $order = makeLabelsOrder();
    $shipping = makeLabel($order, ['is_return' => false, 'label_pdf_path' => 'a.pdf']);
    makeLabel($order, ['is_return' => false, 'label_pdf_path' => null]);
    makeLabel($order, ['is_return' => true, 'label_pdf_path' => 'r.pdf']);

    $result = showVeloydWidget($order)->downloadableLabels();

    expect($result->pluck('id')->all())->toBe([$shipping->id]);
});

it('requeueAllLabels flips printed non-return labels and leaves return labels alone', function () {
    $order = makeLabelsOrder();
    $printed = makeLabel($order, ['is_return' => false, 'shipment_id' => 'S1', 'label_printed' => true]);
    $queued = makeLabel($order, ['is_return' => false, 'shipment_id' => 'S2', 'label_printed' => false]);
    $noShipment = makeLabel($order, ['is_return' => false, 'shipment_id' => null, 'error' => 'old error']);
    $return = makeLabel($order, ['is_return' => true, 'shipment_id' => 'S3', 'label_printed' => true]);

    $counts = showVeloydWidget($order)->requeueAllLabels();

    expect((bool) $printed->fresh()->label_printed)->toBeFalse()
        ->and((bool) $queued->fresh()->label_printed)->toBeFalse()
        ->and($noShipment->fresh()->error)->toBeNull()
        ->and((bool) $return->fresh()->label_printed)->toBeTrue()
        ->and($counts)->toBe(['requeued' => 1, 'concept' => 1, 'queued' => 1]);
});
