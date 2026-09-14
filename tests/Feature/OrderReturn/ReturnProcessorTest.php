<?php

use Dashed\DashedCore\Models\User;
use Dashed\DashedCore\Classes\Sites;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Queue;
use Dashed\DashedEcommerceCore\Models\Order;
use Dashed\DashedEcommerceCore\Models\DiscountCode;
use Dashed\DashedEcommerceCore\Models\OrderLog;
use Dashed\DashedEcommerceCore\Models\OrderReturn;
use Dashed\DashedEcommerceCore\Models\OrderProduct;
use Dashed\DashedEcommerceCore\Models\OrderReturnLine;
use Dashed\DashedEcommerceCore\Mail\OrderCancelledWithCreditMail;
use Dashed\DashedEcommerceCore\Services\OrderReturn\ReturnProcessor;
use Dashed\DashedEcommerceCore\Services\OrderReturn\ReturnableLines;
use Dashed\DashedEcommerceCore\Mail\OrderReturn\OrderReturnProcessedMail;

beforeEach(function () {
    Mail::fake();
    Queue::fake();
    $this->actingAs(User::factory()->create(['role' => 'superadmin']));
});

/** Goedgekeurde retour op spilOrder(): Shirt 2 van 3, Broek 1 van 1. */
function processorReturn(array $orderAttrs = []): array
{
    $fixture = spilOrder($orderAttrs);
    $return = OrderReturn::create(['order_id' => $fixture['order']->id, 'email' => 'klant@example.com', 'status' => OrderReturn::STATUS_APPROVED, 'approved_at' => now()]);
    $shirtLine = OrderReturnLine::create(['order_return_id' => $return->id, 'order_product_id' => $fixture['shirt']->id, 'quantity' => 2]);
    $broekLine = OrderReturnLine::create(['order_return_id' => $return->id, 'order_product_id' => $fixture['broek']->id, 'quantity' => 1]);

    return $fixture + ['return' => $return->fresh(), 'shirtLine' => $shirtLine, 'broekLine' => $broekLine];
}

/** Een cadeaubon met saldo, eigen helper zodat hij niet met ledgerGiftcard() botst. */
function processorGiftcardCode(float $balance = 100.0): DiscountCode
{
    return DiscountCode::create([
        'site_ids' => [Sites::getActive()],
        'name' => 'Cadeaubon',
        'code' => 'GC-PROC-' . strtoupper(uniqid()),
        'is_giftcard' => 1,
        'discount_amount' => $balance,
        'use_stock' => 0,
    ]);
}

/** Een tweede goedgekeurde retour op het Shirt van dezelfde bestelling. */
function processorSecondReturn(array $f, int $quantity): array
{
    $return = OrderReturn::create(['order_id' => $f['order']->id, 'email' => 'klant@example.com', 'status' => OrderReturn::STATUS_APPROVED, 'approved_at' => now()]);
    $line = OrderReturnLine::create(['order_return_id' => $return->id, 'order_product_id' => $f['shirt']->id, 'quantity' => $quantity]);

    return ['return' => $return->fresh(), 'line' => $line];
}

it('maakt een creditorder met de verwerkte aantallen en koppelt hem aan de retour', function () {
    $f = processorReturn();

    $credit = app(ReturnProcessor::class)->process($f['return'], [
        ['order_return_line_id' => $f['shirtLine']->id, 'quantity' => 2],
        ['order_return_line_id' => $f['broekLine']->id, 'quantity' => 0],
    ], ['restock' => false, 'note' => 'Broek beschadigd, niet gecrediteerd']);

    $return = $f['return']->fresh();
    expect($credit->credit_for_order_id)->toBe($f['order']->id)
        ->and($credit->status)->toBe('return')
        ->and(round(abs((float) $credit->total), 2))->toBe(40.0)
        ->and(round(abs((float) $credit->btw), 2))->toBe(6.94)
        ->and($credit->orderProducts()->count())->toBe(1)
        ->and($credit->orderProducts()->first()->quantity)->toBe(-2)
        ->and($credit->retour_status)->toBe('handled')
        ->and($return->status)->toBe(OrderReturn::STATUS_HANDLED)
        ->and($return->credit_order_id)->toBe($credit->id)
        ->and($return->processed_at)->not->toBeNull()
        ->and($return->handled_at)->not->toBeNull()
        ->and($return->admin_note)->toContain('Broek beschadigd')
        ->and($f['shirtLine']->fresh()->processed_quantity)->toBe(2)
        ->and($f['broekLine']->fresh()->processed_quantity)->toBe(0)
        ->and($f['shirt']->fresh()->returned_quantity)->toBe(2)
        ->and($f['broek']->fresh()->returned_quantity)->toBe(0)
        ->and($f['order']->fresh()->retour_status)->toBe('handled')
        ->and($f['order']->fresh()->status)->toBe('paid')
        ->and(OrderLog::where('order_id', $f['order']->id)->where('tag', 'order.return-processed')->exists())->toBeTrue()
        ->and(ReturnableLines::remaining($f['shirt']->fresh()))->toBe(1);

    Mail::assertQueued(OrderReturnProcessedMail::class, fn ($m) => $m->orderReturn->is($return) && $m->hasTo('klant@example.com'));
    Mail::assertNotSent(OrderCancelledWithCreditMail::class);
    Mail::assertNotQueued(OrderCancelledWithCreditMail::class);
});

