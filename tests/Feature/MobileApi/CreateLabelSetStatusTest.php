<?php

use Dashed\DashedCore\Models\User;
use Dashed\DashedEcommerceCore\Models\Order;
use Dashed\DashedEcommerceCore\Http\Controllers\Api\V1\OrderController;

/**
 * Optionele fulfilment-status bij label-aanmaak: het veld wordt gevalideerd
 * tegen de echte statussen en mag nooit als provider-override doorlekken.
 */
it('create-label: weigert een onbekende set_fulfillment_status', function () {
    $this->actingAs(User::factory()->create(['role' => 'admin']), 'sanctum');

    $order = Order::create([
        'site_id' => 'site',
        'email' => 'klant@example.com',
        'invoice_id' => 'INV-' . strtoupper(uniqid()),
        'status' => 'paid',
    ]);

    $res = $this->postJson("/api/v1/orders/{$order->id}/create-label", [
        'set_fulfillment_status' => 'bestaat_niet',
    ], ['X-Site-Id' => 'site']);

    $res->assertStatus(422);
});

it('create-label: set_fulfillment_status lekt niet mee in de provider-overrides', function () {
    $overrides = OrderController::mergeLabelOverrides([
        'carrier' => 'PostNL',
        'set_fulfillment_status' => 'packed',
        'signature' => true,
    ]);

    expect($overrides)->toBe(['carrier' => 'PostNL', 'signature' => true])
        ->not->toHaveKey('set_fulfillment_status');
});
