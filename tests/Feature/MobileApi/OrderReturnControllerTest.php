<?php

use Dashed\DashedCore\Models\User;
use Illuminate\Support\Facades\Mail;
use Dashed\DashedEcommerceCore\Models\Order;
use Dashed\DashedEcommerceCore\Models\OrderReturn;
use Dashed\DashedEcommerceCore\Models\OrderProduct;
use Dashed\DashedEcommerceCore\Models\OrderReturnLine;
use Dashed\DashedEcommerceCore\Mail\OrderReturn\OrderReturnApprovedMail;
use Dashed\DashedEcommerceCore\Mail\OrderReturn\OrderReturnRejectedMail;
use Dashed\DashedEcommerceCore\Mail\OrderReturn\OrderReturnCustomMail;

/**
 * Bouwt een order + één orderproduct + een retour met één regel op de gegeven site.
 * @return array{order: Order, orderProduct: OrderProduct, return: OrderReturn}
 */
function makeReturn(array $returnAttributes = [], string $siteId = 'site', int $orderedQty = 3): array
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
        'quantity' => $orderedQty,
        'returned_quantity' => 0,
        'price' => 10.00,
    ]);
    $return = OrderReturn::create(array_merge([
        'order_id' => $order->id,
        'site_id' => $siteId,
        'email' => 'klant@example.com',
        'customer_note' => 'Past niet',
    ], $returnAttributes));
    OrderReturnLine::create([
        'order_return_id' => $return->id,
        'order_product_id' => $orderProduct->id,
        'quantity' => 2,
    ]);

    return ['order' => $order, 'orderProduct' => $orderProduct, 'return' => $return->fresh()];
}

it('lists returns for the active site, newest first, filtered by status', function () {
    $this->actingAs(User::factory()->create(['role' => 'admin']), 'sanctum');

    $req = makeReturn()['return'];
    $handled = makeReturn(['status' => OrderReturn::STATUS_HANDLED])['return'];
    makeReturn([], 'other'); // andere site → niet zichtbaar

    $all = $this->getJson('/api/v1/returns', ['X-Site-Id' => 'site']);
    $all->assertOk();
    $ids = collect($all->json('data'))->pluck('id')->all();
    expect($ids)->toContain($req->id)->toContain($handled->id)
        ->and(count($ids))->toBe(2);

    $onlyRequested = $this->getJson('/api/v1/returns?status=requested', ['X-Site-Id' => 'site']);
    expect(collect($onlyRequested->json('data'))->pluck('id')->all())->toBe([$req->id]);
});

it('shows a return with lines and status label', function () {
    $this->actingAs(User::factory()->create(['role' => 'admin']), 'sanctum');
    $return = makeReturn()['return'];

    $res = $this->getJson("/api/v1/returns/{$return->id}", ['X-Site-Id' => 'site']);

    $res->assertOk()
        ->assertJsonPath('data.id', $return->id)
        ->assertJsonPath('data.status', 'requested')
        ->assertJsonPath('data.status_label', 'Aangevraagd')
        ->assertJsonPath('data.order.invoice_id', $return->order->invoice_id)
        ->assertJsonPath('data.lines.0.product_name', 'Testproduct')
        ->assertJsonPath('data.lines.0.quantity', 2);
});

it('returns 404 for a return on another site', function () {
    $this->actingAs(User::factory()->create(['role' => 'admin']), 'sanctum');
    $other = makeReturn([], 'other')['return'];

    $this->getJson("/api/v1/returns/{$other->id}", ['X-Site-Id' => 'site'])->assertNotFound();
});

it('approves a requested return and sends the approval mail', function () {
    Mail::fake();
    $this->actingAs(User::factory()->create(['role' => 'admin']), 'sanctum');
    $return = makeReturn()['return'];

    $res = $this->postJson("/api/v1/returns/{$return->id}/approve", ['admin_note' => 'Akkoord'], ['X-Site-Id' => 'site']);

    $res->assertOk()->assertJsonPath('data.status', 'approved')->assertJsonPath('data.admin_note', 'Akkoord');
    Mail::assertQueued(OrderReturnApprovedMail::class);
});

it('rejects approving a non-requested return', function () {
    $this->actingAs(User::factory()->create(['role' => 'admin']), 'sanctum');
    $return = makeReturn(['status' => OrderReturn::STATUS_HANDLED])['return'];

    $this->postJson("/api/v1/returns/{$return->id}/approve", [], ['X-Site-Id' => 'site'])->assertStatus(422);
});

