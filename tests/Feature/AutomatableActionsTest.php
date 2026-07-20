<?php

use Illuminate\Support\Facades\Http;
use Dashed\DashedCore\Models\User;
use Dashed\DashedEcommerceCore\Models\Order;
use Dashed\DashedEcommerceCore\Models\OrderLog;
use Dashed\DashedEcommerceCore\Models\PrintJob;
use Dashed\DashedEcommerceCore\Enums\PrintJobType;
use Dashed\DashedEcommerceCore\Enums\PrintJobStatus;
use Dashed\DashedMobileApi\MobileApiRegistry;
use Dashed\DashedMobileApi\Models\DeviceToken;

/**
 * Task 4: `automatable`-vlag + server-side handlers voor de zeven
 * fulfilment-acties + de nieuwe `notify_app`-actie.
 *
 * De handlers hier raken écht productie-side-effects aan (verzendlabel kost
 * portokosten, een printjob kost papier). Externe lagen (Veloyd/MyParcel,
 * de Expo-push) worden daarom altijd gespiedt/gefaked — nooit echt aangeroepen.
 */
function automatableActionsByKey(): \Illuminate\Support\Collection
{
    return collect(app(MobileApiRegistry::class)->orderActions())->keyBy('key');
}

function makeAutomatableOrder(array $attributes = []): Order
{
    return Order::create(array_merge([
        'site_id' => 'site',
        'email' => 'klant@example.com',
        'invoice_id' => 'INV-' . strtoupper(uniqid()),
        'status' => 'concept',
    ], $attributes));
}

afterEach(function () {
    Mockery::close();
});

// ── Step 1: de `automatable`-vlag staat op de juiste acties ────────────────

it('marks the fulfilment actions, send_confirmation/send_to_fulfillment/regenerate_invoice and notify_app automatable', function () {
    $byKey = automatableActionsByKey();

    foreach ([
        'send_confirmation', 'send_to_fulfillment', 'regenerate_invoice', 'notify_app',
        'mark_packed', 'create_label', 'print_label', 'print_packing_slip',
        'print_invoice', 'set_fulfillment_status', 'mark_paid',
    ] as $key) {
        expect($byKey->has($key))->toBeTrue("ontbreekt: {$key}")
            ->and($byKey[$key]['automatable'] ?? null)->toBeTrue("verwacht automatable=true voor: {$key}");
    }
});

it('keeps track_and_trace, cancel, manual_payment, payment_link and retour_status non-automatable', function () {
    $byKey = automatableActionsByKey();

    foreach (['track_and_trace', 'cancel', 'manual_payment', 'payment_link', 'retour_status'] as $key) {
        expect($byKey[$key]['automatable'] ?? null)->toBeFalse("verwacht automatable=false voor: {$key}");
    }
});

// ── Step 1: de zeven fulfilment-acties krijgen een callable handle ─────────

it('gives the seven fulfilment steps a callable handle while keeping them hidden from the per-order list', function () {
    $byKey = automatableActionsByKey();

    foreach (['mark_packed', 'create_label', 'print_label', 'print_packing_slip', 'print_invoice', 'set_fulfillment_status', 'mark_paid'] as $key) {
        expect($byKey[$key]['handle'] ?? null)->toBeCallable("ontbreekt handle voor: {$key}")
            ->and(($byKey[$key]['visible'])())->toBeFalse();
    }
});

// ── Step 1: notify_app bestaat met zijn velden ──────────────────────────────

it('registers notify_app with title/body/ability fields, defaulting ability to orders.read', function () {
    $action = automatableActionsByKey()['notify_app'] ?? null;

    expect($action)->not->toBeNull()
        ->and($action['automatable'])->toBeTrue()
        ->and($action['handle'] ?? null)->toBeCallable();

    $fields = collect($action['fields'])->keyBy('name');
    expect($fields['title']['required'])->toBeTrue()
        ->and($fields['title']['type'])->toBe('text')
        ->and($fields['body']['required'])->toBeFalse()
        ->and($fields['ability']['type'])->toBe('select')
        ->and($fields['ability']['default'])->toBe('orders.read')
        ->and($fields['ability']['options'])->toBeArray()->toHaveKey('orders.read');
});

// ── Regression: visible=>false blijft de losse endpoint blokkeren ──────────

it('still 422s a direct POST to a hidden fulfilment action, even though it now has a handle', function () {
    $this->actingAs(User::factory()->create(['role' => 'admin']), 'sanctum');
    $order = makeAutomatableOrder();

    $res = $this->postJson("/api/v1/orders/{$order->id}/actions/mark_packed", [], ['X-Site-Id' => 'site']);

    $res->assertStatus(422);
    expect($order->fresh()->packed_at)->toBeNull();
});

it('exposes automatable on the actions() endpoint, and keeps the seven fulfilment steps out of the per-order list', function () {
    $this->actingAs(User::factory()->create(['role' => 'admin']), 'sanctum');
    $order = makeAutomatableOrder();

    $res = $this->getJson("/api/v1/orders/{$order->id}/actions", ['X-Site-Id' => 'site']);
    $res->assertOk();

    $data = collect($res->json('data'))->keyBy('key');
    expect($data['send_confirmation']['automatable'])->toBeTrue()
        ->and($data['cancel']['automatable'])->toBeFalse()
        ->and($data->has('mark_packed'))->toBeFalse();
});

// ── Step 4: elke handler roept dezelfde onderliggende laag aan als het CMS ─

it('mark_packed sets packed_at, syncs the fulfilment status to packed and logs it', function () {
    $order = makeAutomatableOrder(['fulfillment_status' => 'unhandled']);
    $handle = automatableActionsByKey()['mark_packed']['handle'];

    $handle($order, []);

    $order->refresh();
    expect($order->packed_at)->not->toBeNull()
        ->and($order->fulfillment_status)->toBe('packed');
    expect(OrderLog::where('order_id', $order->id)->where('tag', 'order.packed')->exists())->toBeTrue();
});

