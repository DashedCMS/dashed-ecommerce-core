<?php

use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Http;
use Dashed\DashedCore\Models\Customsetting;
use Dashed\DashedEcommerceCore\Models\Cart;
use Dashed\DashedEcommerceCore\Jobs\SendMetaEventJob;
use Dashed\DashedEcommerceCore\Services\Meta\MetaHasher;
use Dashed\DashedEcommerceCore\Services\Meta\MarketingConsent;
use Dashed\DashedEcommerceCore\Services\Meta\MetaBrowserEvents;

require_once __DIR__ . '/../../Support/MetaBrowserFixtures.php';

function checkoutItems(): array
{
    return [
        ['item_id' => 501, 'item_name' => 'Bluma', 'price' => '13.54', 'quantity' => 2],
        ['item_id' => 502, 'item_name' => 'Flowy', 'price' => '20.00', 'quantity' => 1],
        ['geen' => 'product'],
    ];
}

function checkoutCart(?string $email = null): Cart
{
    return Cart::create(['token' => (string) Str::uuid(), 'type' => 'default', 'abandoned_email' => $email]);
}

beforeEach(function () {
    Http::preventStrayRequests();
    Bus::fake([SendMetaEventJob::class]);
    $this->site = metaCapiKlaar();
    metaBezoekerVerzoek();
    $_COOKIE['_fbp'] = 'fb.1.1700000000000.123';
});

afterEach(function () {
    unset($_COOKIE['_fbp'], $_COOKIE['_fbc']);
    MarketingConsent::resolveUsing(null);
    Carbon::setTestNow();
});

it('geeft bij AddToCart een event-ID terug en zet hetzelfde event klaar voor de server', function () {
    Carbon::setTestNow('2026-11-09 12:00:00');
    $product = metaProduct(13.54);

    $result = app(MetaBrowserEvents::class)->addToCart($product, 2, 13.54);

    expect($result['metaEventId'])->toMatch('/^atc_[0-9a-f\-]{36}$/')
        ->and($result['metaValue'])->toBe('27.08');

    Bus::assertDispatched(SendMetaEventJob::class, function (SendMetaEventJob $job) use ($result, $product) {
        expect($job->siteId)->toBe($this->site)
            ->and($job->queue)->toBe('ecommerce')
            ->and($job->event['event_name'])->toBe('AddToCart')
            ->and($job->event['event_id'])->toBe($result['metaEventId'])
            ->and($job->event['event_time'])->toBe(Carbon::now()->timestamp)
            ->and($job->event['action_source'])->toBe('website')
            // Zonder querystring: daar stond een e-mailadres in.
            ->and($job->event['event_source_url'])->toBe('https://shop.test/vazen/bluma')
            ->and($job->event['custom_data'])->toBe([
                'currency' => 'EUR',
                'value' => 27.08,
                'content_type' => 'product',
                'content_ids' => [(string) $product->id],
                'contents' => [['id' => (string) $product->id, 'quantity' => 2, 'item_price' => 13.54]],
                'num_items' => 2,
                'content_name' => 'Bluma vaas',
            ])
            ->and($job->event['user_data'])->toBe([
                'client_ip_address' => '203.0.113.7',
                'client_user_agent' => request()->userAgent(),
                'fbp' => 'fb.1.1700000000000.123',
            ]);

        return true;
    });
});

it('rekent de waarde exclusief btw als de value mode dat vraagt, de stukprijs blijft die van de pixel', function () {
    Customsetting::set('meta_capi_value_mode', 'excl_vat', $this->site);
    Customsetting::set('taxes_prices_include_taxes', true, $this->site);

    $result = app(MetaBrowserEvents::class)->addToCart(metaProduct(12.10), 2, 12.10);

    expect($result['metaValue'])->toBe('20.00');
    Bus::assertDispatched(SendMetaEventJob::class, fn (SendMetaEventJob $job) => $job->event['custom_data']['value'] === 20.0
        && $job->event['custom_data']['contents'][0]['item_price'] === 12.10);
});

it('stuurt niets als de functie, het event of de toestemming uit staat', function (Closure $zetUit) {
    $zetUit($this->site);

    expect(app(MetaBrowserEvents::class)->addToCart(metaProduct(), 1, 12.95))->toBe([]);
    Bus::assertNotDispatched(SendMetaEventJob::class);
})->with([
    'hoofdschakelaar uit' => [fn ($site) => Customsetting::set('meta_capi_enabled', false, $site)],
    'AddToCart uit' => [fn ($site) => Customsetting::set('meta_capi_event_add_to_cart', false, $site)],
    'geen pixel-ID' => [fn ($site) => Customsetting::set('facebook_pixel_conversion_id', null, $site)],
    'geen toestemming' => [fn ($site) => MarketingConsent::resolveUsing(fn () => false)],
    'bot' => [fn ($site) => metaBezoekerVerzoek(['HTTP_USER_AGENT' => 'Mozilla/5.0 (compatible; bingbot/2.0)'])],
    'geen user agent' => [fn ($site) => metaBezoekerVerzoek(['HTTP_USER_AGENT' => ''])],
]);

