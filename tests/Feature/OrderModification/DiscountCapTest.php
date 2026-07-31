<?php

use Livewire\Livewire;
use Dashed\DashedCore\Models\User;
use Dashed\DashedCore\Classes\Sites;
use Dashed\DashedCore\Models\Customsetting;
use Dashed\DashedEcommerceCore\Models\Order;
use Dashed\DashedEcommerceCore\Models\OrderLog;
use Dashed\DashedEcommerceCore\Models\DiscountCode;
use Dashed\DashedEcommerceCore\Models\OrderProduct;
use Dashed\DashedEcommerceCore\Classes\CurrencyHelper;
use Dashed\DashedEcommerceCore\Classes\OrderModificationService;
use Dashed\DashedEcommerceCore\Filament\Resources\OrderResource\Pages\ModifyOrder;

uses(\Illuminate\Foundation\Testing\RefreshDatabase::class);

/**
 * Een korting kan nooit groter zijn dan het subtotaal, dus zakt hij mee wanneer
 * een wijziging de bestelling kleiner maakt. Bij een cadeaubon verdwijnt het
 * verschil daarmee uit het zicht: used_amount van de bon staat nog op het volle
 * bedrag terwijl de klant het niet meer krijgt. Terugboeken gebeurt bewust niet
 * automatisch, maar het mag ook niet stilletjes gebeuren.
 */
beforeEach(function () {
    Customsetting::set('taxes_prices_include_taxes', 1);
    // DiscountCode::created logt een aangemaakte cadeaubon op auth()->user()->id.
    $this->actingAs(User::factory()->create(['role' => 'superadmin']));
});

function capGiftcard(float $balance = 100.0): DiscountCode
{
    return DiscountCode::create([
        'site_ids' => [Sites::getActive()],
        'name' => 'Cadeaubon',
        'code' => 'BON-' . strtoupper(uniqid()),
        'is_giftcard' => 1,
        'discount_amount' => $balance,
        'use_stock' => 0,
    ]);
}

function capAmountCode(): DiscountCode
{
    return DiscountCode::create([
        'site_ids' => [Sites::getActive()],
        'name' => 'Vaste korting',
        'code' => 'KORTING-' . strtoupper(uniqid()),
        'type' => 'amount',
        'discount_amount' => 40,
        'use_stock' => 0,
    ]);
}

/**
 * Een concept met één regel van € 100 en € 40 korting, dus alles wat de regel
 * onder de € 40 brengt topt de korting af.
 */
function capOrder(?DiscountCode $code, string $status = Order::STATUS_CONCEPT): Order
{
    $order = new Order();
    $order->email = 'klant@example.com';
    $order->status = $status;
    $order->discount_code_id = $code?->id;
    $order->discount = 40;
    $order->subtotal = 100;
    $order->total = 60;
    $order->save();

    OrderProduct::create(['order_id' => $order->id, 'name' => 'Product', 'quantity' => 1, 'price' => 100, 'vat_rate' => 21]);

    return $order->fresh();
}

function capLog(Order $order): ?OrderLog
{
    return OrderLog::where('order_id', $order->id)->where('tag', 'order.discount.capped')->first();
}

it('logt op de bestelling dat een korting is afgetopt, met bedragen en verschil', function () {
    $order = capOrder(capAmountCode());

    OrderModificationService::applyInPlace($order, [
        ['order_product_id' => null, 'product_id' => null, 'name' => 'Kleiner product', 'quantity' => 1, 'price' => 25.0, 'vat_rate' => 21, 'product_extras' => []],
    ], ['send_customer_email' => false]);

    $log = capLog($order);

    expect($log)->not->toBeNull()
        // Via CurrencyHelper zodat de test niet op de opmaak van bedragen
        // struikelt maar wel op de bedragen zelf.
        ->and($log->note)->toContain('van ' . CurrencyHelper::formatPrice(40.0) . ' naar ' . CurrencyHelper::formatPrice(25.0))
        ->and($log->note)->toContain('(' . CurrencyHelper::formatPrice(15.0) . ' minder)')
        ->and($log->note)->not->toContain('cadeaubon');
});

it('noemt de cadeaubon expliciet in het logboek, want dat is echt klantsaldo', function () {
    $giftcard = capGiftcard();
    $order = capOrder($giftcard);

    OrderModificationService::applyInPlace($order, [
        ['order_product_id' => null, 'product_id' => null, 'name' => 'Kleiner product', 'quantity' => 1, 'price' => 25.0, 'vat_rate' => 21, 'product_extras' => []],
    ], ['send_customer_email' => false]);

    expect(capLog($order)?->note)->toContain('cadeaubon ' . $giftcard->code);
});

it('logt niets wanneer de korting gewoon binnen het subtotaal past', function () {
    $order = capOrder(capAmountCode());

    // Het subtotaal blijft boven de € 40 korting, dus er valt niets af te toppen.
    OrderModificationService::applyInPlace($order, [
        ['order_product_id' => null, 'product_id' => null, 'name' => 'Ander product', 'quantity' => 1, 'price' => 80.0, 'vat_rate' => 21, 'product_extras' => []],
    ], ['send_customer_email' => false]);

    expect(capLog($order))->toBeNull()
        ->and(round((float) $order->fresh()->discount, 2))->toBe(40.0);
});

it('logt de aftopping ook op de vervangende bestelling', function () {
    // De vervangingstak schrijft de verlaagde korting op de nieuwe order weg;
    // zonder log in recalculate() zou juist die tak niets nalaten.
    $giftcard = capGiftcard();
    $order = capOrder($giftcard, status: 'paid');
    $order->orderPayments()->create(['status' => 'paid', 'amount' => 60, 'psp' => 'own']);

    $newOrder = OrderModificationService::replaceWithNewOrder($order->fresh(), [
        ['order_product_id' => null, 'product_id' => null, 'name' => 'Kleiner product', 'quantity' => 1, 'price' => 25.0, 'vat_rate' => 21, 'product_extras' => []],
    ], ['send_customer_email' => false, 'credit_old_order' => false]);

    expect(capLog($newOrder)?->note)->toContain('cadeaubon ' . $giftcard->code)
        ->and(round((float) $newOrder->fresh()->discount, 2))->toBe(25.0);
});

it('waarschuwt in de bevestigingsstap dat de korting verlaagd wordt', function () {
    $giftcard = capGiftcard();
    $order = capOrder($giftcard);

    $description = Livewire::test(ModifyOrder::class, ['record' => $order->id])
        ->set('data.lines.0.price', 25.0)
        ->instance()
        ->submitAction()
        ->getModalDescription();

    expect($description)->toContain('korting verlaagd van ' . CurrencyHelper::formatPrice(40.0) . ' naar ' . CurrencyHelper::formatPrice(25.0))
        ->and($description)->toContain(CurrencyHelper::formatPrice(15.0) . ' minder')
        ->and($description)->toContain('cadeaubon ' . $giftcard->code);
});

it('waarschuwt niet in de bevestigingsstap zolang de korting past', function () {
    $order = capOrder(capAmountCode());

    $description = Livewire::test(ModifyOrder::class, ['record' => $order->id])
        ->set('data.lines.0.price', 80.0)
        ->instance()
        ->submitAction()
        ->getModalDescription();

    expect($description)->toContain('Toegepaste korting: ' . CurrencyHelper::formatPrice(40.0))
        ->and($description)->not->toContain('verlaagd');
});
