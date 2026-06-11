<?php

use Livewire\Livewire;
use Illuminate\Support\Facades\Mail;
use Dashed\DashedEcommerceCore\Models\Order;
use Dashed\DashedEcommerceCore\Models\OrderProduct;
use Dashed\DashedEcommerceCore\Models\OrderReturn;
use Dashed\DashedEcommerceCore\Models\ReturnReason;
use Dashed\DashedEcommerceCore\Models\OrderReturnLine;
use Dashed\DashedEcommerceCore\Livewire\Frontend\OrderWithdrawal;

function makeOrderWithProductsT4(): array
{
    $order = Order::create(['email' => 'klant@example.com', 'status' => 'paid', 'invoice_id' => 'INV-1001']);
    $p1 = OrderProduct::create(['order_id' => $order->id, 'name' => 'Shirt', 'quantity' => 3, 'price' => 20]);
    $p2 = OrderProduct::create(['order_id' => $order->id, 'name' => 'Broek', 'quantity' => 1, 'price' => 40]);

    return [$order, $p1, $p2];
}

it('creates return lines for selected products with quantity and reason', function () {
    Mail::fake();
    [$order, $p1, $p2] = makeOrderWithProductsT4();
    $reason = ReturnReason::create(['label' => ['nl' => 'Te klein'], 'is_active' => true]);

    Livewire::test(OrderWithdrawal::class)
        ->set('orderNumber', 'INV-1001')
        ->set('email', 'klant@example.com')
        ->call('search')
        ->set("selectedLines.{$p1->id}.selected", true)
        ->set("selectedLines.{$p1->id}.quantity", 2)
        ->set("selectedLines.{$p1->id}.reason_id", $reason->id)
        ->set("selectedLines.{$p1->id}.note", 'Valt klein uit')
        ->call('confirm')
        ->assertSet('completed', true);

    $return = OrderReturn::first();
    expect($return->lines)->toHaveCount(1);
    $line = $return->lines->first();
    expect($line->order_product_id)->toBe($p1->id)
        ->and($line->quantity)->toBe(2)
        ->and($line->return_reason_id)->toBe($reason->id)
        ->and($line->reason_note)->toBe('Valt klein uit');
});

it('refuses to confirm when no line is selected', function () {
    Mail::fake();
    makeOrderWithProductsT4();

    Livewire::test(OrderWithdrawal::class)
        ->set('orderNumber', 'INV-1001')
        ->set('email', 'klant@example.com')
        ->call('search')
        ->call('confirm')
        ->assertSet('completed', false)
        ->assertHasErrors('lines');

    expect(OrderReturn::count())->toBe(0);
});

it('refuses a quantity higher than ordered', function () {
    Mail::fake();
    [$order, $p1] = makeOrderWithProductsT4();

    Livewire::test(OrderWithdrawal::class)
        ->set('orderNumber', 'INV-1001')
        ->set('email', 'klant@example.com')
        ->call('search')
        ->set("selectedLines.{$p1->id}.selected", true)
        ->set("selectedLines.{$p1->id}.quantity", 99)
        ->call('confirm')
        ->assertSet('completed', false);

    expect(OrderReturn::count())->toBe(0);
});

it('persists the reason when reason_id arrives as a string (browser select)', function () {
    Mail::fake();
    [$order, $p1] = makeOrderWithProductsT4();
    $reason = ReturnReason::create(['label' => ['nl' => 'Te klein'], 'is_active' => true]);

    Livewire::test(OrderWithdrawal::class)
        ->set('orderNumber', 'INV-1001')
        ->set('email', 'klant@example.com')
        ->call('search')
        ->set("selectedLines.{$p1->id}.selected", true)
        ->set("selectedLines.{$p1->id}.quantity", 1)
        ->set("selectedLines.{$p1->id}.reason_id", (string) $reason->id)
        ->call('confirm')
        ->assertSet('completed', true);

    expect(OrderReturnLine::first()->return_reason_id)->toBe($reason->id);
});

it('stores null reason_id when the reason is inactive but keeps the note', function () {
    Mail::fake();
    [$order, $p1] = makeOrderWithProductsT4();
    $inactive = ReturnReason::create(['label' => ['nl' => 'Oud'], 'is_active' => false]);

    Livewire::test(OrderWithdrawal::class)
        ->set('orderNumber', 'INV-1001')
        ->set('email', 'klant@example.com')
        ->call('search')
        ->set("selectedLines.{$p1->id}.selected", true)
        ->set("selectedLines.{$p1->id}.quantity", 1)
        ->set("selectedLines.{$p1->id}.reason_id", $inactive->id)
        ->set("selectedLines.{$p1->id}.note", 'Toelichting')
        ->call('confirm')
        ->assertSet('completed', true);

    $line = OrderReturnLine::first();
    expect($line->return_reason_id)->toBeNull()
        ->and($line->reason_note)->toBe('Toelichting');
});
