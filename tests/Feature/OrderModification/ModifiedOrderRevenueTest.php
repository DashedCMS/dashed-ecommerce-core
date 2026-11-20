<?php

use Dashed\DashedCore\Models\Customsetting;
use Dashed\DashedEcommerceCore\Models\Order;
use Dashed\DashedEcommerceCore\Models\OrderProduct;
use Dashed\DashedEcommerceCore\Classes\OrderStatistics;
use Dashed\DashedEcommerceCore\Classes\OrderModificationService;

uses(\Illuminate\Foundation\Testing\RefreshDatabase::class);

/**
 * Een gewijzigde bestelling met factuur laat drie orders achter: de oude
 * (blijft betaald), een creditorder en de vervanger op dezelfde betaaldatum.
 * De omzet mag daar maar één keer het bedrag van de vervanger tellen, zonder
 * dat creditfacturen in het algemeen van de omzet afgaan.
 */
function omzetBetaaldeOrder(float $total = 100.0): Order
{
    Customsetting::set('taxes_prices_include_taxes', 1);

    $order = Order::create([
        'email' => 'klant@example.com',
        'status' => 'paid',
        'invoice_id' => '2026-0001',
        'total' => $total,
        'subtotal' => $total,
    ]);
    OrderProduct::create([
        'order_id' => $order->id,
        'name' => 'Oud product',
        'quantity' => 1,
        'price' => $total,
        'vat_rate' => 21,
    ]);
    $order->orderPayments()->create(['status' => 'paid', 'amount' => $total, 'psp' => 'own']);

    return $order->fresh();
}

function omzetWijzig(Order $order, float $nieuwBedrag): Order
{
    return OrderModificationService::replaceWithNewOrder($order, [[
        'order_product_id' => null,
        'product_id' => null,
        'name' => 'Nieuw product',
        'quantity' => 1,
        'price' => $nieuwBedrag,
        'vat_rate' => 21,
        'product_extras' => [],
    ]], ['credit_old_order' => true]);
}

it('telt een gewijzigde bestelling maar één keer als omzet', function () {
    $oud = omzetBetaaldeOrder(100);
    $nieuw = omzetWijzig($oud, 130);

    // Het scenario zelf: de oude order blijft betaald en er is een creditorder.
    expect($oud->fresh()->status)->toBe('paid')
        ->and(Order::where('status', 'return')->count())->toBe(1);

    expect(Order::query()->isPaid()->pluck('id')->all())->toBe([$nieuw->id])
        ->and((float) Order::query()->isPaid()->sum('total'))->toEqual((float) $nieuw->fresh()->total);
});

it('telt de creditorder van een wijziging niet als retour', function () {
    omzetWijzig(omzetBetaaldeOrder(100), 130);

    expect(Order::query()->isReturn()->count())->toBe(0);
});

it('telt een retour van vóór de wijziging wel gewoon mee', function () {
    $retour = Order::create([
        'email' => 'klant@example.com',
        'status' => 'return',
        'invoice_id' => '2026-0002',
        'total' => -20,
        'subtotal' => -20,
        'credit_for_order_id' => omzetBetaaldeOrder(100)->id,
    ]);

    expect(Order::query()->isReturn()->pluck('id')->all())->toBe([$retour->id])
        ->and(Order::query()->isPaid()->count())->toBe(1);
});

it('laat de omzetlijn op het dashboard niet dubbel tellen', function () {
    $nieuw = omzetWijzig(omzetBetaaldeOrder(100), 130);

    $reeks = OrderStatistics::perStep(now()->startOfDay(), now()->endOfDay(), 'per_day');

    expect(array_sum($reeks['paid']))->toEqual((float) $nieuw->fresh()->total)
        ->and(array_sum($reeks['return']))->toEqual(0.0);
});

it('houdt alle facturen in de exports voor de boekhouding', function () {
    omzetWijzig(omzetBetaaldeOrder(100), 130);

    expect(Order::query()->whereIn('status', Order::PAID_OR_RETURN_STATUSES)->count())->toBe(3);
});
