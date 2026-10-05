<?php

use Dashed\DashedCore\Models\Customsetting;
use Dashed\DashedEcommerceCore\Models\Order;
use Dashed\DashedEcommerceCore\Classes\OssVat;
use Dashed\DashedEcommerceCore\Models\OrderProduct;
use Dashed\DashedEcommerceCore\Classes\InvoiceExport\VatBreakdown;

/**
 * $lines: lijst van [prijs incl, tarief]. De hook van OrderProduct zet zonder
 * product niets om zolang het tarief niet het eigen standaardtarief is; oude
 * orders (21% naar het buitenland) maken we daarom met de schakelaar uit.
 */
function breakdownOrder(string $country, array $lines, array $attributes = []): Order
{
    $total = array_sum(array_column($lines, 0));

    $order = Order::create(array_merge([
        'status' => 'paid',
        'country' => $country,
        'order_origin' => 'own',
        'subtotal' => $total,
        'btw' => 0,
        'total' => $total,
        'discount' => 0,
        'vat_percentages' => [],
    ], $attributes));

    foreach ($lines as [$price, $rate]) {
        OrderProduct::create([
            'order_id' => $order->id,
            'name' => 'Regel',
            'quantity' => 1,
            'price' => $price,
            'vat_rate' => $rate,
        ]);
    }

    return $order->fresh(['orderProducts']);
}

beforeEach(function () {
    OssVat::flush();
    Customsetting::set('company_country', 'Nederland');
    // OSS werkt alleen bij prijzen inclusief btw; de testdatabase staat op exclusief.
    Customsetting::set('taxes_prices_include_taxes', 1);
    Customsetting::set('oss_enabled', '0');
});

it('zet buitenlandse particuliere omzet per land onder OSS met het landtarief', function () {
    $orders = [
        breakdownOrder('Nederland', [[121.00, 21]]),
        breakdownOrder('België', [[121.00, 21]]),
        breakdownOrder('Duitsland', [[119.00, 21]]),          // oude order, opgeslagen als 21%
        breakdownOrder('Duitsland', [[59.50, 19]]),           // nieuwe order, staat al op 19%
        breakdownOrder('Duitsland', [[10.90, 9]]),            // verlaagd tarief blijft 9%
        breakdownOrder('Finland', [[125.50, 21]]),
    ];

    Customsetting::set('oss_enabled', '1');
    $result = VatBreakdown::calculate($orders);

    $rows = collect($result['ossTotals'])->keyBy(fn ($row) => $row['country_code'] . '|' . OssVat::rateKey($row['rate']));

    expect($rows->keys()->all())->toBe(['BE|21', 'DE|9', 'DE|19', 'FI|25.5']);
    expect($rows['BE|21'])->toMatchArray(['country' => 'België', 'incl_vat' => 121.00, 'vat' => 21.00, 'ex_vat' => 100.00]);
    expect($rows['DE|19'])->toMatchArray(['incl_vat' => 178.50, 'vat' => 28.50, 'ex_vat' => 150.00]);
    expect($rows['DE|9'])->toMatchArray(['incl_vat' => 10.90, 'vat' => 0.90, 'ex_vat' => 10.00]);
    expect($rows['FI|25.5'])->toMatchArray(['incl_vat' => 125.50, 'vat' => 25.50, 'ex_vat' => 100.00]);

    // Nederlandse btw bevat alleen de Nederlandse order.
    expect($result['vatPercentages'])->toEqual(['21' => 21.00]);
    expect($result['foreignVat'])->toBe(75.90);
    expect($result['btw'])->toBe(96.90);
    expect($result['total'])->toBe(556.90);
    expect($result['subTotal'])->toBe(460.00);

    expect($result['normalZoneTotals'])->toHaveCount(1);
    expect($result['normalZoneTotals'][0]['incl_vat'])->toBe(121.00);

    foreach ($result['ossTotals'] as $row) {
        expect(round($row['ex_vat'] + $row['vat'], 2))->toBe($row['incl_vat']);
    }
    expect($result['ossTotal'])->toMatchArray(['incl_vat' => 435.90, 'vat' => 75.90, 'ex_vat' => 360.00]);
});

it('telt de blokken samen op tot het periodetotaal', function () {
    $orders = [
        breakdownOrder('Nederland', [[60.50, 21]]),
        breakdownOrder('België', [[24.20, 21]]),
        breakdownOrder('Frankrijk', [[36.00, 21]]),
    ];

    Customsetting::set('oss_enabled', '1');
    $result = VatBreakdown::calculate($orders);

    $blocks = array_sum(array_column($result['normalZoneTotals'], 'incl_vat'))
        + $result['ossTotal']['incl_vat']
        + array_sum(array_column($result['icpTotals'], 'revenue'));

    expect(round($blocks, 2))->toBe($result['total']);
    expect(round(array_sum($result['vatPercentages']) + $result['foreignVat'], 2))->toBe($result['btw']);
});

