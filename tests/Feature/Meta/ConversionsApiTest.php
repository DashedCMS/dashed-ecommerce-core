<?php

use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Http;
use Dashed\DashedCore\Classes\Sites;
use Dashed\DashedCore\Models\Customsetting;
use Dashed\DashedEcommerceCore\Models\Order;
use Dashed\DashedEcommerceCore\Models\OrderPayment;
use Dashed\DashedEcommerceCore\Models\OrderProduct;
use Dashed\DashedEcommerceCore\Models\OrderTracking;
use Dashed\DashedEcommerceCore\Services\Meta\ConversionsApi;
use Dashed\DashedEcommerceCore\Services\Meta\MetaCapiSettings;
use Dashed\DashedEcommerceCore\Services\Meta\MetaPurchaseValue;

/**
 * Order van € 35,03: product € 27,08 (na € 3,87 korting, btw € 4,70), een
 * bundelcomponent van € 0 en verzending € 7,95 (btw € 1,38). Btw samen € 6,08.
 */
function capiOrder(array $attributes = []): Order
{
    $order = Order::create(array_merge([
        'status' => 'pending',
        'email' => ' Jan@Example.com ',
        'phone_number' => '06 12345678',
        'first_name' => 'Zoë',
        'last_name' => 'van der Berg',
        'zip_code' => '1234 AB',
        'city' => 'Den Haag',
        'country' => 'Nederland',
        'ip' => '203.0.113.7',
        'total' => 35.03,
        'subtotal' => 38.90,
        'btw' => 6.08,
        'discount' => 3.87,
    ], $attributes));

    // De creating-hook van Order zet ip op het request-IP; zet de bedoelde waarde terug.
    $order->forceFill(['ip' => array_key_exists('ip', $attributes) ? $attributes['ip'] : '203.0.113.7'])->saveQuietly();

    // De producten 501 en 502 bestaan niet in de testdatabase; alleen de FK-controle uit.
    Schema::disableForeignKeyConstraints();
    OrderProduct::create(['order_id' => $order->id, 'product_id' => 501, 'sku' => 'VAAS-1', 'name' => 'Vaas', 'quantity' => 2, 'price' => 27.08, 'discount' => 3.87, 'btw' => 4.70]);
    OrderProduct::create(['order_id' => $order->id, 'product_id' => 502, 'sku' => 'BUNDEL-DEEL', 'name' => 'Bundeldeel', 'quantity' => 1, 'price' => 0, 'btw' => 0]);
    OrderProduct::create(['order_id' => $order->id, 'product_id' => null, 'sku' => 'shipping_costs', 'name' => 'Verzending', 'quantity' => 1, 'price' => 7.95, 'btw' => 1.38]);
    Schema::enableForeignKeyConstraints();

    return $order->fresh(['orderProducts']);
}

function capiKlaar(): string
{
    $site = Sites::getActive();
    Customsetting::set('meta_capi_enabled', true, $site);
    Customsetting::set('facebook_pixel_conversion_id', '599475696120398', $site);
    MetaCapiSettings::for($site)->storeAccessToken('EAAB-geheim');

    return $site;
}

beforeEach(function () {
    Http::preventStrayRequests();
});

afterEach(fn () => Carbon::setTestNow());

it('berekent de waarde voor de drie value modes', function () {
    $order = capiOrder();

    expect(MetaPurchaseValue::for($order, 'incl_vat'))->toBe(35.03)
        ->and(MetaPurchaseValue::for($order, 'excl_vat'))->toBe(28.95)
        ->and(MetaPurchaseValue::for($order, 'excl_vat_excl_shipping'))->toBe(22.38);
});

it('geeft nooit een negatieve waarde', function () {
    $order = capiOrder(['total' => 0, 'btw' => 5]);

    expect(MetaPurchaseValue::for($order, 'excl_vat'))->toBe(0.0);
});

it('neemt alleen betaalde productregels op in contents, met het product-ID', function () {
    expect(MetaPurchaseValue::contents(capiOrder()))->toBe([
        ['id' => '501', 'quantity' => 2, 'item_price' => 13.54],
    ]);
});

it('bouwt custom_data volgens de value mode', function () {
    $order = capiOrder();

    $data = app(ConversionsApi::class)->buildCustomData($order, 'excl_vat');

    expect($data)->toMatchArray([
        'currency' => 'EUR',
        'value' => 28.95,
        'content_type' => 'product',
        'content_ids' => ['501'],
        'num_items' => 2,
    ])->and($data['order_id'])->toBe((string) $order->id)
        ->and($data['contents'])->toBe([['id' => '501', 'quantity' => 2, 'item_price' => 13.54]]);
});