it('crediteert een volledige retour inclusief korting als daarom gevraagd wordt', function () {
    $f = processorReturn(['discount' => 10, 'total' => 90]);
    // Alle drie de shirts terug, anders is het geen volledige retour en weigert
    // de processor het verrekenen van de korting.
    $f['shirtLine']->update(['quantity' => 3]);

    $credit = app(ReturnProcessor::class)->process($f['return'], [
        ['order_return_line_id' => $f['shirtLine']->id, 'quantity' => 3],
        ['order_return_line_id' => $f['broekLine']->id, 'quantity' => 1],
    ], ['restock' => false, 'refund_discount' => true]);

    // Zonder korting: 60 + 40 = 100 credit. De regel-korting op shirt en broek
    // staat op null (0), dus $discountToGet in markAsCancelledWithCredit
    // blijft op de volle order-korting (10) staan. Bij refund_discount telt
    // de methode dat bedrag bij een NEGATIEF totaal op (100 - 10 = 90 was al
    // betaald), dus het credit wordt kleiner, niet groter: 100 - 10 = 90.
    expect(round(abs((float) $credit->total), 2))->toBe(90.0)
        ->and($credit->orderProducts()->count())->toBe(2);
});

it('weigert korting verrekenen bij een deelretour, zonder creditorder', function () {
    $f = processorReturn(['discount' => 10, 'total' => 90]);
    $processor = app(ReturnProcessor::class);

    // Shirt 2 van 3 en de broek erbij: er blijft één shirt over, dus de hele
    // orderkorting van het credit aftrekken mag hier niet.
    expect(fn () => $processor->process($f['return'], [
        ['order_return_line_id' => $f['shirtLine']->id, 'quantity' => 2],
        ['order_return_line_id' => $f['broekLine']->id, 'quantity' => 1],
    ], ['restock' => false, 'refund_discount' => true]))
        ->toThrow(InvalidArgumentException::class, 'Korting verrekenen kan alleen bij een volledige retour');

    // En ook niet als de broek helemaal buiten de retour blijft.
    expect(fn () => $processor->process($f['return'], [
        ['order_return_line_id' => $f['shirtLine']->id, 'quantity' => 2],
        ['order_return_line_id' => $f['broekLine']->id, 'quantity' => 0],
    ], ['restock' => false, 'refund_discount' => true]))
        ->toThrow(InvalidArgumentException::class);

    expect(Order::where('credit_for_order_id', $f['order']->id)->count())->toBe(0)
        ->and($f['return']->fresh()->status)->toBe(OrderReturn::STATUS_APPROVED)
        ->and($f['shirt']->fresh()->returned_quantity)->toBe(0);
});

it('stort een cadeaubon niet terug, ook niet bij twee deelretouren', function () {
    $giftcard = processorGiftcardCode(100.0);
    $f = processorReturn(['discount' => 10, 'total' => 90, 'discount_code_id' => $giftcard->id]);
    // Wat markAsPaid() gedaan zou hebben: de reservering wordt een verbruik.
    $f['order']->deductDiscount();

    $saldo = round((float) $giftcard->fresh()->discount_amount, 2);

    app(ReturnProcessor::class)->process($f['return'], [
        ['order_return_line_id' => $f['shirtLine']->id, 'quantity' => 2],
    ], ['restock' => false]);

    expect(round((float) $giftcard->fresh()->discount_amount, 2))->toBe($saldo);

    $tweede = processorSecondReturn($f, 1);
    app(ReturnProcessor::class)->process($tweede['return'], [
        ['order_return_line_id' => $tweede['line']->id, 'quantity' => 1],
    ], ['restock' => false]);

    // Met refillGiftcard: true was het saldo er nu twee keer de volle
    // orderkorting bij gekregen, bij elke retour opnieuw.
    expect(round((float) $giftcard->fresh()->discount_amount, 2))->toBe($saldo)
        ->and(round((float) $giftcard->fresh()->used_amount, 2))->toBe(10.0);
});

