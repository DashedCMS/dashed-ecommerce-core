<?php

use App\Models\User as AppUser;
use Dashed\DashedCore\Classes\Sites;
use Dashed\DashedEcommerceCore\Models\POSCart;
use Dashed\DashedEcommerceCore\Models\DiscountCode;
use Dashed\DashedEcommerceCore\Classes\ConceptOrderService;

uses(\Illuminate\Foundation\Testing\RefreshDatabase::class);

it('carries a POS discount into the concept order total and discount', function () {
    $posCart = POSCart::create([
        'identifier' => 'concept-disc-1',
        'user_id' => AppUser::factory()->create()->id,
        'products' => [[
            'id' => null,
            'name' => 'Maatwerk',
            'price' => 100.0,
            'vat_rate' => 21,
            'quantity' => 1,
            'extra' => [],
            'customProduct' => true,
            'identifier' => 'line-1',
        ]],
    ]);

    DiscountCode::create([
        'site_ids' => [Sites::getActive()],
        'name' => 'Test',
        'code' => 'ZOMER10',
        'type' => 'amount',
        'discount_amount' => 10.0,
        'use_stock' => 0,
        'is_giftcard' => 0,
        'is_global_discount' => false,
    ]);

    expect($posCart->applyDiscountCode('ZOMER10')['success'])->toBeTrue();

    $order = ConceptOrderService::saveAsConcept($posCart->fresh(), AppUser::factory()->create());

    // subtotal is het product-totaal NA korting (zelfde semantiek als een
    // definitieve POS-order); total = subtotal; discount = het kortingsbedrag.
    expect((float) $order->discount)->toBe(10.0)
        ->and((float) $order->total)->toBe(90.0)
        ->and((float) $order->subtotal)->toBe(90.0);
});

it('keeps total equal to subtotal when there is no discount', function () {
    $posCart = POSCart::create([
        'identifier' => 'concept-nodisc-1',
        'user_id' => AppUser::factory()->create()->id,
        'products' => [[
            'id' => null,
            'name' => 'Maatwerk',
            'price' => 50.0,
            'vat_rate' => 21,
            'quantity' => 1,
            'extra' => [],
            'customProduct' => true,
            'identifier' => 'line-1',
        ]],
    ]);

    $order = ConceptOrderService::saveAsConcept($posCart->fresh(), AppUser::factory()->create());

    expect((float) $order->discount)->toBe(0.0)
        ->and((float) $order->total)->toBe(50.0);
});
