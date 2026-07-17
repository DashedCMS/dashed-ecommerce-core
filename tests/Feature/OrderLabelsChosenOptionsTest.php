<?php

use Dashed\DashedCore\Models\User;
use Dashed\DashedCore\Models\Customsetting;
use Dashed\DashedEcommerceCore\Models\Order;
use Dashed\DashedEcommerceMyParcel\Models\MyParcelOrder;

/**
 * Task 7: de `labels`-serializer moet per label een leesbare `chosen_options`
 * lijst teruggeven — basis (vervoerder/pakkettype/verzendtype, code→label via
 * de provider-getters) + extra (`MyParcel::readOptionsForDisplay()`).
 *
 * NOOT (zie ook OrderLabelOptionsTest / task-5-report.md): deze test kan niet
 * standalone in de ec-core-testbench draaien — `dashed-ecommerce-myparcel` is
 * geen require-dev van dit package, dus class_exists(MyParcelOrder::class) is
 * hier altijd false. De test documenteert het verwachte contract en draait
 * pas groen in een omgeving (bv. de volledige dashed-cms-app) waar de
 * MyParcel-provider-package geladen is.
 */
it('returns chosen_options with mapped basis fields and readable extra options', function () {
    if (! class_exists(MyParcelOrder::class)) {
        $this->markTestSkipped('dashed-ecommerce-myparcel is niet geladen in deze testomgeving.');
    }

    $this->actingAs(User::factory()->create(['role' => 'admin']), 'sanctum');

    // labels() bouwt ook de 'providers'-lijst op (labelProviders()), die de
    // API-sleutel via MyParcel::apiKey() met een strict `string`-returntype
    // uitleest — zonder Customsetting geeft dat een TypeError. Zie ook
    // OrderLabelOptionsTest (Task 5) voor hetzelfde patroon.
    Customsetting::set('my_parcel_api_key', 'dummy-key-voor-test', 'site');

    $order = Order::create([
        'site_id' => 'site',
        'email' => 'klant@example.com',
        'invoice_id' => 'INV-' . strtoupper(uniqid()),
        'status' => 'paid',
        'country' => 'NL',
    ]);

    $mp = MyParcelOrder::create([
        'order_id' => $order->id,
        'shipment_id' => 'shipment-123',
        'carrier' => \MyParcelNL\Sdk\src\Model\Carrier\CarrierPostNL::class,
        'package_type' => '1',
        'options' => ['signature' => true],
    ]);

    $res = $this->getJson("/api/v1/orders/{$order->id}/labels", ['X-Site-Id' => 'site']);

    $res->assertOk();

    $label = collect($res->json('data'))->firstWhere('id', $mp->id);
    expect($label)->not->toBeNull()
        ->and($label)->toHaveKey('chosen_options');

    $chosenOptions = collect($label['chosen_options']);

    $packageType = $chosenOptions->firstWhere('key', 'package_type');
    expect($packageType)->not->toBeNull()
        ->and($packageType['label'])->toBe('Pakkettype')
        ->and($packageType['value'])->toBe(\Dashed\DashedEcommerceMyParcel\Classes\MyParcel::getPackageTypes()['1']);

    $signature = $chosenOptions->firstWhere('key', 'signature');
    expect($signature)->not->toBeNull()
        ->and($signature['value'])->toBe('Ja');
});

it('never errors and only shows the basis fields when options is null (legacy record)', function () {
    if (! class_exists(MyParcelOrder::class)) {
        $this->markTestSkipped('dashed-ecommerce-myparcel is niet geladen in deze testomgeving.');
    }

    $this->actingAs(User::factory()->create(['role' => 'admin']), 'sanctum');

    // labels() bouwt ook de 'providers'-lijst op (labelProviders()), die de
    // API-sleutel via MyParcel::apiKey() met een strict `string`-returntype
    // uitleest — zonder Customsetting geeft dat een TypeError. Zie ook
    // OrderLabelOptionsTest (Task 5) voor hetzelfde patroon.
    Customsetting::set('my_parcel_api_key', 'dummy-key-voor-test', 'site');

    $order = Order::create([
        'site_id' => 'site',
        'email' => 'klant@example.com',
        'invoice_id' => 'INV-' . strtoupper(uniqid()),
        'status' => 'paid',
        'country' => 'NL',
    ]);

    $mp = MyParcelOrder::create([
        'order_id' => $order->id,
        'shipment_id' => 'shipment-456',
        'carrier' => \MyParcelNL\Sdk\src\Model\Carrier\CarrierPostNL::class,
        'package_type' => '1',
        'options' => null,
    ]);

    $res = $this->getJson("/api/v1/orders/{$order->id}/labels", ['X-Site-Id' => 'site']);

    $res->assertOk();

    $label = collect($res->json('data'))->firstWhere('id', $mp->id);
    $chosenOptions = collect($label['chosen_options']);

    expect($chosenOptions->firstWhere('key', 'package_type'))->not->toBeNull()
        ->and($chosenOptions->firstWhere('key', 'signature'))->toBeNull();
});
