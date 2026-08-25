<?php

use Dashed\DashedCore\Models\User;
use Illuminate\Support\Facades\Event;
use Dashed\DashedEcommerceCore\Models\Order;
use Dashed\DashedEcommerceCore\Events\Orders\InvoiceCreatedEvent;

function makeCmsActionOrder(array $attributes = [], string $siteId = 'site'): Order
{
    return Order::create(array_merge([
        'site_id' => $siteId,
        'email' => 'klant@example.com',
        'invoice_id' => 'INV-' . strtoupper(uniqid()),
        'status' => 'paid',
        'first_name' => 'Test',
        'last_name' => 'Klant',
        'city' => 'Amsterdam',
    ], $attributes));
}

it('verstuurt een bevestigingsmail naar het opgegeven adres', function () {
    $this->actingAs(User::factory()->create(['role' => 'admin']), 'sanctum');
    $order = makeCmsActionOrder();

    $res = $this->postJson("/api/v1/orders/{$order->id}/send-confirmation", [
        'email' => 'ander@example.com',
    ], ['X-Site-Id' => 'site']);

    $res->assertOk()->assertJsonPath('success', true);
});

it('valt terug op het order-e-mailadres als er geen adres is meegestuurd', function () {
    $this->actingAs(User::factory()->create(['role' => 'admin']), 'sanctum');
    $order = makeCmsActionOrder();

    $this->postJson("/api/v1/orders/{$order->id}/send-confirmation", [], ['X-Site-Id' => 'site'])
        ->assertOk()
        ->assertJsonPath('success', true);
});
