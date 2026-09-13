<?php

use Dashed\DashedCore\Models\User;
use Illuminate\Support\Facades\Mail;
use Dashed\DashedEcommerceCore\Models\Order;
use Dashed\DashedEcommerceCore\Mail\OrderNoteMail;

/**
 * Notitie bij een bestelling kan — net als in het CMS — optioneel als e-mail
 * naar de klant. De mail gaat alleen als de notitie publiek is én de
 * e-mailoptie aanstaat.
 */
beforeEach(fn () => Mail::fake());

function noteOrder(): Order
{
    return Order::create([
        'site_id' => 'site',
        'email' => 'klant@example.com',
        'invoice_id' => 'INV-' . strtoupper(uniqid()),
        'status' => 'paid',
    ]);
}

it('order-notitie: mailt de klant bij publiek + e-mailoptie', function () {
    $this->actingAs(User::factory()->create(['role' => 'admin']), 'sanctum');
    $order = noteOrder();

    $res = $this->postJson("/api/v1/orders/{$order->id}/notes", [
        'note' => 'Je pakket vertrekt morgen.',
        'public_for_customer' => true,
        'send_email_to_customer' => true,
        'email_subject' => 'Update over je bestelling',
    ], ['X-Site-Id' => 'site']);
    $res->assertOk();

    Mail::assertSent(OrderNoteMail::class);
});

it('order-notitie: mailt NIET bij een interne (niet-publieke) notitie', function () {
    $this->actingAs(User::factory()->create(['role' => 'admin']), 'sanctum');
    $order = noteOrder();

    $this->postJson("/api/v1/orders/{$order->id}/notes", [
        'note' => 'Interne memo.',
        'public_for_customer' => false,
        'send_email_to_customer' => true,
    ], ['X-Site-Id' => 'site'])->assertOk();

    Mail::assertNothingSent();
});