it('remt een stortvloed van hetzelfde IP-adres af', function () {
    $product = metaProduct();
    $events = app(MetaBrowserEvents::class);

    for ($i = 0; $i < MetaBrowserEvents::RATE_LIMIT_PER_MINUTE; $i++) {
        expect($events->addToCart($product, 1, 12.95))->toHaveKey('metaEventId');
    }

    expect($events->addToCart($product, 1, 12.95))->toBe([]);
    Bus::assertDispatchedTimes(SendMetaEventJob::class, MetaBrowserEvents::RATE_LIMIT_PER_MINUTE);
});

it('stuurt bij InitiateCheckout de wageninhoud en het totaal, met het e-mailadres dat al in de wagen staat', function () {
    $cart = checkoutCart('Klant@Voorbeeld.nl ');

    $result = app(MetaBrowserEvents::class)->initiateCheckout(checkoutItems(), 47.08, $cart, 8.17, 0);

    expect($result['metaEventId'])->toMatch('/^ic_[0-9a-f\-]{36}$/')
        ->and($result['metaValue'])->toBe('47.08');

    Bus::assertDispatched(SendMetaEventJob::class, function (SendMetaEventJob $job) use ($result) {
        expect($job->event['event_name'])->toBe('InitiateCheckout')
            ->and($job->event['event_id'])->toBe($result['metaEventId'])
            ->and($job->event['custom_data'])->toBe([
                'currency' => 'EUR',
                'value' => 47.08,
                'content_type' => 'product',
                'content_ids' => ['501', '502'],
                'contents' => [
                    ['id' => '501', 'quantity' => 2, 'item_price' => 13.54],
                    ['id' => '502', 'quantity' => 1, 'item_price' => 20.0],
                ],
                'num_items' => 3,
            ])
            ->and($job->event['user_data']['em'])->toBe([MetaHasher::email('klant@voorbeeld.nl')])
            ->and($job->event['user_data']['external_id'])->toBe([MetaHasher::email('klant@voorbeeld.nl')])
            ->and(json_encode($job->event))->not->toContain('oorbeeld.nl');

        return true;
    });
});

it('stuurt InitiateCheckout één keer per wagen: herladen geeft hetzelfde event-ID zonder tweede server-event', function () {
    $cart = checkoutCart();
    $events = app(MetaBrowserEvents::class);

    $first = $events->initiateCheckout(checkoutItems(), 47.08, $cart);
    $second = $events->initiateCheckout(checkoutItems(), 47.08, $cart);

    expect($second)->toBe($first);
    Bus::assertDispatchedTimes(SendMetaEventJob::class, 1);

    // Een andere inhoud is een nieuwe checkout.
    $changed = $events->initiateCheckout([checkoutItems()[0]], 27.08, $cart);
    expect($changed['metaEventId'])->not->toBe($first['metaEventId']);
    Bus::assertDispatchedTimes(SendMetaEventJob::class, 2);

    // Na een half uur telt het weer als nieuw bezoek.
    Carbon::setTestNow(Carbon::now()->addMinutes(MetaBrowserEvents::CHECKOUT_DEDUPE_MINUTES + 1));
    expect($events->initiateCheckout(checkoutItems(), 47.08, $cart)['metaEventId'])->not->toBe($first['metaEventId']);
});

it('stuurt geen InitiateCheckout voor een lege wagen of als het event uit staat', function () {
    expect(app(MetaBrowserEvents::class)->initiateCheckout([], 0, checkoutCart()))->toBe([]);

    Customsetting::set('meta_capi_event_initiate_checkout', false, $this->site);
    expect(app(MetaBrowserEvents::class)->initiateCheckout(checkoutItems(), 47.08, checkoutCart()))->toBe([]);

    Bus::assertNotDispatched(SendMetaEventJob::class);
});

it('rekent het checkouttotaal volgens de value mode', function () {
    Customsetting::set('meta_capi_value_mode', 'excl_vat_excl_shipping', $this->site);

    // 121 totaal, 21 btw, 12,10 verzendkosten (incl.): 100 ex btw min 10 verzending ex btw.
    $result = app(MetaBrowserEvents::class)->initiateCheckout(checkoutItems(), 121.00, checkoutCart(), 21.00, 12.10);

    expect($result['metaValue'])->toBe('90.00');
});

it('geeft de productpagina alleen een waarde mee en nooit een event-ID', function () {
    $payload = app(MetaBrowserEvents::class)->viewContentPayload(metaProduct(12.95), 12.95);

    expect($payload)->toBe(['metaValue' => '12.95']);
    Bus::assertNotDispatched(SendMetaEventJob::class);

    Customsetting::set('meta_capi_event_view_content', false, $this->site);
    expect(app(MetaBrowserEvents::class)->viewContentPayload(metaProduct(12.95), 12.95))->toBe([]);
});

it('breekt de wagen nooit: een fout levert een lege uitkomst op', function () {
    Bus::swap(new class () {
        public function __call($method, $arguments)
        {
            throw new RuntimeException('wachtrij onbereikbaar');
        }
    });

    expect(app(MetaBrowserEvents::class)->addToCart(metaProduct(), 1, 12.95))->toBe([])
        ->and(app(MetaBrowserEvents::class)->initiateCheckout(checkoutItems(), 47.08, checkoutCart()))->toBe([]);
});
