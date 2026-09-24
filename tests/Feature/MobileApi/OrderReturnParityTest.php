<?php

use Dashed\DashedCore\Models\User;
use Illuminate\Support\Facades\Mail;
use Dashed\DashedEcommerceCore\Models\Order;
use Dashed\DashedEcommerceCore\Models\OrderReturn;
use Dashed\DashedEcommerceCore\Models\OrderPayment;
use Dashed\DashedEcommerceCore\Models\OrderProduct;
use Dashed\DashedEcommerceCore\Models\ReturnReason;
use Dashed\DashedEcommerceCore\Models\OrderReturnLine;
use Dashed\DashedEcommerceCore\Mail\OrderReturn\OrderReturnCustomMail;
use Dashed\DashedEcommerceCore\Mail\OrderReturn\OrderReturnRefundedMail;

/**
 * CMS-pariteit voor de mobiele retouren: de Filament-acties die nog geen
 * mobile-route hadden — sluiten zonder creditering, terugbetaling registreren,
 * thread-reply, nieuwe retour aanmelden + retourneerbare regels.
 */
function paidOrderWithProduct(int $qty = 3, int $returned = 0, string $siteId = 'default'): array
{
    $order = Order::create([
        'site_id' => $siteId,
        'email' => 'klant@example.com',
        'invoice_id' => 'INV-' . strtoupper(uniqid()),
        'status' => 'paid',
    ]);
    $orderProduct = OrderProduct::create([
        'order_id' => $order->id,
        'name' => 'Testproduct',
        'quantity' => $qty,
        'returned_quantity' => $returned,
        'price' => 10.00,
    ]);

    return [$order, $orderProduct];
}

function approvedReturn(string $siteId = 'default'): OrderReturn
{
    [$order, $orderProduct] = paidOrderWithProduct(siteId: $siteId);
    $return = OrderReturn::create([
        'order_id' => $order->id,
        'site_id' => $siteId,
        'email' => 'klant@example.com',
        'status' => OrderReturn::STATUS_APPROVED,
        'requested_at' => now(),
        'approved_at' => now(),
    ]);
    OrderReturnLine::create(['order_return_id' => $return->id, 'order_product_id' => $orderProduct->id, 'quantity' => 2]);

    return $return->fresh();
}

it('sluit een goedgekeurde retour zonder creditering (met reden)', function () {
    $this->actingAs(User::factory()->create(['role' => 'admin']), 'sanctum');
    $return = approvedReturn();

    $this->postJson("/api/v1/returns/{$return->id}/close", ['reason' => 'Coulance'], ['X-Site-Id' => 'default'])
        ->assertOk()
        ->assertJsonPath('data.status', 'closed')
        ->assertJsonPath('data.closed_reason', 'Coulance');

    expect($return->fresh()->status)->toBe('closed');
});

it('weigert sluiten zonder reden (422)', function () {
    $this->actingAs(User::factory()->create(['role' => 'admin']), 'sanctum');
    $return = approvedReturn();

    $this->postJson("/api/v1/returns/{$return->id}/close", [], ['X-Site-Id' => 'default'])->assertStatus(422);
});

it('weigert sluiten van een niet-goedgekeurde retour (422)', function () {
    $this->actingAs(User::factory()->create(['role' => 'admin']), 'sanctum');
    [$order, $op] = paidOrderWithProduct();
    $return = OrderReturn::create(['order_id' => $order->id, 'site_id' => 'default', 'email' => 'k@e.nl', 'status' => OrderReturn::STATUS_REQUESTED, 'requested_at' => now()]);

    $this->postJson("/api/v1/returns/{$return->id}/close", ['reason' => 'x'], ['X-Site-Id' => 'default'])->assertStatus(422);
});