it('hasht de klantgegevens en laat ip, user agent, fbp en fbc ongehasht', function () {
    $order = capiOrder();
    OrderTracking::create([
        'order_id' => $order->id,
        'meta_fbp' => 'fb.1.1700000000000.123',
        'meta_fbc' => 'fb.1.1700000000001.AbC',
        'client_user_agent' => 'Mozilla/5.0 (Test)',
        'marketing_consent' => true,
    ]);

    $userData = app(ConversionsApi::class)->buildUserData($order->fresh());

    expect($userData)->toBe([
        'em' => [hash('sha256', 'jan@example.com')],
        'ph' => [hash('sha256', '31612345678')],
        'fn' => [hash('sha256', 'zoe')],
        'ln' => [hash('sha256', 'van der berg')],
        'ct' => [hash('sha256', 'denhaag')],
        'zp' => [hash('sha256', '1234ab')],
        'country' => [hash('sha256', 'nl')],
        'external_id' => [hash('sha256', 'jan@example.com')],
        'client_ip_address' => '203.0.113.7',
        'client_user_agent' => 'Mozilla/5.0 (Test)',
        'fbp' => 'fb.1.1700000000000.123',
        'fbc' => 'fb.1.1700000000001.AbC',
    ]);
});

it('laat ontbrekende klantgegevens helemaal weg', function () {
    $order = capiOrder(['phone_number' => null, 'first_name' => '', 'last_name' => null, 'city' => ' ', 'zip_code' => null, 'ip' => null]);

    $userData = app(ConversionsApi::class)->buildUserData($order);

    expect(array_keys($userData))->toBe(['em', 'country', 'external_id']);
});

it('gebruikt het klant-ID als external_id wanneer de order aan een account hangt', function () {
    $order = capiOrder();
    $order->user_id = 42;

    expect(app(ConversionsApi::class)->buildUserData($order)['external_id'])->toBe([hash('sha256', '42')]);
});

it('bouwt een Purchase-event met het betaalmoment en het vaste event-ID', function () {
    capiKlaar();
    $order = capiOrder();
    OrderTracking::create(['order_id' => $order->id, 'event_source_url' => 'https://shop.test/checkout', 'marketing_consent' => true]);
    $payment = OrderPayment::create(['order_id' => $order->id, 'amount' => 35.03, 'status' => 'paid', 'psp' => 'paynl', 'payment_method' => 'iDEAL']);
    $payment->forceFill(['created_at' => '2026-10-08 10:15:00'])->saveQuietly();
    Carbon::setTestNow('2026-10-08 10:20:00');

    $event = app(ConversionsApi::class)->purchaseEvent($order->fresh());

    expect($event['event_name'])->toBe('Purchase')
        ->and($event['event_id'])->toBe('purchase_' . $order->id)
        ->and($event['event_time'])->toBe(Carbon::parse('2026-10-08 10:15:00')->timestamp)
        ->and($event['action_source'])->toBe('website')
        ->and($event['event_source_url'])->toBe('https://shop.test/checkout')
        ->and($event['custom_data']['value'])->toBe(35.03)
        ->and($event['custom_data']['order_id'])->toBe((string) $order->id);
});

it('gebruikt nu als event_time wanneer de betaling ouder is dan zes dagen', function () {
    capiKlaar();
    $order = capiOrder();
    $payment = OrderPayment::create(['order_id' => $order->id, 'amount' => 35.03, 'status' => 'paid', 'psp' => 'paynl', 'payment_method' => 'iDEAL']);
    $payment->forceFill(['created_at' => '2026-09-20 10:00:00'])->saveQuietly();
    Carbon::setTestNow('2026-10-08 10:20:00');

    expect(app(ConversionsApi::class)->purchaseEvent($order->fresh())['event_time'])->toBe(Carbon::now()->timestamp);
});

it('post naar de pixel met het token in de body en niet in de URL', function () {
    $site = capiKlaar();
    Customsetting::set('meta_capi_test_event_code', 'TEST123', $site);
    Http::fake(['graph.facebook.com/*' => Http::response(['events_received' => 1, 'fbtrace_id' => 'abc'], 200)]);

    $result = app(ConversionsApi::class)->sendEvent($site, ['event_name' => 'Purchase', 'event_id' => 'purchase_1']);

    expect($result)->toBe(['ok' => true, 'status' => 200, 'body' => ['events_received' => 1, 'fbtrace_id' => 'abc']]);

    Http::assertSent(function ($request) {
        return $request->url() === 'https://graph.facebook.com/v26.0/599475696120398/events'
            && ! str_contains($request->url(), 'EAAB-geheim')
            && $request['access_token'] === 'EAAB-geheim'
            && $request['test_event_code'] === 'TEST123'
            && $request['data'] === [['event_name' => 'Purchase', 'event_id' => 'purchase_1']];
    });
});

