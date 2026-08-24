<?php

use Livewire\Livewire;
use Dashed\DashedCore\Models\User;
use Illuminate\Support\Facades\Mail;
use Dashed\DashedCore\Models\Customsetting;
use Dashed\DashedEcommerceCore\Models\Order;
use Dashed\DashedEcommerceCore\Models\OrderLog;
use Dashed\DashedEcommerceCore\Models\OrderProduct;
use Dashed\DashedEcommerceCore\Classes\OrderModificationService;
use Dashed\DashedEcommerceCore\Mail\FulfillmentStatusHandledMail;
use Dashed\DashedEcommerceCore\Events\Orders\OrderFulfillmentStatusChangedEvent;
use Dashed\DashedEcommerceCore\Filament\Resources\OrderResource\Pages\ModifyOrder;

uses(\Illuminate\Foundation\Testing\RefreshDatabase::class);

beforeEach(function () {
    Customsetting::set('taxes_prices_include_taxes', 1);
    // Zonder deze instelling stuurt changeFulfillmentStatus() sowieso geen
    // afgehandeld-mail, en dan bewijst "er ging geen mail uit" niets.
    Customsetting::set('fulfillment_status_handled_enabled', 1);
});

/**
 * Namen bewust uniek binnen de map OrderModification: alle bestanden daar
 * draaien in hetzelfde PHP-proces, dus globale functies mogen niet botsen.
 */
function statusOrder(string $invoiceId, string $fulfillmentStatus = 'shipped'): Order
{
    $order = Order::create([
        'email' => 'klant@example.com',
        'status' => 'paid',
        'fulfillment_status' => $fulfillmentStatus,
        'invoice_id' => $invoiceId,
        'total' => 100,
        'subtotal' => 100,
    ]);
    OrderProduct::create([
        'order_id' => $order->id,
        'name' => 'Oud product',
        'quantity' => 1,
        'price' => 100,
        'vat_rate' => 21,
    ]);
    $order->orderPayments()->create(['status' => 'paid', 'amount' => 100, 'psp' => 'own']);

    return $order->fresh();
}

function statusLine(string $name = 'Ander product', float $price = 100.0): array
{
    return [
        'order_product_id' => null,
        'product_id' => null,
        'name' => $name,
        'quantity' => 1,
        'price' => $price,
        'vat_rate' => 21,
        'product_extras' => [],
    ];
}

it('zet de oude bestelling standaard op afgehandeld in de credittak', function () {
    Mail::fake();

    $old = statusOrder('2026-0001');

    OrderModificationService::replaceWithNewOrder($old->fresh(), [statusLine()]);

    expect($old->fresh()->fulfillment_status)->toBe('handled');
});

it('zet de oude bestelling standaard op afgehandeld in de annuleertak', function () {
    Mail::fake();

    $old = statusOrder('PROFORMA');

    OrderModificationService::replaceWithNewOrder($old->fresh(), [statusLine()]);

    expect($old->fresh()->fulfillment_status)->toBe('handled');
});

it('respecteert een andere gekozen status', function () {
    Mail::fake();

    $old = statusOrder('2026-0001', 'unhandled');

    OrderModificationService::replaceWithNewOrder(
        $old->fresh(),
        [statusLine()],
        ['old_order_fulfillment_status' => 'in_treatment'],
    );

    expect($old->fresh()->fulfillment_status)->toBe('in_treatment');
});

it('stuurt de klant geen afgehandeld-mail bij het verzetten', function () {
    Mail::fake();

    $old = statusOrder('2026-0001');

    OrderModificationService::replaceWithNewOrder($old->fresh(), [statusLine()]);

    expect($old->fresh()->fulfillment_status)->toBe('handled');
    Mail::assertNotSent(FulfillmentStatusHandledMail::class);
});

it('vuurt geen statuswijzigings-event bij het verzetten', function () {
    Mail::fake();
    Event::fake([OrderFulfillmentStatusChangedEvent::class]);

    $old = statusOrder('2026-0001');

    OrderModificationService::replaceWithNewOrder($old->fresh(), [statusLine()]);

    Event::assertNotDispatched(OrderFulfillmentStatusChangedEvent::class);
});

it('legt het verzetten vast in het orderlogboek', function () {
    Mail::fake();

    $old = statusOrder('2026-0001');

    OrderModificationService::replaceWithNewOrder($old->fresh(), [statusLine()]);

    expect(OrderLog::where('order_id', $old->id)->where('tag', 'order.modified.fulfillment-status')->exists())->toBeTrue();
});

it('laat de status met rust als de gekozen status de huidige status is', function () {
    Mail::fake();

    $old = statusOrder('2026-0001', 'packed');

    OrderModificationService::replaceWithNewOrder(
        $old->fresh(),
        [statusLine()],
        ['old_order_fulfillment_status' => 'packed'],
    );

    expect($old->fresh()->fulfillment_status)->toBe('packed')
        ->and(OrderLog::where('order_id', $old->id)->where('tag', 'order.modified.fulfillment-status')->exists())->toBeFalse();
});

it('biedt het keuzeveld aan met afgehandeld als standaard', function () {
    $this->actingAs(User::factory()->create(['role' => 'superadmin']));

    $order = statusOrder('2026-0001');

    Livewire::test(ModifyOrder::class, ['record' => $order->id])
        ->assertSet('data.old_order_fulfillment_status', 'handled');
});

it('toont het keuzeveld niet op de in-plaats-route', function () {
    $this->actingAs(User::factory()->create(['role' => 'superadmin']));

    $order = Order::create([
        'email' => 'klant@example.com',
        'status' => Order::STATUS_CONCEPT,
        'invoice_id' => 'PROFORMA',
        'total' => 100,
        'subtotal' => 100,
    ]);
    OrderProduct::create(['order_id' => $order->id, 'name' => 'Oud product', 'quantity' => 1, 'price' => 100, 'vat_rate' => 21]);

    expect(OrderModificationService::canModifyInPlace($order->fresh()))->toBeTrue();

    $page = Livewire::test(ModifyOrder::class, ['record' => $order->id])->instance();

    $veld = collect($page->modifyOrderForm->getFlatComponents(withHidden: true))
        ->first(fn ($component) => method_exists($component, 'getName')
            && $component->getName() === 'old_order_fulfillment_status');

    expect($veld)->not->toBeNull()
        ->and($veld->isHidden())->toBeTrue();
});

it('geeft de gekozen status door vanuit het wijzigscherm', function () {
    Mail::fake();
    $this->actingAs(User::factory()->create(['role' => 'superadmin']));

    $order = statusOrder('2026-0001', 'unhandled');

    Livewire::test(ModifyOrder::class, ['record' => $order->id])
        ->set('data.lines', [
            ['order_product_id' => null, 'product_id' => null, 'name' => 'Nieuw product', 'quantity' => 1, 'price' => 121.0, 'vat_rate' => 21],
        ])
        ->set('data.send_customer_email', false)
        ->set('data.old_order_fulfillment_status', 'ready_for_pickup')
        ->call('submit');

    expect($order->fresh()->fulfillment_status)->toBe('ready_for_pickup');
});
