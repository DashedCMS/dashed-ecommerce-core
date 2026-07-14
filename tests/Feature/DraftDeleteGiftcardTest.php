<?php

use App\Models\User;
use Dashed\DashedCore\Classes\Sites;
use Dashed\DashedEcommerceCore\Models\Order;
use Dashed\DashedEcommerceCore\Models\DiscountCode;
use Dashed\DashedEcommerceCore\Classes\ConceptOrderService;

uses(\Illuminate\Foundation\Testing\RefreshDatabase::class);

beforeEach(function () {
    $this->actingAs(User::factory()->create());
});

function giftcardForTest(float $balance = 50.0): DiscountCode
{
    return DiscountCode::create([
        'site_ids' => [Sites::getActive()],
        'name' => 'Cadeaubon',
        'code' => 'GC-' . strtoupper(uniqid()),
        'is_giftcard' => 1,
        'discount_amount' => $balance,
        'use_stock' => 0,
    ]);
}

function draftWithGiftcard(DiscountCode $gc, float $discount, string $status = 'concept', bool $proforma = false): Order
{
    $order = new Order();
    $order->email = 'a@b.nl';
    $order->status = $status;
    $order->is_proforma = $proforma;
    $order->discount_code_id = $gc->id;
    $order->discount = $discount;
    $order->save(); // fires Order::created -> reserves the giftcard balance

    return $order;
}

it('reserves the giftcard balance when the draft is created (setup sanity)', function () {
    $gc = giftcardForTest(50.0);
    draftWithGiftcard($gc, 20.0);

    expect((float) $gc->fresh()->reserved_amount)->toBe(20.0)
        ->and((float) $gc->fresh()->discount_amount)->toBe(30.0);
});

it('refills the reserved giftcard balance when a draft is deleted', function () {
    $gc = giftcardForTest(50.0);
    $order = draftWithGiftcard($gc, 20.0);

    ConceptOrderService::deleteDraft($order);

    expect((float) $gc->fresh()->reserved_amount)->toBe(0.0)
        ->and((float) $gc->fresh()->discount_amount)->toBe(50.0);
});

it('does not double-refill a giftcard on an already-cancelled draft', function () {
    $gc = giftcardForTest(50.0);
    $order = draftWithGiftcard($gc, 20.0, status: 'pending', proforma: true);

    // Simuleer een eerdere annulering: saldo is al teruggeboekt, status cancelled.
    $order->refillGiftcard();
    $order->status = 'cancelled';
    $order->save();

    expect((float) $gc->fresh()->reserved_amount)->toBe(0.0)
        ->and((float) $gc->fresh()->discount_amount)->toBe(50.0);

    ConceptOrderService::deleteDraft($order);

    // Geen dubbele terugboeking: saldo blijft 50, reserved blijft 0.
    expect((float) $gc->fresh()->reserved_amount)->toBe(0.0)
        ->and((float) $gc->fresh()->discount_amount)->toBe(50.0);
});
