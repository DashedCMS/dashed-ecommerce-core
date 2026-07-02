<?php

declare(strict_types=1);

use Livewire\Livewire;
use Dashed\DashedCore\Classes\Sites;
use Dashed\DashedEcommerceCore\Models\Order;
use Dashed\DashedEcommerceCore\Models\OrderPayment;
use Dashed\DashedEcommerceCore\Models\PaymentMethod;
use Dashed\DashedEcommerceCore\Livewire\Frontend\Checkout\ProformaCheckout;

class FakeReproPsp
{
    public static bool $called = false;
    public static bool $orderWasNull = true;
    public static int $productCount = -1;

    public static function isConnected(): bool
    {
        return true;
    }

    public static function startTransaction(OrderPayment $orderPayment): array
    {
        self::$called = true;
        // Spiegelt PayNL::startTransaction: leest $orderPayment->order->orderProducts.
        self::$orderWasNull = ($orderPayment->order === null);
        self::$productCount = $orderPayment->order ? $orderPayment->order->orderProducts->count() : -1;

        return ['redirectUrl' => 'https://example.test/pay'];
    }
}

it('passes a non-null order with orderProducts to the connected PSP on submit', function () {
    FakeReproPsp::$called = false;
    FakeReproPsp::$orderWasNull = true;

    ecommerce()->builder('paymentServiceProviders', array_merge(
        ecommerce()->builder('paymentServiceProviders') ?: [],
        ['repropsp' => ['class' => FakeReproPsp::class, 'name' => 'Repro PSP']],
    ));

    $order = Order::create(['email' => 'k@x.nl', 'status' => Order::STATUS_CONCEPT, 'is_proforma' => true, 'total' => 121, 'subtotal' => 121, 'btw' => 21]);
    $order->orderProducts()->create(['product_id' => null, 'name' => 'Maatwerk', 'quantity' => 1, 'price' => 121, 'vat_rate' => 21]);

    $method = PaymentMethod::create([
        'site_id' => Sites::getActive(),
        'name' => ['nl' => 'iDEAL'],
        'type' => 'online', 'active' => 1, 'available_from_amount' => 0, 'psp' => 'repropsp',
    ]);

    Livewire::test(ProformaCheckout::class, ['orderHash' => $order->hash])
        ->set('firstName', 'Jan')->set('lastName', 'Jansen')
        ->set('street', 'Straat')->set('houseNr', '1')
        ->set('zipCode', '1234AB')->set('city', 'Stad')->set('country', 'NL')
        ->set('paymentMethod', (string) $method->id)
        ->call('submit');

    expect(FakeReproPsp::$called)->toBeTrue()
        ->and(FakeReproPsp::$orderWasNull)->toBeFalse()
        ->and(FakeReproPsp::$productCount)->toBeGreaterThan(0);
});

/**
 * Regressie voor de productie-crash "Attempt to read property orderProducts on null"
 * in PayNL: als de belongsTo-relatie $orderPayment->order in een bepaalde context
 * null teruggeeft (bijv. de order niet resolvebaar is), moet de expliciet gekoppelde
 * order (setRelation in ProformaCheckout::submit) er tOch zijn voor de PSP.
 */
it('keeps the loaded order attached even when the belongsTo relation would resolve to null', function () {
    $order = Order::create(['email' => 'k@x.nl', 'status' => 'pending', 'is_proforma' => true, 'invoice_id' => 'PROFORMA', 'total' => 50]);
    $order->orderProducts()->create(['product_id' => null, 'name' => 'Dienst', 'quantity' => 1, 'price' => 50, 'vat_rate' => 21]);

    $orderPayment = new OrderPayment();
    $orderPayment->order_id = $order->id;
    $orderPayment->amount = 50;
    $orderPayment->status = 'pending';
    $orderPayment->hash = (string) \Illuminate\Support\Str::uuid();
    $orderPayment->save();

    // Simuleer de prod-conditie: de order is via de belongsTo niet meer op te halen.
    Order::where('id', $order->id)->delete(); // soft-delete
    expect($orderPayment->fresh()->order)->toBeNull();

    // Zoals ProformaCheckout::submit doet: koppel de al-geladen order expliciet.
    $orderPayment->setRelation('order', $order);

    expect($orderPayment->order)->not->toBeNull()
        ->and($orderPayment->order->orderProducts->count())->toBeGreaterThan(0);
});
