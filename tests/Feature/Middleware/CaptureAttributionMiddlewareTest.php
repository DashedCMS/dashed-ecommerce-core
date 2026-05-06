<?php

declare(strict_types=1);

use Illuminate\Http\Request;
use Illuminate\Session\ArraySessionHandler;
use Illuminate\Session\Store;
use Dashed\DashedEcommerceCore\Http\Middleware\CaptureAttributionMiddleware;
use Dashed\DashedEcommerceCore\Services\Attribution\AttributionTracker;

beforeEach(function () {
    $store = new Store('attribution-mw', new ArraySessionHandler(120));
    $store->start();
    $this->session = $store;
});

it('writes UTM data into the session via the middleware', function () {
    $middleware = new CaptureAttributionMiddleware();

    $request = Request::create('https://example.test/page', 'GET', [
        'utm_source' => 'newsletter',
        'utm_medium' => 'email',
    ]);
    $request->setLaravelSession($this->session);

    $response = $middleware->handle($request, fn () => response('ok'));

    expect($response->getContent())->toBe('ok');

    $stored = $this->session->get(AttributionTracker::SESSION_KEY);
    expect($stored)->toBeArray();
    expect($stored['last_touch']['utm_source'])->toBe('newsletter');
    expect($stored['last_touch']['utm_medium'])->toBe('email');
});

it('skips capturing for non-GET requests', function () {
    $middleware = new CaptureAttributionMiddleware();

    $request = Request::create('https://example.test/checkout', 'POST', [
        'utm_source' => 'google',
    ]);
    $request->setLaravelSession($this->session);

    $middleware->handle($request, fn () => response('ok'));

    expect($this->session->get(AttributionTracker::SESSION_KEY))->toBeNull();
});
