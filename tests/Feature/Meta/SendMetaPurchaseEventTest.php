<?php

use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Http;
use Dashed\DashedCore\Classes\Sites;
use Dashed\DashedCore\Models\Customsetting;
use Dashed\DashedEcommerceCore\Models\Order;
use Dashed\DashedEcommerceCore\Models\OrderPayment;
use Dashed\DashedEcommerceCore\Models\OrderProduct;
use Dashed\DashedEcommerceCore\Models\MetaCapiEvent;
use Dashed\DashedEcommerceCore\Models\OrderTracking;
use Dashed\DashedEcommerceCore\Jobs\SendMetaPurchaseEventJob;
use Dashed\DashedEcommerceCore\Services\Meta\MetaCapiSettings;

/** Order uit de webshop-checkout: met klantsignalen en consent. */
function betaalbareOrder(array $attributes = [], bool $metTracking = true, bool $consent = true): Order
{
    $order = Order::create(array_merge([
        'status' => 'pending', 'email' => 'jan@example.com', 'country' => 'Nederland',
        'total' => 35.03, 'subtotal' => 35.03, 'btw' => 6.08, 'discount' => 0,
    ], $attributes));
    // Product 501 bestaat niet in de testdatabase; alleen de FK-controle uit.
    Schema::disableForeignKeyConstraints();
    OrderProduct::create(['order_id' => $order->id, 'product_id' => 501, 'name' => 'Vaas', 'quantity' => 1, 'price' => 35.03, 'btw' => 6.08]);
    Schema::enableForeignKeyConstraints();

    if ($metTracking) {
        OrderTracking::create(['order_id' => $order->id, 'marketing_consent' => $consent]);
    }

    return $order->fresh();
}

/** Zet de status zoals Order::markAsPaid() dat doet, zonder de rest van die methode. */
function zetStatus(Order $order, string $status): void
{
    $order->status = $status;
    $order->save();
}

/** Betaalde betaling bij de order, een aantal dagen geleden. */
function betaaldDagenGeleden(Order $order, int $dagen, string $status = 'paid'): OrderPayment
{
    $payment = OrderPayment::create(['order_id' => $order->id, 'amount' => 35.03, 'status' => $status, 'psp' => 'paynl', 'payment_method' => 'iDEAL']);
    $payment->forceFill(['created_at' => Carbon::now()->subDays($dagen)])->saveQuietly();

    return $payment;
}

/** Eventrij van de order zoals de job hem zelf aanmaakt, met een eigen status. */
function eventRij(Order $order, string $status, ?array $response = null): MetaCapiEvent
{
    return MetaCapiEvent::create(['site_id' => $order->site_id, 'order_id' => $order->id, 'event_name' => 'Purchase', 'event_id' => 'purchase_' . $order->id, 'status' => $status, 'response' => $response]);
}

afterEach(fn () => Carbon::setTestNow());

beforeEach(function () {
    Http::preventStrayRequests();
    $this->site = Sites::getActive();
    Customsetting::set('meta_capi_enabled', true, $this->site);
    Customsetting::set('facebook_pixel_conversion_id', '599475696120398', $this->site);
    MetaCapiSettings::for($this->site)->storeAccessToken('EAAB-geheim');
});

it('verstuurt precies één Purchase zodra een checkout-order op betaald komt', function () {
    Http::fake(['graph.facebook.com/*' => Http::response(['events_received' => 1], 200)]);
    $order = betaalbareOrder();

    zetStatus($order, 'paid');

    Http::assertSentCount(1);
    Http::assertSent(fn ($request) => $request['data'][0]['event_id'] === 'purchase_' . $order->id);

    $event = MetaCapiEvent::where('event_id', 'purchase_' . $order->id)->sole();
    expect($event->status)->toBe(MetaCapiEvent::STATUS_SENT)
        ->and($event->attempts)->toBe(1)
        ->and($event->sent_at)->not->toBeNull()
        ->and($event->order_id)->toBe($order->id)
        ->and($event->site_id)->toBe($this->site)
        ->and($event->response)->toEqual(['status' => 200, 'body' => ['events_received' => 1]]);
});

it('bewaart het token nergens in de eventrij', function () {
    Http::fake(['graph.facebook.com/*' => Http::response(['events_received' => 1], 200)]);
    $order = betaalbareOrder();

    zetStatus($order, 'paid');

    $raw = json_encode(MetaCapiEvent::where('order_id', $order->id)->sole()->getAttributes());
    expect($raw)->not->toContain('EAAB-geheim')->not->toContain('access_token');
});

it('verstuurt niet opnieuw als de order van betaald af en weer terug gaat', function () {
    Http::fake(['graph.facebook.com/*' => Http::response(['events_received' => 1], 200)]);
    $order = betaalbareOrder();

    zetStatus($order, 'paid');
    zetStatus($order, 'partially_paid');
    zetStatus($order, 'paid');

    Http::assertSentCount(1);
    expect(MetaCapiEvent::where('order_id', $order->id)->count())->toBe(1);
});

