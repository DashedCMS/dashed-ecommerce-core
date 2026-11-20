<?php

use Livewire\Livewire;
use Dashed\DashedEcommerceCore\Models\Order;
use Dashed\DashedEcommerceCore\Models\OrderReturn;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Dashed\DashedEcommerceCore\Classes\AuroraTone;
use Dashed\DashedEcommerceCore\Filament\Resources\OrderResource\Pages\ViewOrder;

uses(RefreshDatabase::class);

beforeEach(fn () => alsBeheerder());

it('rendert een gewone bestelling in de Aurora-indeling met alle kaarten', function () {
    $order = Order::factory()->metProducten()->betaald()->create();

    Livewire::test(ViewOrder::class, ['record' => $order->getRouteKey()])
        ->assertOk()
        ->assertSee(__('Producten'))
        ->assertSee(__('Betaling'))
        ->assertSee(__('Verzending'))
        ->assertSee(__('Activiteit & tijdlijn'))
        ->assertSee(__('Klantgegevens'))
        ->assertSee(__('Snelle acties'))
        ->assertSee(__('Documenten'))
        ->assertSee(__('Geplaatst'))
        ->assertSee('data-parcel-type', false);
});

it('houdt de sleutelacties van de bestelpagina bereikbaar', function () {
    $order = Order::factory()->metProducten()->create(['status' => 'waiting_for_confirmation', 'total' => 50]);

    Livewire::test(ViewOrder::class, ['record' => $order->getRouteKey()])
        ->assertActionExists('back')
        ->assertActionExists('edit')
        ->assertActionExists('Factuur')
        ->assertActionExists('Pakbon')
        ->assertActionExists('registerReturn')
        ->assertActionExists('viewInWebsite')
        ->assertActionVisible('registerManualPayment');
});

it('toont de factuur- en pakbonlinks uit de bestaande downloadroutes', function () {
    $order = Order::factory()->metProducten()->betaald()->create();

    $component = Livewire::test(ViewOrder::class, ['record' => $order->getRouteKey()]);

    foreach (array_filter([$order->downloadInvoiceUrl(), $order->downloadPackingslipUrl()]) as $url) {
        $component->assertSee(e($url), false);
    }

    expect($order->downloadInvoiceUrl())->not->toBeNull();
});

it('rendert een creditorder zonder stappen en zonder verzendacties', function () {
    $order = Order::factory()->metProducten()->betaald()->create();
    $credit = Order::factory()->create(['status' => 'return', 'credit_for_order_id' => $order->id, 'total' => -10]);

    Livewire::test(ViewOrder::class, ['record' => $credit->getRouteKey()])
        ->assertOk()
        ->assertSee(__('Creditorder :nummer', ['nummer' => $credit->invoice_id]))
        ->assertSee(__('Retour'))
        ->assertDontSee(__('Geplaatst'))
        ->assertDontSee('data-parcel-type', false);
});

it('rendert een bestelling met een open retour met de retourbalk', function () {
    $order = Order::factory()->metProducten()->betaald()->create();
    $return = OrderReturn::create(['order_id' => $order->id, 'email' => 'klant@example.com', 'status' => OrderReturn::STATUS_REQUESTED]);

    Livewire::test(ViewOrder::class, ['record' => $order->getRouteKey()])
        ->assertOk()
        ->assertSee(__('Retour behandelen'))
        ->assertSee(route('filament.dashed.resources.order-returns.view', [$return]));
});

it('vertaalt Filament-kleuren naar Aurora-tonen', function () {
    expect(AuroraTone::from('success'))->toBe('mint')
        ->and(AuroraTone::from('primary'))->toBe('blue')
        ->and(AuroraTone::from('purple'))->toBe('purple')
        ->and(AuroraTone::from('warning'))->toBe('amber')
        ->and(AuroraTone::from('danger'))->toBe('red')
        ->and(AuroraTone::from('gray'))->toBe('neutral')
        ->and(AuroraTone::from(null))->toBe('neutral');
});
