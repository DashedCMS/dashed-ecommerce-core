<?php

use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Http;
use Dashed\DashedCore\Classes\Sites;
use Dashed\DashedCore\Models\Customsetting;
use Dashed\DashedCore\Retention\RetentionRegistry;
use Dashed\DashedEcommerceCore\Models\Order;
use Dashed\DashedEcommerceCore\Models\OrderProduct;
use Dashed\DashedEcommerceCore\Models\OrderTracking;
use Dashed\DashedEcommerceCore\Filament\Resources\MetaCapiEventResource;
use Dashed\DashedEcommerceCore\Filament\Pages\Settings\MetaCapiSettingsPage;
use Dashed\DashedEcommerceCore\Models\MetaCapiEvent;
use Dashed\DashedEcommerceCore\Jobs\SendMetaPurchaseEventJob;
use Dashed\DashedEcommerceCore\Services\Meta\MetaCapiSettings;
use Dashed\DashedEcommerceCore\DashedEcommerceCoreServiceProvider;

function beheerOrder(): Order
{
    $order = Order::create(['status' => 'paid', 'email' => 'jan@example.com', 'country' => 'Nederland', 'total' => 35.03, 'subtotal' => 35.03, 'btw' => 6.08, 'discount' => 0]);
    // Product 501 bestaat niet in de testdatabase; alleen de FK-controle uit.
    Schema::disableForeignKeyConstraints();
    OrderProduct::create(['order_id' => $order->id, 'product_id' => 501, 'name' => 'Vaas', 'quantity' => 1, 'price' => 35.03, 'btw' => 6.08]);
    Schema::enableForeignKeyConstraints();

    OrderTracking::create(['order_id' => $order->id, 'event_source_url' => 'https://shop.test/checkout', 'marketing_consent' => true]);

    return $order->fresh();
}

beforeEach(function () {
    Http::preventStrayRequests();
    $this->site = Sites::getActive();
    Customsetting::set('facebook_pixel_conversion_id', '599475696120398', $this->site);
    MetaCapiSettings::for($this->site)->storeAccessToken('EAAB-geheim');
});

it('zet een mislukt event terug op pending en zet de job opnieuw klaar', function (string $status) {
    Bus::fake([SendMetaPurchaseEventJob::class]);
    $order = beheerOrder();
    $event = MetaCapiEvent::create(['site_id' => $this->site, 'order_id' => $order->id, 'event_name' => 'Purchase', 'event_id' => 'purchase_' . $order->id, 'status' => $status]);

    expect($event->resend())->toBeTrue()
        ->and($event->fresh()->status)->toBe(MetaCapiEvent::STATUS_PENDING);
    Bus::assertDispatched(SendMetaPurchaseEventJob::class, fn ($job) => $job->orderId === $order->id && $job->queue === 'ecommerce');
})->with(['failed', 'skipped']);

it('verstuurt een al verstuurd event of een event zonder order niet opnieuw', function () {
    Bus::fake([SendMetaPurchaseEventJob::class]);
    $order = beheerOrder();
    $verstuurd = MetaCapiEvent::create(['site_id' => $this->site, 'order_id' => $order->id, 'event_name' => 'Purchase', 'event_id' => 'purchase_a', 'status' => 'sent']);
    $zonderOrder = MetaCapiEvent::create(['site_id' => $this->site, 'event_name' => 'Purchase', 'event_id' => 'purchase_b', 'status' => 'failed']);

    expect($verstuurd->resend())->toBeFalse()->and($zonderOrder->resend())->toBeFalse();
    Bus::assertNotDispatched(SendMetaPurchaseEventJob::class);
});

it('weigert het testcommando zonder test event code', function () {
    Http::fake();

    $this->artisan('meta:capi-test', ['order' => beheerOrder()->id])
        ->expectsOutputToContain('test event code')
        ->assertExitCode(1);

    Http::assertNothingSent();
});

it('meldt een onbekende order', function () {
    Customsetting::set('meta_capi_test_event_code', 'TEST123', $this->site);

    $this->artisan('meta:capi-test', ['order' => 999999])
        ->expectsOutputToContain('niet gevonden')
        ->assertExitCode(1);
});

it('verstuurt met het testcommando een testevent, toont de response en laat het log ongemoeid', function () {
    Customsetting::set('meta_capi_test_event_code', 'TEST123', $this->site);
    Http::fake(['graph.facebook.com/*' => Http::response(['events_received' => 1, 'fbtrace_id' => 'xyz'], 200)]);
    $order = beheerOrder();

    $this->artisan('meta:capi-test', ['order' => $order->id])
        ->expectsOutputToContain('purchase_' . $order->id)
        ->expectsOutputToContain('events_received')
        ->doesntExpectOutputToContain('EAAB-geheim')
        ->assertExitCode(0);

    Http::assertSent(fn ($request) => $request['test_event_code'] === 'TEST123');
    expect(MetaCapiEvent::count())->toBe(0);
});

it('eindigt het testcommando met een fout als Meta het event afkeurt', function () {
    Customsetting::set('meta_capi_test_event_code', 'TEST123', $this->site);
    Http::fake(['graph.facebook.com/*' => Http::response(['error' => ['message' => 'Invalid parameter']], 400)]);

    $this->artisan('meta:capi-test', ['order' => beheerOrder()->id])
        ->expectsOutputToContain('Invalid parameter')
        ->assertExitCode(1);
});

it('meldt het eventlog aan bij de bewaartermijnen', function () {
    app(RetentionRegistry::class)->flush();
    DashedEcommerceCoreServiceProvider::registreerBewaartermijnen();

    expect(app(RetentionRegistry::class)->vind('meta_capi_events'))->not->toBeNull();
});

it('weigert het testcommando voor een order zonder klantsignalen', function () {
    Customsetting::set('meta_capi_test_event_code', 'TEST123', $this->site);
    Http::fake();
    $order = beheerOrder();
    OrderTracking::where('order_id', $order->id)->delete();

    $this->artisan('meta:capi-test', ['order' => $order->id])
        ->expectsOutputToContain('geen klantsignalen')
        ->assertExitCode(1);

    Http::assertNothingSent();
});

it('weigert het testcommando voor een order zonder marketingtoestemming', function () {
    Customsetting::set('meta_capi_test_event_code', 'TEST123', $this->site);
    Http::fake();
    $order = beheerOrder();
    OrderTracking::where('order_id', $order->id)->update(['marketing_consent' => false]);

    $this->artisan('meta:capi-test', ['order' => $order->id])
        ->expectsOutputToContain('geen marketingtoestemming')
        ->assertExitCode(1);

    Http::assertNothingSent();
});

it('weigert het testcommando voor een niet betaalde order', function () {
    Customsetting::set('meta_capi_test_event_code', 'TEST123', $this->site);
    Http::fake();
    $order = beheerOrder();
    $order->forceFill(['status' => 'pending'])->saveQuietly();

    $this->artisan('meta:capi-test', ['order' => $order->id])
        ->expectsOutputToContain('niet betaald')
        ->assertExitCode(1);

    Http::assertNothingSent();
});

it('geeft het log dezelfde toegang als de instellingenpagina', function () {
    expect(MetaCapiEventResource::canAccess())->toBeFalse()
        ->and(MetaCapiEventResource::canAccess())->toBe(MetaCapiSettingsPage::canAccess())
        ->and(MetaCapiEventResource::canViewAny())->toBe(MetaCapiSettingsPage::canAccess());
});