it('verstuurt niet opnieuw als de job twee keer draait, zoals bij een dubbele webhook', function () {
    Http::fake(['graph.facebook.com/*' => Http::response(['events_received' => 1], 200)]);
    $order = betaalbareOrder(['status' => 'paid']);

    SendMetaPurchaseEventJob::dispatchSync($order->id);
    SendMetaPurchaseEventJob::dispatchSync($order->id);

    Http::assertSentCount(1);
});

it('verstuurt ook bij de late bevestiging van een bankoverschrijving', function () {
    Http::fake(['graph.facebook.com/*' => Http::response(['events_received' => 1], 200)]);
    $order = betaalbareOrder(['status' => 'waiting_for_confirmation']);

    zetStatus($order, 'paid');

    Http::assertSentCount(1);
});

it('zet geen job klaar als de Conversions API uit staat', function () {
    Customsetting::set('meta_capi_enabled', false, $this->site);
    Bus::fake([SendMetaPurchaseEventJob::class]);

    zetStatus(betaalbareOrder(), 'paid');

    Bus::assertNotDispatched(SendMetaPurchaseEventJob::class);
});

it('zet geen job klaar bij een andere statuswijziging dan naar betaald', function () {
    Bus::fake([SendMetaPurchaseEventJob::class]);
    $order = betaalbareOrder();

    zetStatus($order, 'cancelled');
    $order->email = 'ander@example.com';
    $order->save();

    Bus::assertNotDispatched(SendMetaPurchaseEventJob::class);
});

it('zet de job op de ecommerce-wachtrij', function () {
    Bus::fake([SendMetaPurchaseEventJob::class]);
    $order = betaalbareOrder();

    zetStatus($order, 'paid');

    Bus::assertDispatched(SendMetaPurchaseEventJob::class, fn ($job) => $job->orderId === $order->id && $job->queue === 'ecommerce');
});

it('slaat over met een reden en zonder HTTP-verzoek', function (Closure $maakOrder, string $reden) {
    Http::fake();
    $order = $maakOrder();

    SendMetaPurchaseEventJob::dispatchSync($order->id);

    Http::assertNothingSent();
    $event = MetaCapiEvent::where('order_id', $order->id)->sole();
    expect($event->status)->toBe(MetaCapiEvent::STATUS_SKIPPED)
        ->and($event->response)->toBe(['reason' => $reden]);
})->with([
    'geen checkout-order (Bol, kassa, handmatig)' => [fn () => betaalbareOrder(['status' => 'paid', 'order_origin' => 'Bol'], metTracking: false), 'geen klantsignalen: order komt niet uit de webshop-checkout'],
    'geen toestemming' => [fn () => betaalbareOrder(['status' => 'paid'], consent: false), 'geen marketingtoestemming'],
    'creditorder' => [fn () => betaalbareOrder(['status' => 'paid', 'credit_for_order_id' => betaalbareOrder()->id]), 'creditorder'],
    'bedrag nul' => [fn () => betaalbareOrder(['status' => 'paid', 'total' => 0]), 'orderbedrag is niet positief'],
    'niet betaald' => [fn () => betaalbareOrder(['status' => 'cancelled']), 'order is niet betaald'],
]);

it('slaat een order over die een eerdere order vervangt', function () {
    Http::fake();
    $nieuw = betaalbareOrder(['status' => 'paid']);
    betaalbareOrder(['status' => 'paid', 'replaced_by_order_id' => $nieuw->id]);

    SendMetaPurchaseEventJob::dispatchSync($nieuw->id);

    Http::assertNothingSent();
    expect(MetaCapiEvent::where('order_id', $nieuw->id)->sole()->response)->toBe(['reason' => 'vervangt een eerdere order']);
});

it('slaat over als pixel-ID of token ontbreekt', function () {
    Http::fake();
    Customsetting::set('facebook_pixel_conversion_id', null, $this->site);
    $order = betaalbareOrder(['status' => 'paid']);

    SendMetaPurchaseEventJob::dispatchSync($order->id);

    Http::assertNothingSent();
    expect(MetaCapiEvent::where('order_id', $order->id)->sole()->response)
        ->toBe(['reason' => 'Conversions API staat uit of pixel-ID/token ontbreekt']);
});

it('zet de rij op failed bij een 400 van Meta en probeert het niet opnieuw', function () {
    Http::fake(['graph.facebook.com/*' => Http::response(['error' => ['message' => 'Invalid OAuth access token', 'code' => 190]], 400)]);
    $order = betaalbareOrder(['status' => 'paid']);

    SendMetaPurchaseEventJob::dispatchSync($order->id);

    $event = MetaCapiEvent::where('order_id', $order->id)->sole();
    expect($event->status)->toBe(MetaCapiEvent::STATUS_FAILED)
        ->and($event->attempts)->toBe(1)
        ->and($event->response['status'])->toBe(400)
        ->and($event->response['body']['error']['code'])->toBe(190);
});

