<?php

use Livewire\Livewire;
use Filament\Actions\ActionGroup;
use Dashed\DashedCore\Models\User;
use Dashed\DashedPages\Models\Page;
use Dashed\DashedCore\Classes\Sites;
use Illuminate\Support\Facades\Queue;
use Dashed\DashedCore\Models\Customsetting;
use Dashed\DashedEcommerceCore\Models\Order;
use Dashed\DashedEcommerceCore\Models\OrderProduct;
use Dashed\DashedEcommerceCore\Filament\Resources\OrderResource\Pages\ViewOrder;

uses(\Illuminate\Foundation\Testing\RefreshDatabase::class);

beforeEach(function () {
    Queue::fake();
    Customsetting::set('taxes_prices_include_taxes', 1);
    $user = User::factory()->create(['role' => 'superadmin']);
    $this->actingAs($user);

    // ViewOrder bouwt een knop met Order::getUrl(), en die valt over een
    // ontbrekende besteloverzicht-pagina heen. Los van deze test, maar zonder
    // de pagina rendert de pagina helemaal niet.
    $page = Page::create([
        'name' => ['nl' => 'Bestelling', 'en' => 'Order'],
        'slug' => ['nl' => 'bestelling', 'en' => 'order'],
        'site_ids' => [Sites::getActive()],
        'public' => 1,
    ]);
    Customsetting::set('order_page_id', $page->id);
});

/**
 * Namen bewust uniek binnen de map OrderModification: alle bestanden daar
 * draaien in hetzelfde PHP-proces, dus globale functies mogen niet botsen.
 */
function actionGroupOrder(string $status = 'paid'): Order
{
    $order = Order::create([
        'email' => 'klant@example.com',
        'status' => $status,
        'fulfillment_status' => 'unhandled',
        'invoice_id' => 'PROFORMA',
        'total' => 100,
        'subtotal' => 100,
    ]);
    OrderProduct::create(['order_id' => $order->id, 'name' => 'Product', 'quantity' => 1, 'price' => 100, 'vat_rate' => 21]);
    if ($status === 'paid') {
        $order->orderPayments()->create(['status' => 'paid', 'amount' => 100, 'psp' => 'own']);
    }

    return $order->fresh();
}

/**
 * De groep heeft zelf geen naam in Filament, dus zoeken we hem op via de
 * acties die erin zitten.
 */
function wijzigGroep(ViewOrder $page): ?ActionGroup
{
    foreach ($page->getCachedHeaderActions() as $action) {
        if (! $action instanceof ActionGroup) {
            continue;
        }

        foreach ($action->getFlatActions() as $name => $child) {
            if ($name === 'edit') {
                return $action;
            }
        }
    }

    return null;
}

it('zet gegevens en producten samen in een knoppengroep', function () {
    $order = actionGroupOrder();

    $page = Livewire::test(ViewOrder::class, ['record' => $order->id])->instance();
    $groep = wijzigGroep($page);

    expect($groep)->not->toBeNull();
    expect(array_keys($groep->getFlatActions()))->toBe(['edit', 'modify']);
});

it('houdt alleen gegevens over als de bestelling niet meer te wijzigen is', function () {
    $order = actionGroupOrder(status: 'cancelled');

    expect($order->isModifiable())->toBeFalse();

    $page = Livewire::test(ViewOrder::class, ['record' => $order->id])->instance();
    $groep = wijzigGroep($page);

    expect($groep)->not->toBeNull();

    $zichtbaar = collect($groep->getFlatActions())
        ->filter(fn ($action) => $action->isVisible())
        ->keys()
        ->all();

    expect($zichtbaar)->toBe(['edit']);
});

it('toont geen losse potloodknop meer naast de groep', function () {
    $order = actionGroupOrder();

    $page = Livewire::test(ViewOrder::class, ['record' => $order->id])->instance();

    $losseNamen = collect($page->getCachedHeaderActions())
        ->reject(fn ($action) => $action instanceof ActionGroup)
        ->map(fn ($action) => $action->getName())
        ->all();

    expect($losseNamen)->not->toContain('edit');
    expect($losseNamen)->not->toContain('modify');
});
