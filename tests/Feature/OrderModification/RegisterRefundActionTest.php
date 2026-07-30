<?php

use Filament\Forms\Components\TextInput;
use Dashed\DashedEcommerceCore\Models\Order;
use Dashed\DashedEcommerceCore\Filament\Resources\OrderResource\Actions\RegisterRefundAction;

uses(\Illuminate\Foundation\Testing\RefreshDatabase::class);

function overpaidOrder(): Order
{
    $order = Order::create(['email' => 'a@b.nl', 'status' => 'paid', 'total' => 80]);
    $order->orderPayments()->create(['status' => 'paid', 'amount' => 100, 'psp' => 'own']);

    return $order->fresh();
}

it('boekt een terugstorting als negatieve betaling', function () {
    $order = overpaidOrder();

    (new RegisterRefundAction())->handle($order, ['amount' => 20]);

    $order = $order->fresh();

    expect(round($order->overpaidAmount(), 2))->toBe(0.0)
        ->and(round((float) $order->orderPayments()->where('status', 'paid')->sum('amount'), 2))->toBe(80.0)
        ->and($order->orderPayments()->where('payment_method', 'refund')->count())->toBe(1);
});

it('weigert een bedrag boven het teveel betaalde', function () {
    $order = overpaidOrder();

    expect(fn () => (new RegisterRefundAction())->handle($order, ['amount' => 25]))
        ->toThrow(InvalidArgumentException::class);
});

it('weigert een bedrag van nul of lager', function () {
    $order = overpaidOrder();

    expect(fn () => (new RegisterRefundAction())->handle($order, ['amount' => 0]))
        ->toThrow(InvalidArgumentException::class);
});

/**
 * De make()-helft had geen enkele toets. Een typefout (200 waar 20 te veel
 * betaald is) kwam als onbehandelde InvalidArgumentException naar buiten op een
 * geldscherm; het formulier had alleen numeric() en required(). Deze toets legt
 * de grenzen op het veld zelf vast, zodat Filament er een nette
 * validatiemelding van maakt voordat handle() eraan te pas komt.
 */
function refundAmountField(Order $order): TextInput
{
    $action = RegisterRefundAction::make($order);

    // De componenten staan als platte array in de protected $schema van de
    // HasSchema-trait; zonder Livewire-container is er geen andere weg naar
    // binnen dan reflectie.
    $property = new ReflectionProperty($action, 'schema');
    $property->setAccessible(true);

    $amountField = collect($property->getValue($action))
        ->first(fn ($component) => $component instanceof TextInput && $component->getName() === 'amount');

    expect($amountField)->not->toBeNull();

    return $amountField;
}

it('begrenst het terugstortbedrag op het formulier tot het teveel betaalde', function () {
    $order = overpaidOrder(); // 100 betaald op een order van 80 => 20 te veel

    $field = refundAmountField($order);

    expect((float) $field->getMaxValue())->toBe(20.0);
});

it('staat op het formulier geen bedrag van nul of lager toe', function () {
    $order = overpaidOrder();

    $field = refundAmountField($order);

    expect((float) $field->getMinValue())->toBe(0.01);
});

it('is niet zichtbaar wanneer er niets te veel betaald is', function () {
    $order = Order::create(['email' => 'a@b.nl', 'status' => 'paid', 'total' => 100]);
    $order->orderPayments()->create(['status' => 'paid', 'amount' => 100, 'psp' => 'own']);

    expect(RegisterRefundAction::make($order->fresh())->isVisible())->toBeFalse();
});