it('gooit bij een storing bij Meta een exception zodat de wachtrij het opnieuw probeert', function () {
    Http::fake(['graph.facebook.com/*' => Http::response('Service Unavailable', 503)]);
    $order = betaalbareOrder(['status' => 'paid']);

    expect(fn () => SendMetaPurchaseEventJob::dispatchSync($order->id))->toThrow(RuntimeException::class);

    expect(MetaCapiEvent::where('order_id', $order->id)->sole()->status)->toBe(MetaCapiEvent::STATUS_FAILED);
});

it('verstuurt alsnog na een eerdere mislukte poging', function () {
    Http::fakeSequence('graph.facebook.com/*')
        ->push(['error' => ['code' => 190]], 400)
        ->push(['events_received' => 1], 200);
    $order = betaalbareOrder(['status' => 'paid']);

    SendMetaPurchaseEventJob::dispatchSync($order->id);
    SendMetaPurchaseEventJob::dispatchSync($order->id);

    $event = MetaCapiEvent::where('order_id', $order->id)->sole();
    expect($event->status)->toBe(MetaCapiEvent::STATUS_SENT)->and($event->attempts)->toBe(2);
});

it('laat de statuswijziging slagen als het klaarzetten van de job mislukt', function () {
    Bus::shouldReceive('dispatch')->andThrow(new RuntimeException('Redis is weg'));
    $order = betaalbareOrder();

    zetStatus($order, 'paid');

    expect($order->fresh()->status)->toBe('paid');
});

it('doet niets als de order niet meer bestaat', function () {
    Http::fake();

    SendMetaPurchaseEventJob::dispatchSync(999999);

    Http::assertNothingSent();
    expect(MetaCapiEvent::count())->toBe(0);
});

it('slaat een order over waarvan de laatste betaling ouder is dan zes dagen', function () {
    Carbon::setTestNow('2026-10-08 12:00:00');
    Http::fake();
    $order = betaalbareOrder(['status' => 'paid']);
    betaaldDagenGeleden($order, 20);
    betaaldDagenGeleden($order, 7);
    // Een niet-betaalde poging van vandaag telt niet als betaalmoment.
    betaaldDagenGeleden($order, 0, 'pending');

    SendMetaPurchaseEventJob::dispatchSync($order->id);

    Http::assertNothingSent();
    $event = MetaCapiEvent::where('order_id', $order->id)->sole();
    expect($event->status)->toBe(MetaCapiEvent::STATUS_SKIPPED)
        ->and($event->response)->toBe(['reason' => 'betaling is ouder dan zes dagen']);
});

it('verstuurt een order waarvan de laatste betaling vijf dagen oud is', function () {
    Carbon::setTestNow('2026-10-08 12:00:00');
    Http::fake(['graph.facebook.com/*' => Http::response(['events_received' => 1], 200)]);
    $order = betaalbareOrder(['status' => 'paid']);
    betaaldDagenGeleden($order, 20);
    betaaldDagenGeleden($order, 5);

    SendMetaPurchaseEventJob::dispatchSync($order->id);

    Http::assertSentCount(1);
    Http::assertSent(fn ($request) => $request['data'][0]['event_time'] === Carbon::now()->subDays(5)->timestamp);
    expect(MetaCapiEvent::where('order_id', $order->id)->sole()->status)->toBe(MetaCapiEvent::STATUS_SENT);
});

it('houdt de vaste volgorde van redenen aan: de betaalleeftijd komt als laatste', function () {
    Carbon::setTestNow('2026-10-08 12:00:00');
    $order = betaalbareOrder(['status' => 'paid', 'total' => 0]);
    betaaldDagenGeleden($order, 7);

    expect(SendMetaPurchaseEventJob::orderSkipReason($order->fresh()))->toBe('orderbedrag is niet positief');
});

it('zet een rij die op pending bleef staan op failed als de job definitief mislukt', function () {
    $order = betaalbareOrder(['status' => 'paid']);
    eventRij($order, MetaCapiEvent::STATUS_PENDING);

    (new SendMetaPurchaseEventJob($order->id))->failed(new RuntimeException("worker weg \xC3\x28 \xFF " . str_repeat('é', 600)));

    $event = MetaCapiEvent::where('order_id', $order->id)->sole();
    expect($event->status)->toBe(MetaCapiEvent::STATUS_FAILED)
        ->and($event->response['error'])->toBeString()->toContain('worker weg')
        ->and(strlen($event->response['error']))->toBeLessThanOrEqual(500)
        ->and(json_encode($event->response))->not->toBeFalse();
});