it('registreert een terugbetaling op een verwerkte retour met creditorder', function () {
    Mail::fake();
    $this->actingAs(User::factory()->create(['role' => 'admin']), 'sanctum');
    $return = approvedReturn();
    // Handmatig de verwerkte-staat opbouwen (ReturnProcessor/creditorder-flow is te
    // zwaar voor de harness): een creditorder van -20 koppelen en op 'handled' zetten.
    $credit = Order::create(['site_id' => 'default', 'email' => 'klant@example.com', 'invoice_id' => 'CREDIT-' . strtoupper(uniqid()), 'status' => 'return', 'total' => -20.00]);
    $return->update(['status' => OrderReturn::STATUS_HANDLED, 'handled_at' => now(), 'credit_order_id' => $credit->id]);

    $this->postJson("/api/v1/returns/{$return->id}/refund", ['amount' => 20, 'method' => 'Bankoverschrijving'], ['X-Site-Id' => 'default'])
        ->assertOk()
        ->assertJsonPath('data.is_refunded', true);

    expect((float) OrderPayment::where('order_id', $credit->id)->where('status', 'paid')->sum('amount'))->toBe(-20.0);
    Mail::assertQueued(OrderReturnRefundedMail::class);
});

it('weigert een terugbetaling boven het terugbetaalbare bedrag (422)', function () {
    $this->actingAs(User::factory()->create(['role' => 'admin']), 'sanctum');
    $return = approvedReturn();
    $credit = Order::create(['site_id' => 'default', 'email' => 'klant@example.com', 'invoice_id' => 'CREDIT-' . strtoupper(uniqid()), 'status' => 'return', 'total' => -20.00]);
    $return->update(['status' => OrderReturn::STATUS_HANDLED, 'handled_at' => now(), 'credit_order_id' => $credit->id]);

    $this->postJson("/api/v1/returns/{$return->id}/refund", ['amount' => 999, 'method' => 'Contant'], ['X-Site-Id' => 'default'])->assertStatus(422);
});

it('geeft terugbetaal-methodes terug (incl. Bankoverschrijving en Contant)', function () {
    $this->actingAs(User::factory()->create(['role' => 'admin']), 'sanctum');

    $methods = $this->getJson('/api/v1/returns/refund-methods', ['X-Site-Id' => 'default'])->assertOk()->json('methods');
    expect($methods)->toContain('Bankoverschrijving')->toContain('Contant');
});

it('plaatst een admin-antwoord in de thread en mailt de klant', function () {
    Mail::fake();
    $this->actingAs(User::factory()->create(['role' => 'admin']), 'sanctum');
    $return = approvedReturn();

    $res = $this->postJson("/api/v1/returns/{$return->id}/reply", ['message' => 'We hebben je pakket ontvangen.'], ['X-Site-Id' => 'default']);
    $res->assertOk();
    expect(collect($res->json('data.messages'))->pluck('sender')->all())->toContain('admin');
    Mail::assertQueued(OrderReturnCustomMail::class);
});

it('toont retourneerbare regels + actieve redenen voor een order', function () {
    $this->actingAs(User::factory()->create(['role' => 'admin']), 'sanctum');
    [$order, $op] = paidOrderWithProduct(qty: 3, returned: 1);
    ReturnReason::create(['site_id' => 'default', 'label' => ['nl' => 'Beschadigd', 'en' => 'Damaged'], 'is_active' => true, 'sort_order' => 1]);

    $res = $this->getJson("/api/v1/orders/{$order->id}/returnable-lines", ['X-Site-Id' => 'default'])->assertOk();
    expect($res->json('lines.0.order_product_id'))->toBe($op->id)
        ->and($res->json('lines.0.remaining'))->toBe(2)
        ->and(collect($res->json('reasons'))->pluck('label')->all())->toContain('Beschadigd');
});

it('meldt als beheerder een nieuwe RMA-retour aan (direct goedgekeurd)', function () {
    Mail::fake();
    $this->actingAs(User::factory()->create(['role' => 'admin']), 'sanctum');
    [$order, $op] = paidOrderWithProduct(qty: 3);

    $res = $this->postJson("/api/v1/orders/{$order->id}/register-return", [
        'lines' => [['order_product_id' => $op->id, 'quantity' => 1]],
        'admin_note' => 'Klant belde',
        'notify_customer' => false,
    ], ['X-Site-Id' => 'default']);

    // JsonResource op een net-aangemaakt model → 201 Created (2xx = ok voor de app).
    $res->assertCreated()->assertJsonPath('data.status', 'approved');
    expect(OrderReturn::where('order_id', $order->id)->count())->toBe(1)
        ->and($order->fresh()->retour_status)->toBe('waiting_for_return');
});
