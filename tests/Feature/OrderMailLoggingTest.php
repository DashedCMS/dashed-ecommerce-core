<?php

use Illuminate\Mail\Mailable;
use Illuminate\Support\Facades\Mail;
use Dashed\DashedEcommerceCore\Models\Order;
use Dashed\DashedEcommerceCore\Models\OrderLog;
use Dashed\DashedEcommerceCore\Models\OrderReturn;

uses(\Illuminate\Foundation\Testing\RefreshDatabase::class);

beforeEach(function () {
    config(['mail.default' => 'array', 'queue.default' => 'sync']);
});

class TestOrderMail extends Mailable
{
    public function __construct(public Order $order) {}

    public function build()
    {
        return $this->html('<p>hoi</p>')->subject('Onderwerp X');
    }
}

class TestReturnMail extends Mailable
{
    public function __construct(public OrderReturn $orderReturn) {}

    public function build()
    {
        return $this->html('<p>hoi</p>')->subject('Retour X');
    }
}

class TestPlainMail extends Mailable
{
    public function build()
    {
        return $this->html('<p>hoi</p>')->subject('Geen order');
    }
}

it('logs a sent order mail to the order timeline', function () {
    $order = Order::create(['email' => 'klant@example.com', 'status' => 'paid', 'invoice_id' => 'INV-L1']);

    Mail::to('klant@example.com')->send(new TestOrderMail($order));

    $log = OrderLog::where('order_id', $order->id)->where('tag', 'order.email.sent')->first();

    expect($log)->not->toBeNull()
        ->and($log->email_subject)->toBe('Onderwerp X')
        ->and($log->note)->toContain('klant@example.com');
});

it('resolves the order from an orderReturn mailable', function () {
    $order = Order::create(['email' => 'a@b.nl', 'status' => 'paid', 'invoice_id' => 'INV-L2']);
    $return = OrderReturn::create(['order_id' => $order->id, 'email' => 'a@b.nl']);

    Mail::to('a@b.nl')->send(new TestReturnMail($return));

    expect(OrderLog::where('order_id', $order->id)->where('tag', 'order.email.sent')->exists())->toBeTrue();
});

it('does not log mail that cannot be attributed to an order', function () {
    Mail::to('iemand@example.com')->send(new TestPlainMail());

    expect(OrderLog::where('tag', 'order.email.sent')->count())->toBe(0);
});
