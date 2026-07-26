<?php

declare(strict_types=1);

use Carbon\Carbon;
use Dashed\DashedEcommerceCore\Models\Order;
use Dashed\DashedEcommerceCore\Models\OrderPayment;
use Dashed\DashedEcommerceCore\Support\Automation\TimeAnchors;

/**
 * Task 3 (B2): TimeAnchors lost ankermomenten van een order op, zodat een
 * tijd-regel kan meten "N dagen na [anker]". Er is geen `Order::factory()`
 * in dit package (zie ook RuleDryRunTest/AutomationRuleModelTest), dus we
 * bouwen orders met `Order::create()` — Order::boot() vult `ip`/`hash`/
 * `site_id` al automatisch in. `created_at` en `dashed__order_payments
 * .created_at` staan niet in de respectievelijke $fillable-lijsten, dus die
 * zetten we na het aanmaken via forceFill() + save().
 */
function timeAnchorsOrder(array $attributes = []): Order
{
    return Order::create(array_merge([
        'email' => 'klant@example.com',
        'status' => 'paid',
    ], $attributes));
}

function withCreatedAt(Order $order, Carbon $createdAt): Order
{
    $order->forceFill(['created_at' => $createdAt])->save();

    return $order->fresh();
}

function addPaidPayment(Order $order, Carbon $paidAt, float $amount = 10.0): OrderPayment
{
    $payment = $order->orderPayments()->create([
        'status' => 'paid',
        'amount' => $amount,
    ]);
    $payment->forceFill(['created_at' => $paidAt])->save();

    return $payment->fresh();
}

it('created_at anker: timeFor geeft orders.created_at', function () {
    $order = timeAnchorsOrder();
    $order = withCreatedAt($order, Carbon::parse('2026-01-10 09:00'));

    expect(TimeAnchors::timeFor($order, 'created_at')->toDateTimeString())
        ->toBe('2026-01-10 09:00:00');
});

it('paid anker: timeFor geeft de vroegste paid-betaling, null zonder betaling', function () {
    $paid = timeAnchorsOrder();
    addPaidPayment($paid, Carbon::parse('2026-01-12 08:00'));

    $unpaid = timeAnchorsOrder();

    expect(TimeAnchors::timeFor($paid, 'paid')->toDateTimeString())->toBe('2026-01-12 08:00:00');
    expect(TimeAnchors::timeFor($unpaid, 'paid'))->toBeNull();
});

it('paid anker: timeFor geeft de vroegste van meerdere paid-betalingen voor dezelfde order', function () {
    $order = timeAnchorsOrder();
    addPaidPayment($order, Carbon::parse('2026-01-20 10:00'));
    addPaidPayment($order, Carbon::parse('2026-01-12 08:00'));
    addPaidPayment($order, Carbon::parse('2026-01-25 10:00'));

    expect(TimeAnchors::timeFor($order, 'paid')->toDateTimeString())->toBe('2026-01-12 08:00:00');
});

it('paid anker: negeert niet-paid betalingen', function () {
    $order = timeAnchorsOrder();
    $pending = $order->orderPayments()->create(['status' => 'pending', 'amount' => 5]);
    $pending->forceFill(['created_at' => Carbon::parse('2026-01-01 00:00')])->save();

    expect(TimeAnchors::timeFor($order, 'paid'))->toBeNull();
});

it('timeFor gooit een InvalidArgumentException bij een onbekend anker', function () {
    $order = timeAnchorsOrder();

    TimeAnchors::timeFor($order, 'fulfilled');
})->throws(InvalidArgumentException::class);

it('applyBefore created_at: alleen orders aangemaakt voor het moment', function () {
    $old = withCreatedAt(timeAnchorsOrder(), Carbon::parse('2026-01-01 00:00'));
    $recent = withCreatedAt(timeAnchorsOrder(), Carbon::parse('2026-02-01 00:00'));

    $ids = Order::query()
        ->tap(fn ($q) => TimeAnchors::applyBefore($q, 'created_at', Carbon::parse('2026-01-15')))
        ->pluck('id');

    expect($ids)->toContain($old->id)->not->toContain($recent->id);
});

it('applyAfter created_at: alleen orders aangemaakt na het moment', function () {
    $old = withCreatedAt(timeAnchorsOrder(), Carbon::parse('2026-01-01 00:00'));
    $recent = withCreatedAt(timeAnchorsOrder(), Carbon::parse('2026-02-01 00:00'));

    $ids = Order::query()
        ->tap(fn ($q) => TimeAnchors::applyAfter($q, 'created_at', Carbon::parse('2026-01-15')))
        ->pluck('id');

    expect($ids)->toContain($recent->id)->not->toContain($old->id);
});

it('applyBefore paid: alleen orders betaald voor het moment', function () {
    $old = timeAnchorsOrder();
    addPaidPayment($old, Carbon::parse('2026-01-01 00:00'));

    $recent = timeAnchorsOrder();
    addPaidPayment($recent, Carbon::parse('2026-02-01 00:00'));

    $unpaid = timeAnchorsOrder();

    $ids = Order::query()
        ->tap(fn ($q) => TimeAnchors::applyBefore($q, 'paid', Carbon::parse('2026-01-15')))
        ->pluck('id');

    expect($ids)->toContain($old->id)
        ->not->toContain($recent->id)
        ->not->toContain($unpaid->id);
});

it('applyAfter paid: alleen orders betaald na het moment (horizon-ondergrens)', function () {
    $old = timeAnchorsOrder();
    addPaidPayment($old, Carbon::parse('2026-01-01 00:00'));

    $recent = timeAnchorsOrder();
    addPaidPayment($recent, Carbon::parse('2026-02-01 00:00'));

    $unpaid = timeAnchorsOrder();

    $ids = Order::query()
        ->tap(fn ($q) => TimeAnchors::applyAfter($q, 'paid', Carbon::parse('2026-01-15')))
        ->pluck('id');

    expect($ids)->toContain($recent->id)
        ->not->toContain($old->id)
        ->not->toContain($unpaid->id);
});
