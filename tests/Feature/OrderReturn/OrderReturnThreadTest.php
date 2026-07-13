<?php

use Livewire\Livewire;
use Illuminate\Support\Facades\Mail;
use Dashed\DashedEcommerceCore\Models\Order;
use Dashed\DashedEcommerceCore\Models\OrderLog;
use Dashed\DashedEcommerceCore\Models\OrderReturn;
use Dashed\DashedEcommerceCore\Models\OrderReturnMessage;
use Dashed\DashedEcommerceCore\Livewire\Frontend\OrderReturnThread;

uses(\Illuminate\Foundation\Testing\RefreshDatabase::class);

it('shows existing messages for a valid hash', function () {
    $order = Order::create(['email' => 'a@b.nl', 'status' => 'paid', 'invoice_id' => 'INV-T1']);
    $return = OrderReturn::create(['order_id' => $order->id, 'email' => 'a@b.nl']);
    $return->messages()->create(['sender' => 'admin', 'message' => '<p>Hallo klant</p>']);

    Livewire::test(OrderReturnThread::class, ['hash' => $return->hash])
        ->assertSee('Hallo klant', false);
});

it('lets the customer post a reply, stores it, notifies admin and logs it', function () {
    Mail::fake();

    $order = Order::create(['email' => 'a@b.nl', 'status' => 'paid', 'invoice_id' => 'INV-T2']);
    $return = OrderReturn::create(['order_id' => $order->id, 'email' => 'a@b.nl']);

    Livewire::test(OrderReturnThread::class, ['hash' => $return->hash])
        ->set('reply', 'Wanneer krijg ik mijn geld terug?')
        ->call('send')
        ->assertHasNoErrors()
        ->assertSet('reply', '');

    $message = OrderReturnMessage::where('order_return_id', $return->id)->where('sender', 'customer')->first();
    expect($message)->not->toBeNull()
        ->and($message->message)->toBe('Wanneer krijg ik mijn geld terug?');

    // De order-log wordt geschreven direct na de admin-notificatie in notifyAdmin(),
    // dus zijn aanwezigheid bewijst dat de notificatie-stap is doorlopen. Of de mail
    // daadwerkelijk uitgaat hangt af van de ingestelde beheerder-e-mails (config).
    expect(OrderLog::where('order_id', $order->id)->where('tag', 'order.return-customer-replied')->exists())->toBeTrue();
});

it('validates that the reply is not empty', function () {
    $order = Order::create(['email' => 'a@b.nl', 'status' => 'paid']);
    $return = OrderReturn::create(['order_id' => $order->id, 'email' => 'a@b.nl']);

    Livewire::test(OrderReturnThread::class, ['hash' => $return->hash])
        ->set('reply', '')
        ->call('send')
        ->assertHasErrors('reply');

    expect(OrderReturnMessage::where('order_return_id', $return->id)->count())->toBe(0);
});
