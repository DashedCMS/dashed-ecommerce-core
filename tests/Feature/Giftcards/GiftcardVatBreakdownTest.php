<?php

use Dashed\DashedCore\Models\Customsetting;
use Dashed\DashedEcommerceCore\Models\Order;
use Dashed\DashedEcommerceCore\Classes\OssVat;
use Dashed\DashedEcommerceCore\Models\Product;
use Dashed\DashedEcommerceCore\Models\OrderPayment;
use Dashed\DashedEcommerceCore\Models\OrderProduct;
use Dashed\DashedEcommerceCore\Models\ProductGroup;
use Dashed\DashedEcommerceCore\Classes\InvoiceExport\VatBreakdown;

/**
 * Een verkochte cadeaubon is een meervoudige voucher: geen omzet in een
 * btw-tarief, maar apart als "Verkochte cadeaubonnen". Een order die met een
 * bon betaald is houdt zijn volle omzet en btw.
 */
function vatBreakdownGiftcardProduct(): Product
{
    $group = ProductGroup::create([
        'name' => ['en' => 'Bon'],
        'slug' => ['en' => 'bon-' . uniqid()],
        'short_description' => ['en' => ''],
        'description' => ['en' => ''],
        'content' => ['en' => ''],
        'search_terms' => ['en' => ''],
        'site_ids' => ['default'],
    ]);
    $group->is_giftcard_product = true;
    $group->save();

    return Product::withoutEvents(fn () => Product::create([
        'product_group_id' => $group->id,
        'name' => ['en' => 'Bon 25'],
        'slug' => ['en' => 'bon-25-' . uniqid()],
        'site_ids' => ['default'],
        'current_price' => 25,
        'price' => 25,
        'vat_rate' => 0,
    ]));
}

/** $lines: lijst van [prijs incl, tarief, ?product-id]. */
function vatBreakdownGiftcardOrder(array $lines, float $btw): Order
{
    $total = array_sum(array_column($lines, 0));

    $order = Order::create([
        'status' => 'paid',
        'country' => 'Nederland',
        'order_origin' => 'own',
        'subtotal' => $total,
        'btw' => $btw,
        'total' => $total,
        'discount' => 0,
        'vat_percentages' => [],
    ]);

    foreach ($lines as $line) {
        OrderProduct::withoutEvents(fn () => OrderProduct::create([
            'order_id' => $order->id,
            'product_id' => $line[2] ?? null,
            'name' => 'Regel',
            'quantity' => 1,
            'price' => $line[0],
            'vat_rate' => $line[1],
        ]));
    }

    return $order->fresh(['orderProducts']);
}

beforeEach(function () {
    OssVat::flush();
    Customsetting::set('company_country', 'Nederland');
    Customsetting::set('taxes_prices_include_taxes', 1);
    Customsetting::set('oss_enabled', '0');
});

it('reports a giftcard sale outside the vat rates', function () {
    $bon = vatBreakdownGiftcardProduct();

    $orders = [
        // Vaas van 121 plus een bon van 25 in één order.
        vatBreakdownGiftcardOrder([[121.00, 21], [25.00, 0, $bon->id]], 21.00),
        // Alleen een bon van 50.
        vatBreakdownGiftcardOrder([[50.00, 0, $bon->id]], 0),
    ];

    $result = VatBreakdown::calculate($orders);

    expect($result['soldGiftcards'])->toBe(75.00)
        ->and($result['redeemedGiftcards'])->toBe(0.00)
        ->and($result['vatPercentages'])->toEqual(['21' => 21.00])
        ->and($result['btw'])->toBe(21.00)
        ->and($result['total'])->toBe(196.00)
        ->and($result['subTotal'])->toBe(100.00);

    expect($result['normalZoneTotals'])->toHaveCount(1)
        ->and($result['normalZoneTotals'][0]['incl_vat'])->toBe(121.00)
        ->and($result['normalZoneTotals'][0]['vat'])->toBe(21.00);
});

it('keeps the full revenue and vat of an order paid with a giftcard', function () {
    $order = vatBreakdownGiftcardOrder([[52.95, 21]], 9.19);

    OrderPayment::withoutEvents(fn () => OrderPayment::create([
        'order_id' => $order->id,
        'psp' => 'giftcard',
        'amount' => 50,
        'status' => 'paid',
    ]));
    OrderPayment::withoutEvents(fn () => OrderPayment::create([
        'order_id' => $order->id,
        'psp' => 'giftcard',
        'amount' => 10,
        'status' => 'cancelled',
    ]));

    $result = VatBreakdown::calculate([$order->fresh(['orderProducts'])]);

    expect($result['total'])->toBe(52.95)
        ->and($result['btw'])->toBe(9.19)
        ->and($result['vatPercentages'])->toEqual(['21' => 9.19])
        ->and($result['subTotal'])->toBe(43.76)
        ->and($result['soldGiftcards'])->toBe(0.00)
        ->and($result['redeemedGiftcards'])->toBe(50.00);
});
