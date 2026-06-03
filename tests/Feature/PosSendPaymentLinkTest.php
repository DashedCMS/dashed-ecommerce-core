<?php

use Illuminate\Support\Str;
use Illuminate\Support\Facades\Mail;
use Dashed\DashedEcommerceCore\Models\Order;
use Dashed\DashedEcommerceCore\Mail\PaymentLinkMail;

function makeOutstandingOrder(array $attributes = []): Order
{
    $base = [
        'first_name' => 'Test',
        'last_name' => 'Klant',
        'email' => 'klant@example.com',
        'hash' => Str::random(32),
        'total' => 50.00,
        'status' => 'waiting_for_confirmation',
    ];

    $merged = array_merge($base, $attributes);

    // email cannot be NULL at DB level; create with a placeholder and then nullify if needed
    $nullifyEmail = array_key_exists('email', $attributes) && $attributes['email'] === null;
    if ($nullifyEmail) {
        $merged['email'] = 'placeholder@example.com';
    }

    $order = Order::create($merged);

    if ($nullifyEmail) {
        $order->email = null;
        $order->saveQuietly();
    }

    return $order;
}

it('sends a payment link mail to the order email', function () {
    Mail::fake();
    $order = makeOutstandingOrder();

    $response = $this->postJson(route('api.point-of-sale.send-payment-link'), [
        'orderId' => $order->id,
    ]);

    $response->assertOk()->assertJson(['success' => true]);
    Mail::assertSent(PaymentLinkMail::class, fn ($mail) => $mail->hasTo('klant@example.com'));
});

it('returns 404 when the order has no email', function () {
    Mail::fake();
    $order = makeOutstandingOrder(['email' => null]);

    $this->postJson(route('api.point-of-sale.send-payment-link'), ['orderId' => $order->id])
        ->assertStatus(404)
        ->assertJson(['success' => false]);

    Mail::assertNothingSent();
});

it('returns 422 when nothing is outstanding', function () {
    Mail::fake();
    $order = makeOutstandingOrder(['total' => 0]);

    $this->postJson(route('api.point-of-sale.send-payment-link'), ['orderId' => $order->id])
        ->assertStatus(422)
        ->assertJson(['success' => false]);

    Mail::assertNothingSent();
});

it('returns 404 when the order does not exist', function () {
    Mail::fake();

    $this->postJson(route('api.point-of-sale.send-payment-link'), ['orderId' => 999999])
        ->assertStatus(404);

    Mail::assertNothingSent();
});