it('laat een verstuurde rij met rust als de job daarna nog mislukt', function () {
    $order = betaalbareOrder(['status' => 'paid']);
    eventRij($order, MetaCapiEvent::STATUS_SENT, ['status' => 200, 'body' => ['events_received' => 1]]);

    (new SendMetaPurchaseEventJob($order->id))->failed(new RuntimeException('te laat'));
    (new SendMetaPurchaseEventJob(999999))->failed(new RuntimeException('geen rij'));

    $event = MetaCapiEvent::where('order_id', $order->id)->sole();
    expect($event->status)->toBe(MetaCapiEvent::STATUS_SENT)
        ->and($event->response)->toEqual(['status' => 200, 'body' => ['events_received' => 1]]);
});

it('zet een wachtende rij op overgeslagen als de order intussen verwijderd is', function () {
    Http::fake();
    $order = betaalbareOrder(['status' => 'paid']);
    eventRij($order, MetaCapiEvent::STATUS_PENDING);
    $order->forceFill(['deleted_at' => Carbon::now()])->saveQuietly();

    SendMetaPurchaseEventJob::dispatchSync($order->id);

    Http::assertNothingSent();
    $event = MetaCapiEvent::where('order_id', $order->id)->sole();
    expect($event->status)->toBe(MetaCapiEvent::STATUS_SKIPPED)
        ->and($event->response)->toBe(['reason' => 'order bestaat niet meer']);
});

it('markeert een verzending met een test event code en stuurt hem niet nog eens zolang de code er staat', function () {
    Customsetting::set('meta_capi_test_event_code', 'TEST123', $this->site);
    Http::fake(['graph.facebook.com/*' => Http::response(['events_received' => 1], 200)]);
    $order = betaalbareOrder(['status' => 'paid']);

    SendMetaPurchaseEventJob::dispatchSync($order->id);
    SendMetaPurchaseEventJob::dispatchSync($order->id);

    Http::assertSentCount(1);
    $event = MetaCapiEvent::where('order_id', $order->id)->sole();
    expect($event->status)->toBe(MetaCapiEvent::STATUS_SENT)
        ->and($event->response)->toEqual(['status' => 200, 'body' => ['events_received' => 1], 'test' => true])
        ->and($event->attempts)->toBe(1);
});

it('verstuurt een als test verstuurde aankoop alsnog echt nadat de test event code is leeggemaakt', function () {
    Customsetting::set('meta_capi_test_event_code', 'TEST123', $this->site);
    Http::fake(['graph.facebook.com/*' => Http::response(['events_received' => 1], 200)]);
    $order = betaalbareOrder(['status' => 'paid']);
    SendMetaPurchaseEventJob::dispatchSync($order->id);

    Customsetting::set('meta_capi_test_event_code', '', $this->site);
    $event = MetaCapiEvent::where('order_id', $order->id)->sole();

    expect($event->canResend())->toBeTrue()
        ->and($event->resend())->toBeTrue();

    Http::assertSentCount(2);
    $event = $event->fresh();
    expect($event->status)->toBe(MetaCapiEvent::STATUS_SENT)
        ->and($event->response)->not->toHaveKey('test')
        ->and($event->attempts)->toBe(2)
        ->and($event->canResend())->toBeFalse();

    // Nu echt verstuurd: een volgende job doet niets meer.
    SendMetaPurchaseEventJob::dispatchSync($order->id);
    Http::assertSentCount(2);
});

it('stuurt een als test verstuurde aankoop ook zonder de knop alsnog echt zodra de code leeg is', function () {
    Http::fake(['graph.facebook.com/*' => Http::response(['events_received' => 1], 200)]);
    $order = betaalbareOrder(['status' => 'paid']);
    eventRij($order, MetaCapiEvent::STATUS_SENT, ['status' => 200, 'body' => [], 'test' => true]);

    SendMetaPurchaseEventJob::dispatchSync($order->id);

    Http::assertSentCount(1);
    Http::assertSent(fn ($request) => ! array_key_exists('test_event_code', $request->data()));
    expect(MetaCapiEvent::where('order_id', $order->id)->sole()->response)->not->toHaveKey('test');
});

it('kan een gewoon verstuurde aankoop niet opnieuw versturen', function () {
    Http::fake(['graph.facebook.com/*' => Http::response(['events_received' => 1], 200)]);
    $order = betaalbareOrder(['status' => 'paid']);
    SendMetaPurchaseEventJob::dispatchSync($order->id);

    $event = MetaCapiEvent::where('order_id', $order->id)->sole();

    expect($event->response)->not->toHaveKey('test')
        ->and($event->canResend())->toBeFalse()
        ->and($event->resend())->toBeFalse();
    Http::assertSentCount(1);
});
