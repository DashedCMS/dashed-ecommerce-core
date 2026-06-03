<?php

use Illuminate\Support\Str;
use Dashed\DashedEcommerceCore\Models\Order;
use Dashed\DashedEcommerceVeloyd\Models\VeloydOrder;
use Dashed\DashedEcommerceVeloyd\Livewire\Orders\ShowCreateVeloydReturnLabelOrder;

function makeVeloydOrderFor(Order $order, array $attributes = []): VeloydOrder
{
    return $order->veloydOrders()->create(array_merge([
        'carrier' => 'PostNL',
        'is_return' => false,
    ], $attributes));
}

function widgetFor(Order $order): ShowCreateVeloydReturnLabelOrder
{
    $component = new ShowCreateVeloydReturnLabelOrder();
    $component->order = $order;

    return $component;
}

it('returns the latest return label that has a pdf', function () {
    $order = Order::create(['email' => 'k@example.com', 'hash' => Str::random(32), 'total' => 10, 'site_id' => 'default', 'ip' => '127.0.0.1']);

    makeVeloydOrderFor($order, ['is_return' => false, 'label_pdf_path' => 'shipping.pdf']);
    makeVeloydOrderFor($order, ['is_return' => true, 'label_pdf_path' => null]);
    $latestReturn = makeVeloydOrderFor($order, ['is_return' => true, 'label_pdf_path' => 'return-2.pdf']);

    expect(widgetFor($order)->existingReturnLabel()?->id)->toBe($latestReturn->id);
});

it('returns null when there is no return label with a pdf', function () {
    $order = Order::create(['email' => 'k@example.com', 'hash' => Str::random(32), 'total' => 10, 'site_id' => 'default', 'ip' => '127.0.0.1']);
    makeVeloydOrderFor($order, ['is_return' => false, 'label_pdf_path' => 'shipping.pdf']);
    makeVeloydOrderFor($order, ['is_return' => true, 'label_pdf_path' => null]);

    expect(widgetFor($order)->existingReturnLabel())->toBeNull();
});
