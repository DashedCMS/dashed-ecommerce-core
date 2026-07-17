<?php

use Dashed\DashedCore\Models\User;
use Dashed\DashedCore\Models\Customsetting;
use Dashed\DashedEcommerceCore\Models\Order;

/**
 * Task 5: `label-options` moet naast de basis-selects (carrier/package_type/
 * delivery_type, group:'basis') ook de provider-specifieke extra-velden
 * (group:'extra') meesturen — bv. MyParcel::extraLabelOptions() levert
 * 'signature', 'insurance', 'age_check', ...
 *
 * NOOT (zie task-5-report.md): deze test kan niet standalone in de
 * ec-core-testbench draaien. `dashed-ecommerce-myparcel` /
 * `dashed-ecommerce-veloyd` zijn geen require-dev van dit package, dus
 * class_exists(MyParcel::class) is hier altijd false en labelOptions() geeft
 * een lege providers-lijst terug ongeacht de Customsetting hieronder. De test
 * documenteert het verwachte contract en draait pas groen in een omgeving
 * (bv. de volledige dashed-cms-app) waar de MyParcel-provider-package
 * geladen is.
 */
it('includes provider extra fields (group: extra) alongside the basis selects (group: basis)', function () {
    if (! class_exists(\Dashed\DashedEcommerceMyParcel\Classes\MyParcel::class)) {
        $this->markTestSkipped('dashed-ecommerce-myparcel is niet geladen in deze testomgeving.');
    }

    $this->actingAs(User::factory()->create(['role' => 'admin']), 'sanctum');

    Customsetting::set('my_parcel_api_key', 'dummy-key-voor-test', 'site');

    $order = Order::create([
        'site_id' => 'site',
        'email' => 'klant@example.com',
        'invoice_id' => 'INV-' . strtoupper(uniqid()),
        'status' => 'paid',
        'country' => 'NL',
    ]);

    $res = $this->getJson("/api/v1/orders/{$order->id}/label-options", ['X-Site-Id' => 'site']);

    $res->assertOk();

    $myparcel = collect($res->json('providers'))->firstWhere('provider', 'myparcel');
    expect($myparcel)->not->toBeNull();

    $fields = collect($myparcel['fields']);

    $carrier = $fields->firstWhere('name', 'carrier');
    expect($carrier)->not->toBeNull()
        ->and($carrier['group'])->toBe('basis');

    $signature = $fields->firstWhere('name', 'signature');
    expect($signature)->not->toBeNull()
        ->and($signature['group'])->toBe('extra');
});
