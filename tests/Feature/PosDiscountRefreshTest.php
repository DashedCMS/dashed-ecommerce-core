<?php

declare(strict_types=1);

use Dashed\DashedCore\Models\User;
use Dashed\DashedCore\Classes\Sites;
use Dashed\DashedEcommerceCore\Models\POSCart;
use Dashed\DashedEcommerceCore\Models\DiscountCode;
use Dashed\DashedEcommerceCore\Controllers\Api\PointOfSale\PointOfSaleApiController;

/**
 * Borgt het server-contract waar de POS-front-end op leunt: updateCart is een
 * pure refresh. Zonder expliciete kortingscode mag het NIETS (her)toepassen.
 * Anders zou het verwijderen van een kortingscode in de kassa direct ongedaan
 * gemaakt worden zodra de mand ververst (de oorspronkelijke bug).
 */
function makePosCartWithProduct(string $identifier): POSCart
{
    return POSCart::create([
        'identifier' => $identifier,
        'user_id' => User::factory()->create()->id,
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
}

function makeUsableDiscountCode(string $code): DiscountCode
{
    return DiscountCode::create([
        'site_ids' => [Sites::getActive()],
        'name' => 'Test',
        'code' => $code,
        'type' => 'amount',
        'discount_amount' => 10.0,
        'use_stock' => 0,
        'is_giftcard' => 0,
        'is_global_discount' => false,
    ]);
}

it('does not re-apply a removed discount code on a plain cart refresh', function () {
    $posCart = makePosCartWithProduct('pos-refresh-1');
    makeUsableDiscountCode('ZOMER10');

    $result = $posCart->applyDiscountCode('ZOMER10');
    expect($result['success'])->toBeTrue();
    expect($posCart->fresh()->applied_discount_codes)->toHaveCount(1);

    $controller = new PointOfSaleApiController();

    // Verversen zonder code: de code blijft staan, niet gedupliceerd.
    $controller->updateCart('', 'pos-refresh-1', null);
    expect($posCart->fresh()->applied_discount_codes)->toHaveCount(1);

    // Klant/kassa verwijdert de code.
    expect($posCart->fresh()->removeDiscountCode('ZOMER10'))->toBeTrue();
    expect($posCart->fresh()->applied_discount_codes)->toHaveCount(0);

    // Nog een refresh zonder code: de verwijderde code komt NIET terug.
    $controller->updateCart('', 'pos-refresh-1', null);
    expect($posCart->fresh()->applied_discount_codes)->toHaveCount(0);
});

it('applies a discount code when one is passed explicitly (scan-as-code)', function () {
    $posCart = makePosCartWithProduct('pos-refresh-2');
    makeUsableDiscountCode('SCAN5');

    $controller = new PointOfSaleApiController();
    $controller->updateCart('', 'pos-refresh-2', 'SCAN5');

    $applied = $posCart->fresh()->applied_discount_codes;
    expect($applied)->toHaveCount(1)
        ->and($applied[0]['code'])->toBe('SCAN5');
});
