<?php

use Illuminate\Http\Request;
use Illuminate\Session\Store;
use Illuminate\Support\Carbon;
use Illuminate\Session\ArraySessionHandler;
use Dashed\DashedEcommerceCore\Models\Order;
use Dashed\DashedEcommerceCore\DashedEcommerceCoreEventServiceProvider;
use Dashed\DashedEcommerceCore\Models\OrderTracking;
use Dashed\DashedEcommerceCore\Services\Meta\MarketingConsent;
use Dashed\DashedEcommerceCore\Events\Orders\OrderCreatedEvent;
use Dashed\DashedEcommerceCore\Http\Middleware\CaptureMetaClickId;
use Dashed\DashedEcommerceCore\Services\Meta\OrderTrackingRecorder;

function trackingOrder(array $attributes = []): Order
{
    return Order::create(array_merge([
        'status' => 'pending', 'total' => 10, 'subtotal' => 10, 'btw' => 0, 'discount' => 0,
    ], $attributes));
}

function checkoutRequest(array $server = []): Request
{
    $request = Request::create('https://shop.test/livewire/update', 'POST', [], [], [], array_merge([
        'HTTP_USER_AGENT' => 'Mozilla/5.0 (Test)',
        'HTTP_REFERER' => 'https://shop.test/checkout?stap=2',
    ], $server));
    $session = new Store('meta-test', new ArraySessionHandler(120));
    $session->start();
    $request->setLaravelSession($session);

    return $request;
}

afterEach(function () {
    MarketingConsent::resolveUsing(null);
    Carbon::setTestNow();
});

it('zet een fbclid uit de URL als fbc in de sessie', function () {
    Carbon::setTestNow('2026-10-08 12:00:00');
    $request = Request::create('https://shop.test/product?fbclid=AbC123', 'GET');
    $session = new Store('meta-mw', new ArraySessionHandler(120));
    $session->start();
    $request->setLaravelSession($session);

    $response = (new CaptureMetaClickId())->handle($request, fn () => response('ok'));

    expect($response->getContent())->toBe('ok')
        ->and($session->get(CaptureMetaClickId::SESSION_KEY))
        ->toBe('fb.1.' . Carbon::now()->getTimestampMs() . '.AbC123');
});

it('doet niets bij een POST, zonder sessie of zonder fbclid', function () {
    $post = Request::create('https://shop.test/checkout?fbclid=AbC123', 'POST');
    $session = new Store('meta-mw', new ArraySessionHandler(120));
    $session->start();
    $post->setLaravelSession($session);
    (new CaptureMetaClickId())->handle($post, fn () => response('ok'));

    $zonderSessie = Request::create('https://shop.test/?fbclid=AbC123', 'GET');
    $response = (new CaptureMetaClickId())->handle($zonderSessie, fn () => response('ok'));

    expect($session->get(CaptureMetaClickId::SESSION_KEY))->toBeNull()
        ->and($response->getContent())->toBe('ok');
});

it('legt cookies, user agent, checkout-URL en consent vast', function () {
    $order = trackingOrder();

    $tracking = app(OrderTrackingRecorder::class)->record($order, checkoutRequest(), [
        '_fbp' => 'fb.1.1700000000000.1234567890',
        '_fbc' => 'fb.1.1700000000001.AbC123',
    ]);

    expect($tracking)->toBeInstanceOf(OrderTracking::class)
        ->and($tracking->meta_fbp)->toBe('fb.1.1700000000000.1234567890')
        ->and($tracking->meta_fbc)->toBe('fb.1.1700000000001.AbC123')
        ->and($tracking->client_user_agent)->toBe('Mozilla/5.0 (Test)')
        ->and($tracking->event_source_url)->toBe('https://shop.test/checkout?stap=2')
        ->and($tracking->marketing_consent)->toBeTrue();
});

it('maakt fbc uit de sessie als de cookie ontbreekt', function () {
    $request = checkoutRequest();
    $request->session()->put(CaptureMetaClickId::SESSION_KEY, 'fb.1.1700000000002.UitSessie');

    $tracking = app(OrderTrackingRecorder::class)->record(trackingOrder(), $request, []);

    expect($tracking->meta_fbc)->toBe('fb.1.1700000000002.UitSessie');
});

it('maakt fbc uit de fbclid op de order als cookie en sessie ontbreken', function () {
    $order = trackingOrder([
        'fbclid' => 'VanDeOrder',
        'attribution_last_touch_at' => '2026-10-07 09:30:00',
    ]);

    $tracking = app(OrderTrackingRecorder::class)->record($order, checkoutRequest(), []);

    expect($tracking->meta_fbc)->toBe('fb.1.' . Carbon::parse('2026-10-07 09:30:00')->getTimestampMs() . '.VanDeOrder');
});