it('create_label calls Veloyd::createLabelForOrder when Veloyd is configured for the site', function () {
    // Veloyd/MyParcel zijn plain static-method-klassen die al geladen zijn
    // vóór de test draait (package auto-discovery) — een Mockery alias-mock
    // ("cannot redeclare class") is daardoor niet bruikbaar. De handler haalt
    // de klasse daarom via de container op, wat een instance-mock hier wél
    // laat intercepten (functioneel identiek in productie: geen bindings →
    // gewoon `new Veloyd()`).
    $veloyd = Mockery::mock(\Dashed\DashedEcommerceVeloyd\Classes\Veloyd::class);
    $veloyd->shouldReceive('apiKey')->andReturn('a-fake-veloyd-key');
    $veloyd->shouldReceive('createLabelForOrder')->once()
        ->withArgs(fn (Order $passedOrder) => true)
        ->andReturn(['ok' => true]);
    $this->app->instance(\Dashed\DashedEcommerceVeloyd\Classes\Veloyd::class, $veloyd);

    $order = makeAutomatableOrder();
    $handle = automatableActionsByKey()['create_label']['handle'];

    // Geen exception/live-call betekent: de spy hierboven is aangeroepen (en
    // Mockery verifieert de `->once()`-verwachting in afterEach via close()).
    $handle($order, []);
});

it('create_label falls back to MyParcel::createLabelForOrder when Veloyd is not configured', function () {
    $veloyd = Mockery::mock(\Dashed\DashedEcommerceVeloyd\Classes\Veloyd::class);
    $veloyd->shouldReceive('apiKey')->andReturn('');
    $this->app->instance(\Dashed\DashedEcommerceVeloyd\Classes\Veloyd::class, $veloyd);

    $myParcel = Mockery::mock(\Dashed\DashedEcommerceMyParcel\Classes\MyParcel::class);
    $myParcel->shouldReceive('apiKey')->andReturn('a-fake-myparcel-key');
    $myParcel->shouldReceive('createLabelForOrder')->once()
        ->withArgs(fn (Order $passedOrder) => true)
        ->andReturn(['ok' => true]);
    $this->app->instance(\Dashed\DashedEcommerceMyParcel\Classes\MyParcel::class, $myParcel);

    $order = makeAutomatableOrder();
    $handle = automatableActionsByKey()['create_label']['handle'];

    $handle($order, []);
});

it('print_label, print_packing_slip and print_invoice queue a PrintJob of the right type via the print-queue', function () {
    $order = makeAutomatableOrder();
    $byKey = automatableActionsByKey();

    ($byKey['print_label']['handle'])($order, []);
    ($byKey['print_packing_slip']['handle'])($order, []);
    ($byKey['print_invoice']['handle'])($order, []);

    foreach ([PrintJobType::ShippingLabel, PrintJobType::PackingSlip, PrintJobType::Invoice] as $type) {
        expect(PrintJob::where('order_id', $order->id)
            ->where('type', $type->value)
            ->where('status', PrintJobStatus::Pending->value)
            ->exists())->toBeTrue("geen printjob voor: {$type->value}");
    }
    expect(PrintJob::where('order_id', $order->id)->count())->toBe(3);
});

it('does not queue a duplicate PrintJob while one is still pending for the same type', function () {
    $order = makeAutomatableOrder();
    $handle = automatableActionsByKey()['print_label']['handle'];

    $handle($order, []);
    $handle($order, []);

    expect(PrintJob::where('order_id', $order->id)->where('type', PrintJobType::ShippingLabel->value)->count())->toBe(1);
});

it('set_fulfillment_status changes the fulfilment status via Order::changeFulfillmentStatus', function () {
    $order = makeAutomatableOrder(['fulfillment_status' => 'unhandled']);
    $handle = automatableActionsByKey()['set_fulfillment_status']['handle'];

    $handle($order, ['status' => 'handled']);

    expect($order->fresh()->fulfillment_status)->toBe('handled');
});

it('mark_paid marks the order as paid via Order::markAsPaid', function () {
    // 'waiting_for_confirmation' neemt in markAsPaid() het lichte pad (status +
    // log), zonder de facturatie-/voorraad-/marketing-jobs van een verse
    // betaling te dispatchen — dat hoort niet bij dít contract (reuse van de
    // markAsPaid-laag, niet een test van heel de betaal-afhandeling).
    $order = makeAutomatableOrder(['status' => 'waiting_for_confirmation']);
    $handle = automatableActionsByKey()['mark_paid']['handle'];

    $handle($order, []);

    expect($order->fresh()->status)->toBe('paid');
    expect(OrderLog::where('order_id', $order->id)->where('tag', 'order.marked-as-paid')->exists())->toBeTrue();
});

it('notify_app sends a push via NotificationCenter, routed to the order', function () {
    Http::fake();

    $user = User::factory()->create(['role' => 'admin']);
    DeviceToken::create([
        'user_id' => $user->id,
        'platform' => 'ios',
        'token' => 'ExponentPushToken[xxxxxxxxxxxxxxxxxxxxxx]',
    ]);

    $order = makeAutomatableOrder();
    $handle = automatableActionsByKey()['notify_app']['handle'];

    $handle($order, ['title' => 'Bestelling wacht', 'body' => 'Even checken', 'ability' => 'orders.read']);

    Http::assertSent(function ($request) use ($order) {
        $body = json_decode($request->body(), true);

        return is_array($body) && collect($body)->contains(
            fn ($message) => ($message['data']['route'] ?? null) === "/order/{$order->id}"
        );
    });
});
