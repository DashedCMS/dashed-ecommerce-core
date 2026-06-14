<?php

declare(strict_types=1);

use Dashed\DashedCore\Models\User;
use Dashed\DashedEcommerceCore\Models\Order;
use Dashed\DashedEcommerceCore\Models\OrderPayment;
use Dashed\DashedEcommerceCore\Models\PaymentMethod;
use Dashed\DashedEcommerceCore\Models\PosRegisterSession;

/**
 * Dagafsluiting / kasstaat (Z-rapport): één open sessie per (user + site + dag).
 * De omzet sinds opening wordt live per betaalmethode berekend uit POS-orders
 * (order_origin = 'pos', betaald, actieve site). De verwachte kas = startkas +
 * som contante betalingen. Bij afsluiten wordt het verschil weggeschreven.
 */
function makePaymentMethod(bool $isCash, string $name, string $siteId = 'site'): PaymentMethod
{
    $method = new PaymentMethod();
    $method->site_id = $siteId;
    $method->name = $name;
    $method->is_cash_payment = $isCash;
    $method->type = 'pos';
    $method->active = 1;
    $method->save();

    return $method;
}

function makePaidPosOrder(float $total, PaymentMethod $method, string $siteId = 'site'): Order
{
    $order = Order::create([
        'invoice_id' => 'POS-' . uniqid(),
        'status' => 'paid',
        'order_origin' => 'pos',
        'first_name' => 'Kassa',
        'last_name' => 'Klant',
        'total' => $total,
    ]);
    $order->site_id = $siteId;
    $order->save();

    $payment = new OrderPayment();
    $payment->order_id = $order->id;
    $payment->payment_method_id = $method->id;
    $payment->payment_method = $method->name;
    $payment->amount = $total;
    $payment->psp = 'own';
    $payment->status = 'paid';
    $payment->save();

    return $order;
}

it('opens a day, computes a per-method summary and closes with the correct difference', function () {
    $this->actingAs(User::factory()->create(['role' => 'admin']), 'sanctum');
    $headers = ['X-Site-Id' => 'site'];

    $cash = makePaymentMethod(true, 'Contant');
    $pin = makePaymentMethod(false, 'Pin');

    // Open de dag met startkas 50.
    $open = $this->postJson('/api/v1/point-of-sale/open-day', ['opening_float' => 50], $headers);
    $open->assertStatus(200)
        ->assertJsonPath('ok', true)
        ->assertJsonPath('already_open', false)
        ->assertJsonPath('session.opening_float', 50)
        ->assertJsonPath('session.is_open', true);

    // Twee betaalde POS-orders: 30 contant + 70 pin.
    makePaidPosOrder(30, $cash);
    makePaidPosOrder(70, $pin);

    $summary = $this->getJson('/api/v1/point-of-sale/day-summary', $headers);
    $summary->assertStatus(200)
        ->assertJsonPath('ok', true)
        ->assertJsonPath('opening_float', 50)
        ->assertJsonPath('order_count', 2)
        // verwachte kas = 50 startkas + 30 contant = 80
        ->assertJsonPath('expected_cash', 80);

    $totals = collect($summary->json('totals'))->keyBy('label');
    expect($totals['Contant']['total'])->toBe(30)
        ->and($totals['Contant']['is_cash'])->toBeTrue()
        ->and($totals['Contant']['count'])->toBe(1)
        ->and($totals['Pin']['total'])->toBe(70)
        ->and($totals['Pin']['is_cash'])->toBeFalse();

    // Afsluiten: geteld 75 → verschil = 75 - 80 = -5.
    $close = $this->postJson('/api/v1/point-of-sale/close-day', [
        'counted_cash' => 75,
        'notes' => 'Tekort van een vijfje',
    ], $headers);

    $close->assertStatus(200)
        ->assertJsonPath('ok', true)
        ->assertJsonPath('session.is_open', false)
        ->assertJsonPath('session.counted_cash', 75)
        ->assertJsonPath('session.expected_cash', 80)
        ->assertJsonPath('session.difference', -5);

    $closedTotals = collect($close->json('session.totals'))->keyBy('label');
    expect($closedTotals['Contant']['total'])->toBe(30)
        ->and($closedTotals['Pin']['total'])->toBe(70);

    $session = PosRegisterSession::first();
    expect($session->closed_at)->not->toBeNull();
});

it('returns the already-open session when opening twice', function () {
    $this->actingAs(User::factory()->create(['role' => 'admin']), 'sanctum');
    $headers = ['X-Site-Id' => 'site'];

    $first = $this->postJson('/api/v1/point-of-sale/open-day', ['opening_float' => 100], $headers);
    $first->assertStatus(200)->assertJsonPath('already_open', false);
    $sessionId = $first->json('session.id');

    $second = $this->postJson('/api/v1/point-of-sale/open-day', ['opening_float' => 999], $headers);
    $second->assertStatus(200)
        ->assertJsonPath('already_open', true)
        ->assertJsonPath('session.id', $sessionId)
        // De startkas blijft die van de eerste opening.
        ->assertJsonPath('session.opening_float', 100);

    expect(PosRegisterSession::count())->toBe(1);
});

it('returns 404 for day-summary when no session is open', function () {
    $this->actingAs(User::factory()->create(['role' => 'admin']), 'sanctum');

    $this->getJson('/api/v1/point-of-sale/day-summary', ['X-Site-Id' => 'site'])
        ->assertStatus(404)
        ->assertJsonPath('ok', false);
});

it('rejects open-day without the pos.use ability', function () {
    $this->actingAs(User::factory()->create(['role' => 'customer']), 'sanctum');

    $this->postJson('/api/v1/point-of-sale/open-day', ['opening_float' => 50], ['X-Site-Id' => 'site'])
        ->assertStatus(403);
});
