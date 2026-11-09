<?php

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Cache;
use Dashed\DashedCore\Models\Customsetting;
use Dashed\DashedEcommerceCore\Models\MetaCapiEvent;
use Dashed\DashedEcommerceCore\Jobs\SendMetaEventJob;
use Dashed\DashedEcommerceCore\Models\MetaCapiEventCount;

require_once __DIR__ . '/../../Support/MetaBrowserFixtures.php';

function browserEvent(string $name = 'AddToCart', string $id = 'atc_11111111-1111-1111-1111-111111111111'): array
{
    return [
        'event_name' => $name,
        'event_time' => 1794218400,
        'event_id' => $id,
        'action_source' => 'website',
        'user_data' => ['client_ip_address' => '203.0.113.7', 'client_user_agent' => 'Mozilla/5.0 (Test)', 'fbp' => 'fb.1.1.2'],
        'custom_data' => ['currency' => 'EUR', 'value' => 10.0],
    ];
}

beforeEach(function () {
    Http::preventStrayRequests();
    $this->site = metaCapiKlaar();
});

it('verstuurt het event, telt het en schrijft bij succes geen logregel', function () {
    Http::fake(['graph.facebook.com/*' => Http::response(['events_received' => 1], 200)]);

    SendMetaEventJob::dispatchSync($this->site, browserEvent('ViewContent', 'vc_11111111-1111-1111-1111-111111111111'));
    SendMetaEventJob::dispatchSync($this->site, browserEvent('ViewContent', 'vc_22222222-2222-2222-2222-222222222222'));

    Http::assertSentCount(2);
    Http::assertSent(fn ($request) => $request['data'][0]['event_id'] === 'vc_11111111-1111-1111-1111-111111111111'
        && $request['data'][0]['user_data']['client_ip_address'] === '203.0.113.7'
        && $request['access_token'] === 'EAAB-geheim');

    expect(MetaCapiEvent::count())->toBe(0);

    $count = MetaCapiEventCount::sole();
    expect($count->event_name)->toBe('ViewContent')
        ->and($count->sent)->toBe(2)
        ->and($count->failed)->toBe(0);
});

it('legt een afgekeurd event vast als mislukt, één keer per uur per event en status, zonder IP-adres of user agent', function () {
    Http::fake(['graph.facebook.com/*' => Http::response(['error' => ['message' => 'Invalid parameter', 'code' => 100]], 400)]);

    SendMetaEventJob::dispatchSync($this->site, browserEvent());
    SendMetaEventJob::dispatchSync($this->site, browserEvent('AddToCart', 'atc_22222222-2222-2222-2222-222222222222'));
    SendMetaEventJob::dispatchSync($this->site, browserEvent('AddToCart', 'atc_33333333-3333-3333-3333-333333333333'));

    $row = MetaCapiEvent::sole();
    expect($row->status)->toBe(MetaCapiEvent::STATUS_FAILED)
        ->and($row->event_name)->toBe('AddToCart')
        ->and($row->order_id)->toBeNull()
        ->and($row->response['status'])->toBe(400)
        ->and($row->canResend())->toBeFalse()
        ->and($row->payload['user_data'])->toBe(['fbp' => 'fb.1.1.2'])
        ->and(json_encode($row->getAttributes()))->not->toContain('EAAB-geheim')
        // Een afgekeurd event zegt niets over de bereikbaarheid van Meta: geen rem.
        ->and(Cache::has(SendMetaEventJob::downKey($this->site)))->toBeFalse();

    Http::assertSentCount(3);
    expect(MetaCapiEventCount::sole()->failed)->toBe(3);
});

it('slaat na een storing bij Meta twee minuten alles over en gooit geen fout', function () {
    Http::fake(['graph.facebook.com/*' => Http::response('Service Unavailable', 503)]);

    SendMetaEventJob::dispatchSync($this->site, browserEvent());

    expect(Cache::has(SendMetaEventJob::downKey($this->site)))->toBeTrue();
    $callsDuringOutage = count(Http::recorded());

    SendMetaEventJob::dispatchSync($this->site, browserEvent('ViewContent', 'vc_22222222-2222-2222-2222-222222222222'));

    expect(count(Http::recorded()))->toBe($callsDuringOutage)
        ->and(MetaCapiEvent::count())->toBe(1)
        ->and(MetaCapiEventCount::where('event_name', 'ViewContent')->sole()->failed)->toBe(1);
});

it('verstuurt niets als de functie of het event na het klaarzetten is uitgezet', function () {
    Http::fake();

    Customsetting::set('meta_capi_event_add_to_cart', false, $this->site);
    SendMetaEventJob::dispatchSync($this->site, browserEvent());

    Customsetting::set('meta_capi_event_add_to_cart', true, $this->site);
    Customsetting::set('meta_capi_enabled', false, $this->site);
    SendMetaEventJob::dispatchSync($this->site, browserEvent());

    Http::assertNothingSent();
    expect(MetaCapiEvent::count())->toBe(0)
        ->and(MetaCapiEventCount::count())->toBe(0);
});

it('stuurt de test event code mee', function () {
    Customsetting::set('meta_capi_test_event_code', 'TEST123', $this->site);
    Http::fake(['graph.facebook.com/*' => Http::response(['events_received' => 1], 200)]);

    SendMetaEventJob::dispatchSync($this->site, browserEvent());

    Http::assertSent(fn ($request) => $request['test_event_code'] === 'TEST123');
});

it('doet één poging en houdt de wachtrij niet op', function () {
    $job = new SendMetaEventJob($this->site, browserEvent());

    expect($job->tries)->toBe(1);
});
