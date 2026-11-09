<?php

use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Route;
use Dashed\DashedCore\Models\Customsetting;
use Dashed\DashedEcommerceCore\Jobs\SendMetaEventJob;
use Dashed\DashedEcommerceCore\Services\Meta\MetaBrowserEvents;

require_once __DIR__ . '/../../Support/MetaBrowserFixtures.php';

const VC_ID = 'vc_0b0e6a1e-6f1c-4c1c-9d58-1f6f5a2f9c11';
const VC_BROWSER = 'Mozilla/5.0 (iPhone; CPU iPhone OS 17_0 like Mac OS X) AppleWebKit/605.1.15 Version/17.0 Mobile/15E148 Safari/604.1';

function meldViewContent($test, array $data, array $headers = [])
{
    return $test->withHeaders(array_merge([
        'User-Agent' => VC_BROWSER,
        'Referer' => 'https://shop.test/producten/bluma-vaas?fbclid=AbC123&naam=Max',
    ], $headers))->post(route('dashed.frontend.meta.view-content'), $data);
}

beforeEach(function () {
    Http::preventStrayRequests();
    Bus::fake([SendMetaEventJob::class]);
    $this->site = metaCapiKlaar();
    RateLimiter::clear(MetaBrowserEvents::rateKey('127.0.0.1'));
    $_COOKIE['_fbp'] = 'fb.1.1700000000000.123';
});

afterEach(function () {
    unset($_COOKIE['_fbp'], $_COOKIE['_fbc']);
    Carbon::setTestNow();
});

it('zet een ViewContent klaar met het event-ID van de browser en de prijs van de server', function () {
    $product = metaProduct(24.95);

    // De browser kan elke prijs meesturen; die wordt genegeerd.
    meldViewContent($this, ['product_id' => $product->id, 'event_id' => VC_ID, 'price' => '0.01'])->assertNoContent();

    Bus::assertDispatched(SendMetaEventJob::class, function (SendMetaEventJob $job) use ($product) {
        expect($job->queue)->toBe('ecommerce')
            ->and($job->event['event_name'])->toBe('ViewContent')
            ->and($job->event['event_id'])->toBe(VC_ID)
            ->and($job->event['event_source_url'])->toBe('https://shop.test/producten/bluma-vaas')
            ->and($job->event['custom_data'])->toBe([
                'currency' => 'EUR',
                'value' => 24.95,
                'content_type' => 'product',
                'content_ids' => [(string) $product->id],
                'contents' => [['id' => (string) $product->id, 'quantity' => 1, 'item_price' => 24.95]],
                'content_name' => 'Bluma vaas',
            ])
            ->and($job->event['user_data']['client_user_agent'])->toBe(VC_BROWSER)
            ->and($job->event['user_data']['fbp'])->toBe('fb.1.1700000000000.123')
            // Geen _fbc-cookie: de fbclid uit de pagina-URL.
            ->and($job->event['user_data']['fbc'])->toMatch('/^fb\.1\.\d+\.AbC123$/');

        return true;
    });
});

it('ontdubbelt per bezoeker per product per 30 minuten', function () {
    $product = metaProduct();
    $other = metaProduct();

    meldViewContent($this, ['product_id' => $product->id, 'event_id' => VC_ID]);
    meldViewContent($this, ['product_id' => $product->id, 'event_id' => 'vc_11111111-1111-1111-1111-111111111111']);
    Bus::assertDispatchedTimes(SendMetaEventJob::class, 1);

    // Een ander product van dezelfde bezoeker telt wel.
    meldViewContent($this, ['product_id' => $other->id, 'event_id' => 'vc_22222222-2222-2222-2222-222222222222']);
    Bus::assertDispatchedTimes(SendMetaEventJob::class, 2);

    // Een andere bezoeker (andere _fbp) ook.
    $_COOKIE['_fbp'] = 'fb.1.1700000000000.999';
    meldViewContent($this, ['product_id' => $product->id, 'event_id' => 'vc_33333333-3333-3333-3333-333333333333']);
    Bus::assertDispatchedTimes(SendMetaEventJob::class, 3);

    // En dezelfde bezoeker na een half uur weer.
    Carbon::setTestNow(Carbon::now()->addMinutes(31));
    meldViewContent($this, ['product_id' => $product->id, 'event_id' => 'vc_44444444-4444-4444-4444-444444444444']);
    Bus::assertDispatchedTimes(SendMetaEventJob::class, 4);
});

it('slaat bots, onbekende producten en ongeldige event-IDs over, altijd met een leeg antwoord', function (Closure $verzoek) {
    $verzoek($this, metaProduct())->assertNoContent();

    Bus::assertNotDispatched(SendMetaEventJob::class);
})->with([
    'bot' => [fn ($test, $product) => meldViewContent($test, ['product_id' => $product->id, 'event_id' => VC_ID], ['User-Agent' => 'Mozilla/5.0 (compatible; Googlebot/2.1)'])],
    'onbekend product' => [fn ($test, $product) => meldViewContent($test, ['product_id' => 999999, 'event_id' => VC_ID])],
    'niet-openbaar product' => [fn ($test, $product) => meldViewContent($test, ['product_id' => metaProduct(10, ['public' => false])->id, 'event_id' => VC_ID])],
    'event-ID van een ander soort' => [fn ($test, $product) => meldViewContent($test, ['product_id' => $product->id, 'event_id' => 'purchase_123'])],
    'event-ID met rommel' => [fn ($test, $product) => meldViewContent($test, ['product_id' => $product->id, 'event_id' => 'vc_<script>alert(1)</script>'])],
    'geen gegevens' => [fn ($test, $product) => meldViewContent($test, [])],
    'array als invoer' => [fn ($test, $product) => meldViewContent($test, ['product_id' => [$product->id], 'event_id' => [VC_ID]])],
]);

it('doet niets als ViewContent uit staat', function () {
    Customsetting::set('meta_capi_event_view_content', false, $this->site);

    meldViewContent($this, ['product_id' => metaProduct()->id, 'event_id' => VC_ID])->assertNoContent();

    Bus::assertNotDispatched(SendMetaEventJob::class);
});

it('heeft een route zonder CSRF-controle, met sessie en een limiet per IP-adres', function () {
    $route = Route::getRoutes()->getByName('dashed.frontend.meta.view-content');

    expect($route->methods())->toBe(['POST'])
        ->and($route->uri())->toBe('ecommerce/meta/view-content')
        ->and($route->gatherMiddleware())->toContain('web')
        ->and($route->gatherMiddleware())->toContain('throttle:120,1')
        ->and($route->excludedMiddleware())->toContain(\Illuminate\Foundation\Http\Middleware\ValidateCsrfToken::class);
});