it('requires a reason to reject', function () {
    Mail::fake();
    $this->actingAs(User::factory()->create(['role' => 'admin']), 'sanctum');
    $return = makeReturn()['return'];

    $this->postJson("/api/v1/returns/{$return->id}/reject", [], ['X-Site-Id' => 'site'])->assertStatus(422);

    $ok = $this->postJson("/api/v1/returns/{$return->id}/reject", ['reason' => 'Buiten termijn'], ['X-Site-Id' => 'site']);
    $ok->assertOk()->assertJsonPath('data.status', 'rejected')->assertJsonPath('data.rejected_reason', 'Buiten termijn');
    Mail::assertQueued(OrderReturnRejectedMail::class);
});

it('handles a return: status handled, and restock bumps returned_quantity when opted in', function () {
    $this->actingAs(User::factory()->create(['role' => 'admin']), 'sanctum');
    ['return' => $return, 'orderProduct' => $op] = makeReturn(['status' => OrderReturn::STATUS_APPROVED]);

    $res = $this->postJson("/api/v1/returns/{$return->id}/handle", ['restock' => true, 'refund' => false], ['X-Site-Id' => 'site']);

    $res->assertOk()->assertJsonPath('data.status', 'handled');
    expect((int) $op->fresh()->returned_quantity)->toBe(2)
        ->and($return->order->fresh()->retour_status)->toBe('handled');
});

it('handles a return without restock and leaves returned_quantity untouched', function () {
    $this->actingAs(User::factory()->create(['role' => 'admin']), 'sanctum');
    ['return' => $return, 'orderProduct' => $op] = makeReturn(['status' => OrderReturn::STATUS_APPROVED]);

    $this->postJson("/api/v1/returns/{$return->id}/handle", [], ['X-Site-Id' => 'site'])->assertOk();

    expect((int) $op->fresh()->returned_quantity)->toBe(0);
});

it('rejects write actions without the orders.write ability', function () {
    // Een niet-geprivilegieerde gebruiker (geen rollen) heeft geen orders.write.
    $this->actingAs(User::factory()->create(['role' => 'customer']), 'sanctum');
    $return = makeReturn()['return'];

    $this->postJson("/api/v1/returns/{$return->id}/approve", [], ['X-Site-Id' => 'site'])->assertStatus(403);
});

it('returns 404 for a label when none is present', function () {
    $this->actingAs(User::factory()->create(['role' => 'admin']), 'sanctum');
    $return = makeReturn()['return'];

    $this->getJson("/api/v1/returns/{$return->id}/label", ['X-Site-Id' => 'site'])->assertNotFound();
});

it('returns email defaults for the compose sheet', function () {
    $this->actingAs(User::factory()->create(['role' => 'admin']), 'sanctum');

    $res = $this->getJson('/api/v1/returns/email-defaults', ['X-Site-Id' => 'site']);

    $res->assertOk();
    expect($res->json('subject'))->toBeString()->not->toBe('')
        ->and($res->json('message'))->toBeString()->not->toBe('')
        ->and($res->json('message'))->not->toContain('<'); // platte tekst, geen HTML-tags
});

it('sends a custom email to the customer and logs it', function () {
    Mail::fake();
    $this->actingAs(User::factory()->create(['role' => 'admin']), 'sanctum');
    ['return' => $return, 'order' => $order] = makeReturn();

    $res = $this->postJson("/api/v1/returns/{$return->id}/email", [
        'subject' => 'Update over je retour',
        'message' => "Beste klant,\n\nWe hebben je retour ontvangen.",
    ], ['X-Site-Id' => 'site']);

    $res->assertOk()->assertJsonPath('data.id', $return->id);
    Mail::assertQueued(OrderReturnCustomMail::class);
    // De verzonden-regel komt nu uit de globale MessageSent-listener; sendCustomEmail
    // schrijft geen eigen 'order.return-message-sent' meer.
    expect(\Dashed\DashedEcommerceCore\Models\OrderLog::where('order_id', $order->id)->where('tag', 'order.return-message-sent')->exists())->toBeFalse();
});

it('requires subject and message to send an email', function () {
    Mail::fake();
    $this->actingAs(User::factory()->create(['role' => 'admin']), 'sanctum');
    $return = makeReturn()['return'];

    $this->postJson("/api/v1/returns/{$return->id}/email", ['subject' => 'x'], ['X-Site-Id' => 'site'])->assertStatus(422);
    $this->postJson("/api/v1/returns/{$return->id}/email", ['message' => 'y'], ['X-Site-Id' => 'site'])->assertStatus(422);
    Mail::assertNothingQueued();
});

it('gates sending an email behind orders.write', function () {
    $this->actingAs(User::factory()->create(['role' => 'customer']), 'sanctum');
    $return = makeReturn()['return'];

    $this->postJson("/api/v1/returns/{$return->id}/email", ['subject' => 'x', 'message' => 'y'], ['X-Site-Id' => 'site'])->assertStatus(403);
});
