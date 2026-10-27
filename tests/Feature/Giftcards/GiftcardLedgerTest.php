<?php

declare(strict_types=1);

use Dashed\DashedCore\Classes\Sites;
use Dashed\DashedEcommerceCore\Models\Order;
use Dashed\DashedEcommerceCore\Models\DiscountCode;
use Dashed\DashedEcommerceCore\Services\Giftcards\GiftcardLedger;

/**
 * Het saldo van een verkochte cadeaubon loopt via reserveren (order
 * aangemaakt), afboeken (order betaald) of terugzetten (order geannuleerd),
 * en bij een retour terug op de bon. Elke stap mag vaker draaien zonder dat
 * het saldo verschuift.
 */
function grootboekBon(float $saldo): DiscountCode
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

function grootboekOrder(float $total = 100): Order
{
    return Order::create(['email' => 'klant@example.com', 'status' => 'pending', 'total' => $total]);
}

function grootboekTellers(DiscountCode $code): array
{
    $code = $code->fresh();

    return [
        'saldo' => round((float) $code->discount_amount, 2),
        'gereserveerd' => round((float) $code->reserved_amount, 2),
        'gebruikt' => round((float) $code->used_amount, 2),
    ];
}

function grootboekInwisseling(DiscountCode $code, float $bedrag): array
{
    return ['code' => $code->code, 'discount_code_id' => $code->id, 'redeemed' => $bedrag];
}

it('reserveert nooit meer dan het saldo', function () {
    $bon = grootboekBon(20);
    $order = grootboekOrder();

    $reserved = GiftcardLedger::reserve($order, [grootboekInwisseling($bon, 30)]);

    expect($reserved)->toHaveCount(1)
        ->and($reserved[0]['redeemed'])->toBe(20.0)
        ->and(grootboekTellers($bon))->toBe(['saldo' => 0.0, 'gereserveerd' => 20.0, 'gebruikt' => 0.0])
        ->and($order->fresh()->applied_gift_cards)->toEqual([[
            'code' => $bon->code,
            'discount_code_id' => $bon->id,
            'redeemed' => 20.0,
            'status' => 'reserved',
            'refunded' => 0.0,
        ]])
        ->and($bon->logs()->where('tag', 'giftcard.payment.reserved')->count())->toBe(1);
});

it('boekt een reservering één keer af', function () {
    $bon = grootboekBon(50);
    $order = grootboekOrder();
    GiftcardLedger::reserve($order, [grootboekInwisseling($bon, 30)]);

    GiftcardLedger::commit($order);
    GiftcardLedger::commit($order->fresh());

    expect(grootboekTellers($bon))->toBe(['saldo' => 20.0, 'gereserveerd' => 0.0, 'gebruikt' => 30.0])
        ->and($order->fresh()->applied_gift_cards[0]['status'])->toBe('used')
        ->and($bon->logs()->where('tag', 'giftcard.payment.completed')->count())->toBe(1);
});

it('zet een reservering één keer terug op de bon', function () {
    $bon = grootboekBon(50);
    $order = grootboekOrder();
    GiftcardLedger::reserve($order, [grootboekInwisseling($bon, 30)]);

    GiftcardLedger::release($order);
    GiftcardLedger::release($order->fresh());

    expect(grootboekTellers($bon))->toBe(['saldo' => 50.0, 'gereserveerd' => 0.0, 'gebruikt' => 0.0])
        ->and($order->fresh()->applied_gift_cards[0]['status'])->toBe('released')
        ->and($bon->logs()->where('tag', 'giftcard.payment.released')->count())->toBe(1);
});

it('zet na afboeken niets meer terug bij vrijgeven', function () {
    $bon = grootboekBon(50);
    $order = grootboekOrder();
    GiftcardLedger::reserve($order, [grootboekInwisseling($bon, 30)]);
    GiftcardLedger::commit($order);

    GiftcardLedger::release($order->fresh());

    expect(grootboekTellers($bon))->toBe(['saldo' => 20.0, 'gereserveerd' => 0.0, 'gebruikt' => 30.0])
        ->and($order->fresh()->applied_gift_cards[0]['status'])->toBe('used');
});

it('zet bij een retour nooit meer terug dan er van de bon betaald is', function () {
    $bon = grootboekBon(40);
    $order = grootboekOrder();
    GiftcardLedger::reserve($order, [grootboekInwisseling($bon, 40)]);
    GiftcardLedger::commit($order);

    $eerste = GiftcardLedger::refund($order->fresh(), 30);
    $tweede = GiftcardLedger::refund($order->fresh(), 30);

    expect($eerste)->toBe(30.0)
        ->and($tweede)->toBe(10.0)
        ->and(grootboekTellers($bon))->toBe(['saldo' => 40.0, 'gereserveerd' => 0.0, 'gebruikt' => 0.0])
        ->and((float) $order->fresh()->applied_gift_cards[0]['refunded'])->toBe(40.0)
        ->and(GiftcardLedger::refund($order->fresh(), 5))->toBe(0.0);
});

it('zet een retour eerst op de laatst gebruikte bon terug', function () {
    $eerste = grootboekBon(20);
    $tweede = grootboekBon(20);
    $order = grootboekOrder();
    GiftcardLedger::reserve($order, [grootboekInwisseling($eerste, 20), grootboekInwisseling($tweede, 15)]);
    GiftcardLedger::commit($order);

    expect(GiftcardLedger::refund($order->fresh(), 25))->toBe(25.0)
        ->and(grootboekTellers($tweede)['saldo'])->toBe(20.0)
        ->and(grootboekTellers($eerste)['saldo'])->toBe(10.0);
});

it('laat twee bestellingen met dezelfde bon nooit samen meer dan het saldo gebruiken', function () {
    $bon = grootboekBon(60);

    $eerste = GiftcardLedger::reserve(grootboekOrder(), [grootboekInwisseling($bon, 50)]);
    $tweede = GiftcardLedger::reserve(grootboekOrder(), [grootboekInwisseling($bon, 50)]);

    expect($eerste[0]['redeemed'])->toBe(50.0)
        ->and($tweede[0]['redeemed'])->toBe(10.0)
        ->and(grootboekTellers($bon))->toBe(['saldo' => 0.0, 'gereserveerd' => 60.0, 'gebruikt' => 0.0]);
});

it('slaat een promo-bon en een bon zonder saldo over', function () {
    $promo = grootboekBon(50);
    $promo->forceFill(['giftcard_type' => 'promo']);
    DiscountCode::withoutEvents(fn () => $promo->save());
    $leeg = grootboekBon(0);
    $order = grootboekOrder();

    expect(GiftcardLedger::reserve($order, [grootboekInwisseling($promo, 10), grootboekInwisseling($leeg, 10)]))->toBe([])
        ->and(grootboekTellers($promo)['saldo'])->toBe(50.0);
});

it('laat de kassa-inwisselingen zonder status ongemoeid', function () {
    $bon = grootboekBon(50);
    $order = grootboekOrder();
    $order->applied_gift_cards = [['discount_code_id' => $bon->id, 'code' => $bon->code, 'redeemed' => 10]];
    $order->saveQuietly();

    GiftcardLedger::commit($order);
    GiftcardLedger::release($order);

    expect(grootboekTellers($bon))->toBe(['saldo' => 50.0, 'gereserveerd' => 0.0, 'gebruikt' => 0.0]);
});
