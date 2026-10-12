<?php

require_once __DIR__ . '/../../Support/SpilOrder.php';

use Dashed\DashedEcommerceCore\Models\Order;
use Dashed\DashedEcommerceCore\Classes\OrderStatistics;
use Dashed\DashedEcommerceCore\Models\OrderProduct;
use Dashed\DashedEcommerceCore\Services\OrderReturn\FreeShippingReversal;

it('telt de verzendinhoudingsregel niet als verkocht product', function () {
    $f = spilOrder();
    OrderProduct::withoutEvents(fn () => OrderProduct::create([
        'order_id' => $f['order']->id, 'name' => 'Verzendkosten', 'quantity' => 1, 'price' => 7.95, 'vat_rate' => 21, 'sku' => FreeShippingReversal::SKU,
    ]));

    $totals = OrderStatistics::totals(Order::query()->whereKey($f['order']->id));

    // Shirt 3 + Broek 1; de inhoudingsregel telt niet mee.
    expect((int) $totals['products'])->toBe(4);
});
