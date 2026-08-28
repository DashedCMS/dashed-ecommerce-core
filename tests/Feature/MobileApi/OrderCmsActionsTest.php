<?php

use Dashed\DashedCore\Models\User;
use Illuminate\Support\Facades\Mail;
use Dashed\DashedEcommerceCore\Models\Order;

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
    Mail::fake();
    $this->actingAs(User::factory()->create(['role' => 'admin']), 'sanctum');
    $order = makeCmsActionOrder();

    $res = $this->postJson("/api/v1/orders/{$order->id}/send-confirmation", [
        'email' => 'ander@example.com',
    ], ['X-Site-Id' => 'site']);

    $res->assertOk()->assertJsonPath('success', true);
});

it('valt terug op het order-e-mailadres als er geen adres is meegestuurd', function () {
    Mail::fake();
    $this->actingAs(User::factory()->create(['role' => 'admin']), 'sanctum');
    $order = makeCmsActionOrder();

    $this->postJson("/api/v1/orders/{$order->id}/send-confirmation", [], ['X-Site-Id' => 'site'])
        ->assertOk()
        ->assertJsonPath('success', true);
});

it('genereert de factuur opnieuw voor een order met een echte factuur', function () {
    Mail::fake();
    $this->actingAs(User::factory()->create(['role' => 'admin']), 'sanctum');
    $order = makeCmsActionOrder();

    $this->postJson("/api/v1/orders/{$order->id}/regenerate-invoice", [], ['X-Site-Id' => 'site'])
        ->assertOk()
        ->assertJsonPath('success', true);
});

it('weigert factuur-regeneratie op een proforma-order', function () {
    Mail::fake();
    $this->actingAs(User::factory()->create(['role' => 'admin']), 'sanctum');
    $order = makeCmsActionOrder(['invoice_id' => 'PROFORMA']);

    $this->postJson("/api/v1/orders/{$order->id}/regenerate-invoice", [], ['X-Site-Id' => 'site'])
        ->assertStatus(422)
        ->assertJsonPath('success', false);
});