it('verdeelt een lump-korting en verwerkt een creditorder', function () {
    $orders = [
        // regels samen 119, betaald 107,10 (10% lump-korting)
        breakdownOrder('Duitsland', [[119.00, 21]], ['total' => 107.10, 'discount' => 11.90]),
        // creditorder
        breakdownOrder('Duitsland', [[-23.80, 21]], ['total' => -23.80]),
    ];

    Customsetting::set('oss_enabled', '1');
    $result = VatBreakdown::calculate($orders);

    expect($result['ossTotals'])->toHaveCount(1);
    expect($result['ossTotals'][0])->toMatchArray(['incl_vat' => 83.30, 'vat' => 13.30, 'ex_vat' => 70.00]);
});

it('laat verlegde orders, ophalen en niet-EU buiten OSS', function () {
    $orders = [
        breakdownOrder('Duitsland', [[100.00, 0]], ['vat_reverse_charge' => true]),
        breakdownOrder('Engeland', [[50.00, 21]]),
        breakdownOrder('klant@example.com', [[20.00, 21]]),
    ];

    Customsetting::set('oss_enabled', '1');
    $result = VatBreakdown::calculate($orders);

    expect($result['ossTotals'])->toBe([]);
    expect($result['foreignVat'])->toBe(0.0);
    expect(round(array_sum(array_column($result['normalZoneTotals'], 'incl_vat')), 2))->toBe(170.00);
});

it('toont geen OSS-omzet als de schakelaar uit staat', function () {
    $orders = [
        breakdownOrder('Nederland', [[121.00, 21]]),
        breakdownOrder('Duitsland', [[119.00, 21]]),
    ];

    $result = VatBreakdown::calculate($orders);

    expect($result['ossTotals'])->toBe([]);
    expect($result['vatPercentages'])->toEqual(['21' => 41.65]);
    expect($result['btw'])->toBe(41.65);
    expect($result['subTotal'])->toBe(198.35);
    expect(round(array_sum(array_column($result['normalZoneTotals'], 'incl_vat')), 2))->toBe(240.00);
});

it('laat een OSS-order zonder regels en btw in de normale zone vallen', function () {
    $orders = [
        breakdownOrder('Nederland', [[121.00, 21]]),
        breakdownOrder('Duitsland', [], ['total' => 50.00, 'subtotal' => 50.00]),
    ];

    Customsetting::set('oss_enabled', '1');
    $result = VatBreakdown::calculate($orders);

    expect($result['ossTotals'])->toBe([]);
    expect($result['btw'])->toBe(21.00);
    expect($result['total'])->toBe(171.00);
    expect(round(array_sum(array_column($result['normalZoneTotals'], 'incl_vat')), 2))->toBe(171.00);
});

it('houdt order->total aan bij een regelsom die een cent afwijkt', function () {
    $orders = [breakdownOrder('Duitsland', [[10.01, 19]], ['total' => 10.00])];

    Customsetting::set('oss_enabled', '1');
    $result = VatBreakdown::calculate($orders);

    expect($result['ossTotals'])->toHaveCount(1);
    expect($result['ossTotals'][0]['incl_vat'])->toBe(10.00);
});

it('rendert de OSS-tabel en de regel buitenlandse btw', function () {
    // De facade-aliassen van een volledige app ontbreken in Testbench.
    foreach ([
        'Translation' => \Dashed\DashedTranslations\Models\Translation::class,
        'Customsetting' => Customsetting::class,
        'CurrencyHelper' => \Dashed\DashedEcommerceCore\Classes\CurrencyHelper::class,
        'Sites' => \Dashed\DashedCore\Classes\Sites::class,
    ] as $alias => $class) {
        if (! class_exists($alias, false)) {
            class_alias($class, $alias);
        }
    }

    $orders = [
        breakdownOrder('Nederland', [[121.00, 21]]),
        breakdownOrder('Duitsland', [[119.00, 21]]),
    ];

    Customsetting::set('oss_enabled', '1');
    $result = VatBreakdown::calculate($orders);

    $html = view('dashed-ecommerce-core::invoices.combined-invoices', array_merge($result, [
        'paymentCosts' => 0,
        'shippingCosts' => 0,
        'productSales' => [],
        'startDate' => now()->startOfMonth(),
        'endDate' => now(),
    ]))->render();

    expect($html)->toContain('OSS omzet');
    expect($html)->toContain('Duitsland');
    expect($html)->toContain('19%');
    expect($html)->toContain('Buitenlandse btw (OSS)');
    expect($html)->not->toContain('Geen OSS omzet in deze periode');
});
