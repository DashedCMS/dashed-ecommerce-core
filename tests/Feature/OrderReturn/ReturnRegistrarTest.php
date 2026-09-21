<?php

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Event;
use Dashed\DashedEcommerceCore\Models\Order;
use Dashed\DashedEcommerceCore\Models\OrderLog;
use Dashed\DashedEcommerceCore\Models\OrderReturn;
use Dashed\DashedEcommerceCore\Models\OrderProduct;
use Dashed\DashedEcommerceCore\Models\ReturnReason;
use Dashed\DashedEcommerceCore\Services\OrderReturn\ReturnableLines;
use Dashed\DashedEcommerceCore\Services\OrderReturn\ReturnRegistrar;
use Dashed\DashedEcommerceCore\Events\Orders\OrderReturnApprovedEvent;
use Dashed\DashedEcommerceCore\Mail\OrderReturn\OrderReturnApprovedMail;

beforeEach(function () {
    Mail::fake();
    Event::fake([OrderReturnApprovedEvent::class]);
});

it('meldt een retour aan als goedgekeurd met regels en mailt de klant', function () {
    ['order' => $order, 'shirt' => $shirt] = spilOrder();
    $reason = ReturnReason::create(['label' => ['nl' => 'Te klein'], 'is_active' => true]);

    $return = app(ReturnRegistrar::class)->register($order, [
        ['order_product_id' => $shirt->id, 'quantity' => 2, 'return_reason_id' => $reason->id, 'reason_note' => 'Valt klein'],
    ], ['admin_note' => 'Gebeld met klant', 'notify_customer' => true]);

    expect($return->status)->toBe(OrderReturn::STATUS_APPROVED)
        ->and($return->approved_at)->not->toBeNull()
        ->and($return->auto_accepted)->toBeFalse()
        ->and($return->admin_note)->toBe('Gebeld met klant')
        ->and($return->email)->toBe('klant@example.com')
        ->and($return->site_id)->toBe($order->site_id)
        ->and($return->lines)->toHaveCount(1)
        ->and($return->lines->first()->quantity)->toBe(2)
        ->and($return->lines->first()->return_reason_id)->toBe($reason->id)
        ->and($order->fresh()->retour_status)->toBe('waiting_for_return')
        ->and(OrderLog::where('order_id', $order->id)->where('tag', 'order.return-registered-by-admin')->exists())->toBeTrue();

    Mail::assertQueued(OrderReturnApprovedMail::class, fn ($m) => $m->orderReturn->is($return) && $m->hasTo('klant@example.com'));
    Event::assertDispatched(OrderReturnApprovedEvent::class, fn ($e) => $e->orderReturn->is($return) && $e->notifyCustomer === true);
});

it('mailt en labelt niet als de klant niet geinformeerd hoeft te worden', function () {
    ['order' => $order, 'shirt' => $shirt] = spilOrder();

    $return = app(ReturnRegistrar::class)->register($order, [['order_product_id' => $shirt->id, 'quantity' => 1]], ['notify_customer' => false]);

    Mail::assertNothingQueued();
    Event::assertDispatched(OrderReturnApprovedEvent::class, fn ($e) => $e->orderReturn->is($return) && $e->notifyCustomer === false);
});

it('informeert een Bol-klant nooit, ook niet met de schakelaar aan', function () {
    ['order' => $order, 'shirt' => $shirt] = spilOrder(['order_origin' => 'Bol']);

    app(ReturnRegistrar::class)->register($order, [['order_product_id' => $shirt->id, 'quantity' => 1]], ['notify_customer' => true]);

    Mail::assertNothingQueued();
    Event::assertDispatched(OrderReturnApprovedEvent::class, fn ($e) => $e->notifyCustomer === false);
    expect(OrderLog::where('order_id', $order->id)->where('tag', 'order.return-mail-skipped-bol')->exists())->toBeTrue();
});

it('weigert een onbetaalde bestelling', function () {
    ['order' => $order, 'shirt' => $shirt] = spilOrder(['status' => 'pending']);

    expect(fn () => app(ReturnRegistrar::class)->register($order, [['order_product_id' => $shirt->id, 'quantity' => 1]]))
        ->toThrow(InvalidArgumentException::class);
    expect(OrderReturn::count())->toBe(0);
});

it('weigert als er al een open retour staat en noemt die', function () {
    ['order' => $order, 'shirt' => $shirt] = spilOrder();
    $open = OrderReturn::create(['order_id' => $order->id, 'email' => 'klant@example.com', 'status' => OrderReturn::STATUS_APPROVED]);

    expect(fn () => app(ReturnRegistrar::class)->register($order, [['order_product_id' => $shirt->id, 'quantity' => 1]]))
        ->toThrow(InvalidArgumentException::class, (string) $open->id);
});

it('weigert een aantal boven het restant, een vreemde regel, een lege lijst en een niet-retourneerbare SKU', function () {
    ['order' => $order, 'shirt' => $shirt] = spilOrder();
    $shirt->update(['returned_quantity' => 2]);
    $verzend = OrderProduct::withoutEvents(fn () => OrderProduct::create(['order_id' => $order->id, 'name' => 'Verzendkosten', 'quantity' => 1, 'price' => 5, 'sku' => 'shipping_costs']));
    $ander = Order::create(['email' => 'x@y.nl', 'status' => 'paid']);
    $vreemd = OrderProduct::withoutEvents(fn () => OrderProduct::create(['order_id' => $ander->id, 'name' => 'Vreemd', 'quantity' => 1, 'price' => 5]));

    $registrar = app(ReturnRegistrar::class);
    expect(fn () => $registrar->register($order, [['order_product_id' => $shirt->id, 'quantity' => 2]]))->toThrow(InvalidArgumentException::class);
    expect(fn () => $registrar->register($order, [['order_product_id' => $vreemd->id, 'quantity' => 1]]))->toThrow(InvalidArgumentException::class);
    expect(fn () => $registrar->register($order, []))->toThrow(InvalidArgumentException::class);
    expect(fn () => $registrar->register($order, [['order_product_id' => $verzend->id, 'quantity' => 1]]))->toThrow(InvalidArgumentException::class);
    expect(OrderReturn::count())->toBe(0);
});

it('rekent het restant per regel uit en laat verzendkosten weg', function () {
    ['order' => $order, 'shirt' => $shirt, 'broek' => $broek] = spilOrder();
    $shirt->update(['returned_quantity' => 3]);
    OrderProduct::withoutEvents(fn () => OrderProduct::create(['order_id' => $order->id, 'name' => 'Verzendkosten', 'quantity' => 1, 'price' => 5, 'sku' => 'shipping_costs']));

    expect(ReturnableLines::remaining($shirt->fresh()))->toBe(0)
        ->and(ReturnableLines::remaining($broek))->toBe(1)
        ->and(ReturnableLines::forOrder($order->fresh())->pluck('id')->all())->toBe([$broek->id]);
});

it('hergebruikt de al geladen orderregels en query niet opnieuw', function () {
    ['order' => $order] = spilOrder();

    $loaded = Order::with('orderProducts')->find($order->id);

    DB::enableQueryLog();
    $result = ReturnableLines::forOrder($loaded);
    $queries = DB::getQueryLog();
    DB::disableQueryLog();

    expect($queries)->toBeEmpty()
        ->and($result->pluck('name')->sort()->values()->all())->toBe(['Broek', 'Shirt']);
});
