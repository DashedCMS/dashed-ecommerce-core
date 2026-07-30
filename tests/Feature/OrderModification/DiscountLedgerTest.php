<?php

use Dashed\DashedCore\Models\User;
use Dashed\DashedCore\Classes\Sites;
use Dashed\DashedCore\Models\Customsetting;
use Dashed\DashedEcommerceCore\Models\Order;
use Dashed\DashedEcommerceCore\Models\DiscountCode;
use Dashed\DashedEcommerceCore\Models\OrderProduct;
use Dashed\DashedEcommerceCore\Classes\OrderModificationService;

uses(\Illuminate\Foundation\Testing\RefreshDatabase::class);

// DiscountCode::created logt een aangemaakte cadeaubon op auth()->user()->id.
beforeEach(function () {
    $this->actingAs(User::factory()->create(['role' => 'superadmin']));
});

/**
 * Een wijziging verplaatst de bestelling, hij verbruikt geen tweede korting.
 * De vervangende order erft discount_code_id en discount, dus alle tellers van
 * de kortingscode horen na afloop exact gelijk te zijn aan ervoor.
 *
 * Er lopen drie boekingen doorheen die elkaar moeten opheffen: de
 * created-hook op Order reserveert opnieuw zodra de vervanger opgeslagen
 * wordt, markAsCancelled()/markAsCancelledWithCredit() geven de code terug, en
 * deductDiscount() boekt hem opnieuw af. Deze tests leggen het netto resultaat
 * vast, niet de losse stappen.
 */
function ledgerGiftcard(float $balance = 100.0): DiscountCode
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

function ledgerStockCode(int $stock = 5): DiscountCode
{
    return DiscountCode::create([
        'site_ids' => [Sites::getActive()],
        'name' => 'Kortingscode',
        'code' => 'KORTING-' . strtoupper(uniqid()),
        'type' => 'amount',
        'discount_amount' => 20,
        'use_stock' => 1,
        'stock' => $stock,
    ]);
}

/**
 * De vier tellers van een kortingscode in één array, zodat een test ze in hun
 * geheel kan vergelijken en er niet per ongeluk eentje ongetoetst blijft.
 */
function ledgerCounters(DiscountCode $code): array
{
    $code = $code->fresh();

    return [
        'discount_amount' => round((float) $code->discount_amount, 2),
        'used_amount' => round((float) $code->used_amount, 2),
        'reserved_amount' => round((float) $code->reserved_amount, 2),
        'stock_used' => (int) $code->stock_used,
        'stock' => (int) $code->stock,
    ];
}

/**
 * Een order zoals hij eruitziet nadat hij normaal betaald is: de created-hook
 * heeft gereserveerd en markAsPaid() heeft via deductDiscount() de reservering
 * omgezet in verbruik.
 */
function ledgerOrder(DiscountCode $code, float $discount, string $invoiceId, string $status = 'paid'): Order
{
    Customsetting::set('taxes_prices_include_taxes', 1);

    $order = new Order();
    $order->email = 'klant@example.com';
    $order->status = $status;
    $order->invoice_id = $invoiceId;
    $order->subtotal = 100;
    $order->discount = $discount;
    $order->total = 100 - $discount;
    $order->discount_code_id = $code->id;
    $order->save();

    OrderProduct::create([
        'order_id' => $order->id,
        'name' => 'Oud product',
        'quantity' => 1,
        'price' => 100,
        'vat_rate' => 21,
    ]);

    $order->orderPayments()->create(['status' => 'paid', 'amount' => 100 - $discount, 'psp' => 'own']);

    // Wat markAsPaid()/markAsPartiallyPaid() gedaan zou hebben.
    $order->deductDiscount();

    return $order->fresh();
}

function ledgerLine(float $price): array
{
    return [
        'order_product_id' => null,
        'product_id' => null,
        'name' => 'Nieuw product',
        'quantity' => 1,
        'price' => $price,
        'vat_rate' => 21,
        'product_extras' => [],
    ];
}

it('laat alle cadeaubon-tellers ongemoeid bij een wijziging in de annuleertak', function () {
    $giftcard = ledgerGiftcard(100.0);
    // PROFORMA: geen echte factuur, dus replaceWithNewOrder() annuleert de oude
    // order en verplaatst de betalingen.
    $order = ledgerOrder($giftcard, discount: 20.0, invoiceId: 'PROFORMA');

    $before = ledgerCounters($giftcard);

    OrderModificationService::replaceWithNewOrder($order, [ledgerLine(100.0)], ['send_customer_email' => false]);

    expect(ledgerCounters($giftcard))->toBe($before);
});

it('laat alle cadeaubon-tellers ongemoeid bij een wijziging in de credittak', function () {
    $giftcard = ledgerGiftcard(100.0);
    // Echt factuurnummer, dus de credittak: de oude order blijft op 'paid' en
    // wordt met een creditorder op nul gezet.
    $order = ledgerOrder($giftcard, discount: 20.0, invoiceId: '2026-0001');

    $before = ledgerCounters($giftcard);

    OrderModificationService::replaceWithNewOrder($order, [ledgerLine(100.0)], ['send_customer_email' => false]);

    expect(ledgerCounters($giftcard))->toBe($before);
});

it('verbruikt geen extra voorraad van een use_stock-code bij een deels betaalde order', function () {
    // Deze order haalt de else-tak van markAsCancelled(): daar draaide
    // refillDiscount() nooit, terwijl de vervanger wel deductDiscount() kreeg.
    // Eén wijziging verbruikte de code daardoor een tweede keer.
    $code = ledgerStockCode(stock: 5);
    $order = ledgerOrder($code, discount: 20.0, invoiceId: 'PROFORMA', status: 'partially_paid');

    $before = ledgerCounters($code);

    OrderModificationService::replaceWithNewOrder($order, [ledgerLine(100.0)], ['send_customer_email' => false]);

    expect(ledgerCounters($code))->toBe($before);
});

it('verbruikt geen extra voorraad van een use_stock-code bij een betaalde order', function () {
    $code = ledgerStockCode(stock: 5);
    $order = ledgerOrder($code, discount: 20.0, invoiceId: 'PROFORMA');

    $before = ledgerCounters($code);

    OrderModificationService::replaceWithNewOrder($order, [ledgerLine(100.0)], ['send_customer_email' => false]);

    expect(ledgerCounters($code))->toBe($before);
});

it('houdt de korting op de vervangende order staan', function () {
    // Vangnet bij het onderdrukken van de created-hook-reservering: de korting
    // wordt tijdelijk op 0 gezet en moet daarna wel weer op zijn echte waarde
    // terugkomen, anders betaalt de klant de korting alsnog.
    $giftcard = ledgerGiftcard(100.0);
    $order = ledgerOrder($giftcard, discount: 20.0, invoiceId: 'PROFORMA');

    $new = OrderModificationService::replaceWithNewOrder($order, [ledgerLine(100.0)], ['send_customer_email' => false]);

    expect(round((float) $new->discount, 2))->toBe(20.0)
        ->and(round((float) $new->total, 2))->toBe(80.0)
        ->and($new->discount_code_id)->toBe($giftcard->id);
});