it('telt twee retourregels op hetzelfde orderproduct bij elkaar op tegen het restant', function () {
    $f = processorReturn();
    $tweedeRegel = OrderReturnLine::create(['order_return_id' => $f['return']->id, 'order_product_id' => $f['shirt']->id, 'quantity' => 2]);

    // 2 + 2 op een shirt met restant 3 kan niet.
    expect(fn () => app(ReturnProcessor::class)->process($f['return'], [
        ['order_return_line_id' => $f['shirtLine']->id, 'quantity' => 2],
        ['order_return_line_id' => $tweedeRegel->id, 'quantity' => 2],
    ], ['restock' => false]))->toThrow(InvalidArgumentException::class);

    expect(Order::where('credit_for_order_id', $f['order']->id)->count())->toBe(0);

    // 1 + 2 past precies in het restant en wordt per regel vastgelegd.
    app(ReturnProcessor::class)->process($f['return'], [
        ['order_return_line_id' => $f['shirtLine']->id, 'quantity' => 1],
        ['order_return_line_id' => $tweedeRegel->id, 'quantity' => 2],
    ], ['restock' => false]);

    expect($f['shirtLine']->fresh()->processed_quantity)->toBe(1)
        ->and($tweedeRegel->fresh()->processed_quantity)->toBe(2)
        ->and($f['shirt']->fresh()->returned_quantity)->toBe(3);
});

it('boekt de voorraad terug als restock aan staat, en niet als hij uit staat', function () {
    $group = makeChatTestProductGroup('Retourgroep ' . uniqid(), 'retourgroep-' . uniqid());
    $product = makeMobileProduct(['name' => ['nl' => 'Shirt'], 'use_stock' => true, 'stock' => 5, 'product_group_id' => $group->id]);

    foreach ([true => 7, false => 5] as $restock => $verwacht) {
        $f = processorReturn();
        $f['shirt']->update(['product_id' => $product->id]);
        $product->refresh();
        $product->update(['stock' => 5]);

        app(ReturnProcessor::class)->process($f['return'], [['order_return_line_id' => $f['shirtLine']->id, 'quantity' => 2]], ['restock' => (bool) $restock]);

        expect((int) $product->fresh()->stock)->toBe($verwacht);
    }
});

it('weigert nul regels, een verkeerde status, een vreemde regel en een te hoog aantal, zonder creditorder', function () {
    $f = processorReturn();
    $processor = app(ReturnProcessor::class);
    $ander = OrderReturn::create(['order_id' => $f['order']->id, 'email' => 'x@y.nl', 'status' => OrderReturn::STATUS_REJECTED]);
    $vreemd = OrderReturnLine::create(['order_return_id' => $ander->id, 'order_product_id' => $f['shirt']->id, 'quantity' => 1]);

    expect(fn () => $processor->process($f['return'], [['order_return_line_id' => $f['shirtLine']->id, 'quantity' => 0]]))->toThrow(InvalidArgumentException::class);
    expect(fn () => $processor->process($f['return'], [['order_return_line_id' => $vreemd->id, 'quantity' => 1]]))->toThrow(InvalidArgumentException::class);
    expect(fn () => $processor->process($f['return'], [['order_return_line_id' => $f['shirtLine']->id, 'quantity' => 4]]))->toThrow(InvalidArgumentException::class);
    expect(fn () => $processor->process($ander, [['order_return_line_id' => $vreemd->id, 'quantity' => 1]]))->toThrow(InvalidArgumentException::class);

    expect(Order::where('credit_for_order_id', $f['order']->id)->count())->toBe(0)
        ->and($f['return']->fresh()->status)->toBe(OrderReturn::STATUS_APPROVED);
    Mail::assertNothingQueued();
});

it('laat een tweede retour alleen het restant verwerken', function () {
    $f = processorReturn();
    app(ReturnProcessor::class)->process($f['return'], [['order_return_line_id' => $f['shirtLine']->id, 'quantity' => 2]], ['restock' => false]);

    $tweede = OrderReturn::create(['order_id' => $f['order']->id, 'email' => 'klant@example.com', 'status' => OrderReturn::STATUS_APPROVED]);
    $lijn = OrderReturnLine::create(['order_return_id' => $tweede->id, 'order_product_id' => $f['shirt']->id, 'quantity' => 1]);

    expect(fn () => app(ReturnProcessor::class)->process($tweede, [['order_return_line_id' => $lijn->id, 'quantity' => 2]]))->toThrow(InvalidArgumentException::class);

    $credit = app(ReturnProcessor::class)->process($tweede, [['order_return_line_id' => $lijn->id, 'quantity' => 1]], ['restock' => false]);
    expect(round(abs((float) $credit->total), 2))->toBe(20.0)
        ->and($f['shirt']->fresh()->returned_quantity)->toBe(3);
});

it('mailt een Bol-klant niet maar verwerkt wel', function () {
    $f = processorReturn(['order_origin' => 'Bol']);

    $credit = app(ReturnProcessor::class)->process($f['return'], [['order_return_line_id' => $f['shirtLine']->id, 'quantity' => 2]], ['restock' => false]);

    expect($credit->exists)->toBeTrue()
        ->and(OrderLog::where('order_id', $f['order']->id)->where('tag', 'order.return-mail-skipped-bol')->exists())->toBeTrue();
    Mail::assertNotQueued(OrderReturnProcessedMail::class);
});
