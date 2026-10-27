<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Event;
use Dashed\DashedCore\Classes\Sites;
use Dashed\DashedCore\Models\Customsetting;
use Dashed\DashedEcommerceCore\Models\Order;
use Dashed\DashedEcommerceCore\Models\Product;
use Dashed\DashedEcommerceCore\Models\DiscountCode;
use Dashed\DashedEcommerceCore\Models\OrderPayment;
use Dashed\DashedEcommerceCore\Models\OrderProduct;
use Dashed\DashedEcommerceCore\Models\ProductGroup;
use Dashed\DashedEcommerceCore\Events\Orders\OrderCancelledEvent;
use Dashed\DashedEcommerceCore\Livewire\Frontend\Checkout\Checkout;
use Dashed\DashedEcommerceCore\Events\Orders\OrderMarkedAsPaidEvent;
use Dashed\DashedEcommerceCore\Events\Orders\OrderFulfillmentStatusChangedEvent;

/**
 * Afrekenen met een verkochte cadeaubon: de bon wordt een betaling op de
 * order (psp giftcard), totaal en btw blijven de volle bedragen en alleen wat
 * er daarna openstaat gaat naar de betaalprovider. De Livewire-checkout zelf
 * draait niet in dit harnas; de testsubklasse geeft de inwisselingen van de
 * wagen mee en roept dezelfde stappen aan als submit().
 */
beforeEach(function () {
    Customsetting::set('taxes_prices_include_taxes', 1);
    Bus::fake();
    Mail::fake();
    Event::fake([OrderMarkedAsPaidEvent::class, OrderCancelledEvent::class, OrderFulfillmentStatusChangedEvent::class]);
});

class GiftcardCheckoutProbe extends Checkout
{
    public array $redemptions = [];

    protected function giftcardRedemptions(): array
    {
        return $this->redemptions;
    }

    public function pay(Order $order): ?OrderPayment
    {
        return $this->payWithGiftcards($order);
    }

    public function covered(Order $order): bool
    {
        return $this->giftcardsCoverOrder($order);
    }

    public function rest(Order $order): float
    {
        return $this->amountForPaymentProvider($order);
    }

    public function balancesChanged(): bool
    {
        return $this->giftcardBalancesChanged();
    }
}

function afrekenBon(float $saldo): DiscountCode
{
    $code = new DiscountCode();
    $code->forceFill([
        'site_ids' => [Sites::getActive()],
        'name' => 'Cadeaubon',
        'code' => 'LOV-' . strtoupper(uniqid()),
        'type' => 'amount',
        'discount_amount' => $saldo,
        'initial_amount' => $saldo,
        'is_giftcard' => 1,
        'giftcard_type' => 'verkocht',
        'valid_for' => 'all',
    ]);
    DiscountCode::withoutEvents(fn () => $code->save());

    return $code->fresh();
}

/**
 * Een order zoals submit() hem opslaat: één regel van € 50 (21% btw) op een
 * product met voorraad.
 */
function afrekenOrder(): array
{
    $group = ProductGroup::create([
        'name' => ['nl' => 'Groep'],
        'slug' => ['nl' => 'groep-' . uniqid()],
        'short_description' => ['nl' => ''],
        'description' => ['nl' => ''],
        'content' => ['nl' => ''],
        'search_terms' => ['nl' => ''],
        'site_ids' => [Sites::getActive()],
    ]);

    $product = Product::withoutEvents(fn () => Product::create([
        'name' => ['nl' => 'Vaas'],
        'slug' => ['nl' => 'vaas-' . uniqid()],
        'site_ids' => [Sites::getActive()],
        'product_group_id' => $group->id,
        'use_stock' => 1,
        'stock' => 5,
        'total_stock' => 5,
        'in_stock' => 1,
        'stock_status' => 'in_stock',
        'price' => 50,
        'current_price' => 50,
        'vat_rate' => 21,
        'public' => 1,
    ]));

    $order = Order::create([
        'email' => 'klant@example.com',
        'first_name' => 'Kim',
        'last_name' => 'Jansen',
        'status' => 'pending',
        'invoice_id' => 'PROFORMA',
        'subtotal' => 50,
        'total' => 50,
        'btw' => 8.68,
        'vat_percentages' => ['21' => 8.68],
    ]);

    OrderProduct::withoutEvents(fn () => OrderProduct::create([
        'order_id' => $order->id,
        'product_id' => $product->id,
        'name' => 'Vaas',
        'quantity' => 1,
        'price' => 50,
        'vat_rate' => 21,
        'btw' => 8.68,
        'sku' => 'VAAS',
    ]));

    return [$order->fresh(), $product];
}

function afrekenProbe(DiscountCode $bon, float $bedrag): GiftcardCheckoutProbe
{
    $probe = new GiftcardCheckoutProbe();
    $probe->redemptions = [['code' => $bon->code, 'discount_code_id' => $bon->id, 'redeemed' => $bedrag]];

    return $probe;
}

