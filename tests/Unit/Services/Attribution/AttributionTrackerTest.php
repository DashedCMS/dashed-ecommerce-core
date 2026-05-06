<?php

declare(strict_types=1);

use Illuminate\Http\Request;
use Illuminate\Session\ArraySessionHandler;
use Illuminate\Session\Store;
use Dashed\DashedEcommerceCore\Services\Attribution\AttributionTracker;

beforeEach(function () {
    $store = new Store('attribution-test', new ArraySessionHandler(120));
    $store->start();
    $this->session = $store;
});

function attributionRequest(array $query, ?\Illuminate\Session\Store $session = null): Request
{
    $request = Request::create('https://example.test/landing', 'GET', $query);
    if ($session) {
        $request->setLaravelSession($session);
    }

    return $request;
}

it('captures UTM parameters from a request and persists them in the session', function () {
    $request = attributionRequest([
        'utm_source' => 'google',
        'utm_medium' => 'cpc',
        'utm_campaign' => 'spring',
        'gclid' => 'abc123',
    ], $this->session);

    $touch = AttributionTracker::captureFromRequest($request);

    expect($touch)->not->toBeNull();
    expect($touch['utm_source'])->toBe('google');
    expect($touch['utm_medium'])->toBe('cpc');
    expect($touch['gclid'])->toBe('abc123');

    $stored = $this->session->get(AttributionTracker::SESSION_KEY);
    expect($stored)->toBeArray();
    expect($stored['first_touch']['utm_source'])->toBe('google');
    expect($stored['last_touch']['utm_source'])->toBe('google');
});

it('keeps the first-touch but updates the last-touch on subsequent UTM hits', function () {
    AttributionTracker::captureFromRequest(attributionRequest([
        'utm_source' => 'google',
        'utm_campaign' => 'spring',
    ], $this->session));

    AttributionTracker::captureFromRequest(attributionRequest([
        'utm_source' => 'facebook',
        'utm_campaign' => 'retargeting',
    ], $this->session));

    $stored = $this->session->get(AttributionTracker::SESSION_KEY);

    expect($stored['first_touch']['utm_source'])->toBe('google');
    expect($stored['first_touch']['utm_campaign'])->toBe('spring');
    expect($stored['last_touch']['utm_source'])->toBe('facebook');
    expect($stored['last_touch']['utm_campaign'])->toBe('retargeting');
});

it('captures a landing-page touch on the very first request even without UTM', function () {
    $request = attributionRequest([], $this->session);

    AttributionTracker::captureFromRequest($request);

    $stored = $this->session->get(AttributionTracker::SESSION_KEY);
    expect($stored)->toBeArray();
    expect($stored['first_touch']['landing_page'])->toContain('example.test/landing');
    expect($this->session->get(AttributionTracker::SESSION_FIRST_FLAG))->toBeTrue();
});

it('does nothing when there are no UTM params and the first-touch is already captured', function () {
    AttributionTracker::captureFromRequest(attributionRequest([], $this->session));

    $touch = AttributionTracker::captureFromRequest(attributionRequest([], $this->session));

    expect($touch)->toBeNull();
});

it('exposes a stable list of tracked params', function () {
    expect(AttributionTracker::TRACKED_PARAMS)->toContain('utm_source');
    expect(AttributionTracker::TRACKED_PARAMS)->toContain('utm_medium');
    expect(AttributionTracker::TRACKED_PARAMS)->toContain('utm_campaign');
    expect(AttributionTracker::TRACKED_PARAMS)->toContain('utm_term');
    expect(AttributionTracker::TRACKED_PARAMS)->toContain('utm_content');
    expect(AttributionTracker::TRACKED_PARAMS)->toContain('gclid');
    expect(AttributionTracker::TRACKED_PARAMS)->toContain('fbclid');
    expect(AttributionTracker::TRACKED_PARAMS)->toContain('msclkid');
});