it('laat rommel in de cookies weg en legt de rest gewoon vast', function (array $cookies) {
    $tracking = app(OrderTrackingRecorder::class)->record(trackingOrder(), checkoutRequest(), $cookies);

    expect($tracking)->not->toBeNull()
        ->and($tracking->meta_fbp)->toBeNull()
        ->and($tracking->meta_fbc)->toBeNull()
        ->and($tracking->client_user_agent)->toBe('Mozilla/5.0 (Test)');
})->with([
    'leeg' => [['_fbp' => '', '_fbc' => '']],
    'onzin' => [['_fbp' => '<script>alert(1)</script>', '_fbc' => 'geen-fb-formaat']],
    'veel te lang' => [['_fbp' => 'fb.1.1700000000000.' . str_repeat('9', 400), '_fbc' => 'fb.1.1700000000000.' . str_repeat('a', 2000)]],
    'array in plaats van tekst' => [['_fbp' => ['x'], '_fbc' => ['y']]],
]);

it('legt consent false vast als de resolver nee zegt', function () {
    MarketingConsent::resolveUsing(fn () => false);

    $tracking = app(OrderTrackingRecorder::class)->record(trackingOrder(), checkoutRequest(), []);

    expect($tracking->marketing_consent)->toBeFalse();
});

it('kort een extreem lange user agent en referer in', function () {
    $request = checkoutRequest([
        'HTTP_USER_AGENT' => str_repeat('u', 5000),
        'HTTP_REFERER' => 'https://shop.test/checkout?' . str_repeat('q', 5000),
    ]);

    $tracking = app(OrderTrackingRecorder::class)->record(trackingOrder(), $request, []);

    expect(strlen($tracking->client_user_agent))->toBe(1000)
        ->and(strlen($tracking->event_source_url))->toBe(2048);
});

it('maakt geen tweede rij als de order al signalen heeft', function () {
    $order = trackingOrder();
    $recorder = app(OrderTrackingRecorder::class);

    $recorder->record($order, checkoutRequest(), ['_fbp' => 'fb.1.1700000000000.111']);
    $recorder->record($order, checkoutRequest(), ['_fbp' => 'fb.1.1700000000000.222']);

    expect(OrderTracking::where('order_id', $order->id)->count())->toBe(1)
        ->and($order->fresh()->tracking->meta_fbp)->toBe('fb.1.1700000000000.111');
});

it('legt signalen vast zodra de checkout OrderCreatedEvent afvuurt', function () {
    // De package-testharness laadt de event-provider niet via package-discovery.
    app()->register(DashedEcommerceCoreEventServiceProvider::class);
    $order = trackingOrder();

    OrderCreatedEvent::dispatch($order);

    expect($order->fresh()->tracking)->not->toBeNull();
});

it('legt ook vast bij een user agent met ongeldige UTF-8 en bewaart de cookie', function () {
    $request = checkoutRequest(['HTTP_USER_AGENT' => "Bot\xC3\x28 crawler"]);

    $tracking = app(OrderTrackingRecorder::class)->record(trackingOrder(), $request, ['_fbp' => 'fb.1.1700000000000.555']);

    expect($tracking)->not->toBeNull()
        ->and($tracking->client_user_agent)->not->toBeNull()
        ->and(mb_check_encoding($tracking->client_user_agent, 'UTF-8'))->toBeTrue()
        ->and($tracking->meta_fbp)->toBe('fb.1.1700000000000.555');
});

it('knipt een user agent nooit midden in een multibyte-teken af', function () {
    $request = checkoutRequest(['HTTP_USER_AGENT' => str_repeat('a', 999) . 'ééé']);

    $tracking = app(OrderTrackingRecorder::class)->record(trackingOrder(), $request, []);

    expect($tracking)->not->toBeNull()
        ->and(mb_check_encoding($tracking->client_user_agent, 'UTF-8'))->toBeTrue()
        ->and(strlen($tracking->client_user_agent))->toBeLessThanOrEqual(1000);
});

it('slaat zonder referer nooit het livewire-endpoint op', function () {
    $request = checkoutRequest();
    $request->headers->remove('referer');

    $tracking = app(OrderTrackingRecorder::class)->record(trackingOrder(), $request, []);

    expect($tracking)->not->toBeNull();
    if ($tracking->event_source_url !== null) {
        expect($tracking->event_source_url)->not->toContain('livewire');
    }
});