it('betaalt een bestelling die de bon helemaal dekt zonder betaalprovider', function () {
    [$order, $product] = afrekenOrder();
    $bon = afrekenBon(100);
    $probe = afrekenProbe($bon, 50);

    $payment = $probe->pay($order);

    expect($payment->psp)->toBe('giftcard')
        ->and((float) $payment->amount)->toBe(50.0)
        ->and($payment->status)->toBe('paid')
        ->and($payment->payment_method)->toBe('Cadeaubon ' . $bon->code)
        ->and($payment->payment_method_id)->toBeNull()
        ->and($probe->covered($order))->toBeTrue()
        ->and($probe->rest($order))->toBe(0.0);

    $order->changeStatus('paid');
    $order = $order->fresh();

    expect($order->status)->toBe('paid')
        ->and($order->orderPayments()->count())->toBe(1)
        ->and((float) $order->total)->toBe(50.0)
        ->and((float) $order->btw)->toBe(8.68)
        ->and((float) $order->btw)->toBe((float) $order->orderProducts()->sum('btw'))
        ->and((int) $product->fresh()->stock)->toBe(4)
        ->and($order->applied_gift_cards[0]['status'])->toBe('used')
        ->and((float) $bon->fresh()->discount_amount)->toBe(50.0)
        ->and((float) $bon->fresh()->used_amount)->toBe(50.0)
        ->and((float) $bon->fresh()->reserved_amount)->toBe(0.0);

    Event::assertDispatchedTimes(OrderMarkedAsPaidEvent::class, 1);
});

it('maakt een bestelling met bon en betaalprovider betaald zodra de rest binnen is', function () {
    [$order, $product] = afrekenOrder();
    $bon = afrekenBon(20);
    $probe = afrekenProbe($bon, 30);

    $probe->pay($order);

    expect($probe->covered($order))->toBeFalse()
        ->and($probe->rest($order))->toBe(30.0)
        ->and($order->fresh()->status)->toBe('pending')
        ->and((float) $bon->fresh()->reserved_amount)->toBe(20.0);

    $psp = $order->orderPayments()->create(['status' => 'pending', 'amount' => $probe->rest($order), 'psp' => 'paynl']);
    $order->changeStatus($psp->changeStatus('paid'));
    $order = $order->fresh();

    expect($order->status)->toBe('paid')
        ->and((int) $product->fresh()->stock)->toBe(4)
        ->and((float) $bon->fresh()->used_amount)->toBe(20.0)
        ->and((float) $bon->fresh()->reserved_amount)->toBe(0.0);

    Event::assertDispatchedTimes(OrderMarkedAsPaidEvent::class, 1);
});

it('zet de bon terug als de rest bij de betaalprovider mislukt', function () {
    [$order] = afrekenOrder();
    $bon = afrekenBon(50);
    $probe = afrekenProbe($bon, 30);
    $probe->pay($order);

    $psp = $order->orderPayments()->create(['status' => 'pending', 'amount' => $probe->rest($order), 'psp' => 'paynl']);
    $order->changeStatus($psp->changeStatus('cancelled'));
    $order = $order->fresh();

    expect($order->status)->toBe('cancelled')
        ->and((float) $bon->fresh()->discount_amount)->toBe(50.0)
        ->and((float) $bon->fresh()->reserved_amount)->toBe(0.0)
        ->and($order->applied_gift_cards[0]['status'])->toBe('released')
        ->and($order->orderPayments()->where('psp', 'giftcard')->value('status'))->toBe('cancelled');
});

it('neemt bij weinig saldo alleen het saldo en laat de rest naar de betaalprovider gaan', function () {
    [$order] = afrekenOrder();
    $bon = afrekenBon(15);
    // De wagen toonde nog € 30, intussen is er elders € 15 van de bon gebruikt.
    $probe = afrekenProbe($bon, 30);

    $payment = $probe->pay($order);

    expect((float) $payment->amount)->toBe(15.0)
        ->and($probe->rest($order))->toBe(35.0);
});

it('doet niets zonder bonnen', function () {
    [$order] = afrekenOrder();
    $probe = new GiftcardCheckoutProbe();

    expect($probe->pay($order))->toBeNull()
        ->and($probe->covered($order))->toBeFalse()
        ->and($probe->rest($order))->toBe(50.0);
});

it('ziet vóór het aanmaken van de order dat het saldo intussen lager is', function () {
    $bon = afrekenBon(50);
    $probe = afrekenProbe($bon, 50);

    expect($probe->balancesChanged())->toBeFalse();

    // Elders € 20 van de bon gebruikt terwijl de klant in de checkout zat.
    $bon->forceFill(['discount_amount' => 30]);
    DiscountCode::withoutEvents(fn () => $bon->save());

    expect($probe->balancesChanged())->toBeTrue();
});

it('ziet een bon die geen verkochte bon meer is als gewijzigd', function () {
    $bon = afrekenBon(50);
    $probe = afrekenProbe($bon, 20);
    $bon->forceFill(['giftcard_type' => 'promo']);
    DiscountCode::withoutEvents(fn () => $bon->save());

    expect($probe->balancesChanged())->toBeTrue();
});

it('stuurt bij een tekort na het reserveren de rest naar de betaalprovider in plaats van te annuleren', function () {
    [$order] = afrekenOrder();
    $bon = afrekenBon(50);
    $probe = afrekenProbe($bon, 50);

    // De race tussen de controle en reserve(): het saldo zakt net daartussen.
    $bon->forceFill(['discount_amount' => 35]);
    DiscountCode::withoutEvents(fn () => $bon->save());

    $payment = $probe->pay($order);

    expect((float) $payment->amount)->toBe(35.0)
        ->and($probe->covered($order))->toBeFalse()
        ->and($probe->rest($order))->toBe(15.0)
        ->and($order->fresh()->status)->toBe('pending');

    Event::assertNotDispatched(OrderCancelledEvent::class);
});
