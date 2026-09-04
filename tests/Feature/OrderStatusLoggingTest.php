<?php

use Dashed\DashedCore\Models\User;
use Illuminate\Support\Facades\Mail;
use Dashed\DashedEcommerceCore\Models\Order;
use Dashed\DashedEcommerceCore\Models\OrderLog;

uses(\Illuminate\Foundation\Testing\RefreshDatabase::class);

beforeEach(function () {
    Mail::fake();
});

function statusLogOrder(string $fulfillmentStatus = 'unhandled'): Order
{
    return Order::create([
        'first_name' => 'Klant',
        'last_name' => 'Jansen',
        'email' => 'klant@example.com',
        'status' => 'paid',
        'fulfillment_status' => $fulfillmentStatus,
        'invoice_id' => 'LOG-' . uniqid(),
        'total' => 100,
        'subtotal' => 100,
    ]);
}

it('logt een automatische fulfilment-statuswijziging als systeemregel in de orderlogs', function () {
    $order = statusLogOrder();

    $order->changeFulfillmentStatus('shipped', 'Automatisch: alle zendingen zijn onderweg.');

    $log = OrderLog::where('order_id', $order->id)
        ->where('tag', 'order.changed-fulfillment-status-to-shipped')
        ->first();

    expect($log)->not->toBeNull();
    expect($log->user_id)->toBeNull();
    expect((bool) $log->is_system)->toBeTrue();
    expect($log->note)->toBe('Automatisch: alle zendingen zijn onderweg.');
    expect($log->tag())->toStartWith('Systeem ');
});

it('logt een fulfilment-statuswijziging door een ingelogde beheerder met die gebruiker erbij', function () {
    $user = User::factory()->create(['role' => 'superadmin']);
    $this->actingAs($user);

    $order = statusLogOrder();
    $order->changeFulfillmentStatus('handled');

    $log = OrderLog::where('order_id', $order->id)
        ->where('tag', 'order.changed-fulfillment-status-to-handled')
        ->first();

    expect($log)->not->toBeNull();
    expect($log->user_id)->toBe($user->id);
});

it('logt niets als de fulfilment-status niet verandert', function () {
    $order = statusLogOrder('shipped');

    $order->changeFulfillmentStatus('shipped');

    expect(OrderLog::where('order_id', $order->id)->count())->toBe(0);
});

it('logt precies een regel per statuswijziging, ook een keten van wijzigingen', function () {
    $order = statusLogOrder();

    $order->changeFulfillmentStatus('shipped');
    $order->changeFulfillmentStatus('handled');

    expect(OrderLog::where('order_id', $order->id)->where('tag', 'like', 'order.changed-fulfillment-status-to-%')->count())->toBe(2);
});

it('logt het aanmaken van een track & trace-code in de orderlogs', function () {
    $order = statusLogOrder();

    $order->addTrackAndTrace('myparcel', 'PostNL', '3STEST123456', 'https://tracking.example/3STEST123456');

    $log = OrderLog::where('order_id', $order->id)
        ->where('tag', 'order.track-and-trace.created')
        ->first();

    expect($log)->not->toBeNull();
    expect($log->note)->toContain('3STEST123456');
    expect($log->note)->toContain('PostNL');
});

it('rendert de labelstatus-tags leesbaar in plaats van als onbekende tag', function () {
    $order = statusLogOrder();

    $synced = new OrderLog();
    $synced->order_id = $order->id;
    $synced->tag = 'order.labelstatus.synced';
    $synced->is_system = true;
    $synced->save();

    $updated = new OrderLog();
    $updated->order_id = $order->id;
    $updated->tag = 'order.trackandtrace.updated';
    $updated->is_system = true;
    $updated->save();

    expect($synced->tag())->not->toContain('ERROR');
    expect($updated->tag())->not->toContain('ERROR');
    expect($synced->tag())->toStartWith('Systeem ');
    expect($updated->tag())->toStartWith('Systeem ');
});