it('stuurt geen test_event_code mee als die leeg is', function () {
    $site = capiKlaar();
    Http::fake(['graph.facebook.com/*' => Http::response(['events_received' => 1], 200)]);

    app(ConversionsApi::class)->sendEvent($site, ['event_name' => 'Purchase']);

    Http::assertSent(fn ($request) => ! array_key_exists('test_event_code', $request->data()));
});

it('geeft een fout van Meta terug zonder exception en zonder het token', function () {
    $site = capiKlaar();
    Http::fake(['graph.facebook.com/*' => Http::response(['error' => ['message' => 'Invalid OAuth access token', 'code' => 190]], 400)]);

    $result = app(ConversionsApi::class)->sendEvent($site, ['event_name' => 'Purchase']);

    expect($result['ok'])->toBeFalse()
        ->and($result['status'])->toBe(400)
        ->and($result['body']['error']['code'])->toBe(190)
        ->and(json_encode($result))->not->toContain('EAAB-geheim');
    Http::assertSentCount(1);
});

it('probeert het bij een storing bij Meta drie keer en meldt dan de fout', function () {
    $site = capiKlaar();
    Http::fake(['graph.facebook.com/*' => Http::response('Service Unavailable', 503)]);

    $result = app(ConversionsApi::class)->sendEvent($site, ['event_name' => 'Purchase']);

    expect($result['ok'])->toBeFalse()->and($result['status'])->toBe(503);
    Http::assertSentCount(3);
});

it('meldt een verbindingsfout als status 0', function () {
    $site = capiKlaar();
    Http::fake(['graph.facebook.com/*' => fn () => throw new \Illuminate\Http\Client\ConnectionException('cURL error 28: timed out')]);

    $result = app(ConversionsApi::class)->sendEvent($site, ['event_name' => 'Purchase']);

    expect($result['ok'])->toBeFalse()
        ->and($result['status'])->toBe(0)
        ->and($result['body']['error'])->toContain('timed out');
});

it('verstuurt niets zonder pixel-ID of token', function () {
    Http::fake();

    $result = app(ConversionsApi::class)->sendEvent(Sites::getActive(), ['event_name' => 'Purchase']);

    expect($result)->toBe(['ok' => false, 'status' => 0, 'body' => ['error' => 'Pixel-ID of toegangstoken ontbreekt']]);
    Http::assertNothingSent();
});

it('laat country en een nationaal telefoonnummer weg bij een leeg of onbekend land', function (string $country) {
    $order = capiOrder(['country' => $country, 'phone_number' => '0612345678']);

    $userData = app(ConversionsApi::class)->buildUserData($order);

    // Zonder land is de landcode niet te bepalen: liever geen ph dan een hash die nooit matcht.
    expect($userData)->not->toHaveKey('country')
        ->and($userData)->not->toHaveKey('ph')
        ->and($userData)->toHaveKey('em');
})->with(['', 'Atlantis']);

it('hasht een internationaal nummer met zijn eigen landcode bij een onbekend land', function () {
    $order = capiOrder(['country' => 'Atlantis', 'phone_number' => '+49 171 1234567']);

    expect(app(ConversionsApi::class)->buildUserData($order)['ph'])->toBe([hash('sha256', '491711234567')]);
});

it('laat ph weg als het telefoonnummer alleen letters bevat', function () {
    $order = capiOrder(['phone_number' => 'geen nummer']);

    expect(app(ConversionsApi::class)->buildUserData($order))->not->toHaveKey('ph');
});

it('heeft user_data in het Purchase-event van een gewone order', function () {
    capiKlaar();

    expect(app(ConversionsApi::class)->purchaseEvent(capiOrder()))->toHaveKey('user_data')
        ->and(app(ConversionsApi::class)->purchaseEvent(capiOrder())['user_data'])->not->toBeEmpty();
});

it('geeft bij een foutpagina met ongeldige UTF-8 een json-encodeerbaar resultaat', function () {
    $site = capiKlaar();
    Http::fake(['graph.facebook.com/*' => Http::response(str_repeat('a', 499) . 'é' . "\xC3\x28", 502)]);

    $result = app(ConversionsApi::class)->sendEvent($site, ['event_name' => 'Purchase']);

    expect(json_encode($result))->not->toBeFalse()
        ->and($result['status'])->toBe(502)
        ->and($result['body'])->toHaveKey('raw');
});

it('geeft bij een verbindingsfout met ongeldige UTF-8 een json-encodeerbaar resultaat', function () {
    $site = capiKlaar();
    Http::fake(['graph.facebook.com/*' => fn () => throw new \Illuminate\Http\Client\ConnectionException("timeout \xC3\x28 \xFF")]);

    $result = app(ConversionsApi::class)->sendEvent($site, ['event_name' => 'Purchase']);

    expect(json_encode($result))->not->toBeFalse()->and($result['status'])->toBe(0);
});
