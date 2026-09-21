<?php

use Dashed\DashedCore\Models\User;
use Illuminate\Support\Facades\Mail;
use Dashed\DashedCore\Models\Customsetting;
use Dashed\DashedEcommerceCore\Models\POSCart;
use Dashed\DashedEcommerceCore\Models\DiscountCode;
use Dashed\DashedEcommerceCore\Mail\ProformaCheckoutMail;

/**
 * CMS-pariteit voor de mobiele kassa: de vier Livewire-only acties die nu een
 * eigen REST-route hebben — prijs per regel aanpassen, cadeaubon toepassen/
 * verwijderen, klant koppelen (customer_user_id) en proforma mailen.
 */
function posLine(string $identifier, float $single, int $qty = 1, float $vat = 21.0): array
{
    return [
        'id' => null,
        'name' => 'Regel ' . $identifier,
        'quantity' => $qty,
        'singlePrice' => $single,
        'price' => $single * $qty,
        'vat_rate' => $vat,
        'extra' => [],
        'identifier' => $identifier,
    ];
}

/** Ingelogde kassamedewerker (admin, sanctum) — pos_carts.user_id verwijst naar users. */
function posCashier($test): User
{
    $cashier = User::factory()->create(['role' => 'admin']);
    $test->actingAs($cashier, 'sanctum');

    return $cashier;
}

it('past de prijs van een regel aan (incl-BTW modus)', function () {
    $cashier = posCashier($this);
    $cart = POSCart::create(['user_id' => $cashier->id, 'identifier' => 'pos-cpp-1', 'products' => [posLine('a', 10.0, 2)]]);

    $this->postJson('/api/v1/point-of-sale/change-product-price', [
        'posIdentifier' => 'pos-cpp-1',
        'productIdentifier' => 'a',
        'singlePrice' => 15,
    ], ['X-Site-Id' => 'site'])->assertOk()->assertJsonPath('success', true);

    $line = collect(POSCart::find($cart->id)->products)->firstWhere('identifier', 'a');
    expect((float) $line['singlePrice'])->toBe(15.0)
        ->and((float) $line['price'])->toBe(30.0)
        ->and($line['isCustomPrice'])->toBeTrue();
});

it('rekent een ex-BTW ingevoerde regelprijs om naar incl', function () {
    $cashier = posCashier($this);
    $cart = POSCart::create(['user_id' => $cashier->id, 'identifier' => 'pos-cpp-2', 'prices_ex_vat' => true, 'products' => [posLine('a', 10.0, 1, 21.0)]]);

    $this->postJson('/api/v1/point-of-sale/change-product-price', [
        'posIdentifier' => 'pos-cpp-2',
        'productIdentifier' => 'a',
        'singlePrice' => 100,
    ], ['X-Site-Id' => 'site'])->assertOk();

    $line = collect(POSCart::find($cart->id)->products)->firstWhere('identifier', 'a');
    expect((float) $line['singlePrice'])->toBe(121.0);
});

it('past een cadeaubon toe en verwijdert hem weer', function () {
    $cashier = posCashier($this);
    DiscountCode::create([
        'site_ids' => ['site'],
        'name' => 'Cadeaubon',
        'code' => 'GC-PARITY-1',
        'is_giftcard' => 1,
        'discount_amount' => 25.0,
        'use_stock' => 0,
    ]);
    $cart = POSCart::create(['user_id' => $cashier->id, 'identifier' => 'pos-gc-1', 'products' => [posLine('a', 40.0, 1)]]);

    $this->postJson('/api/v1/point-of-sale/apply-gift-card', [
        'posIdentifier' => 'pos-gc-1',
        'code' => 'GC-PARITY-1',
    ], ['X-Site-Id' => 'site'])->assertOk()->assertJsonPath('success', true);

    expect(collect(POSCart::find($cart->id)->applied_gift_cards)->pluck('code'))->toContain('GC-PARITY-1')
        ->and((float) collect(POSCart::find($cart->id)->applied_gift_cards)->firstWhere('code', 'GC-PARITY-1')['balance'])->toBe(25.0);

    $this->postJson('/api/v1/point-of-sale/remove-gift-card', [
        'posIdentifier' => 'pos-gc-1',
        'code' => 'GC-PARITY-1',
    ], ['X-Site-Id' => 'site'])->assertOk()->assertJsonPath('success', true);

    expect(POSCart::find($cart->id)->applied_gift_cards ?? [])->toBeEmpty();
});

it('weigert een onbekende cadeaubon met 422', function () {
    $cashier = posCashier($this);
    POSCart::create(['user_id' => $cashier->id, 'identifier' => 'pos-gc-2', 'products' => []]);

    $this->postJson('/api/v1/point-of-sale/apply-gift-card', [
        'posIdentifier' => 'pos-gc-2',
        'code' => 'BESTAAT-NIET',
    ], ['X-Site-Id' => 'site'])->assertStatus(422)->assertJsonPath('success', false);
});

it('koppelt een klantaccount en zet ex-BTW aan als de klant dat wil', function () {
    $cashier = posCashier($this);
    $customer = User::factory()->create();
    $customer->show_prices_ex_vat = true;
    $customer->save();

    $cart = POSCart::create(['user_id' => $cashier->id, 'identifier' => 'pos-cust-1', 'products' => []]);

    $this->postJson('/api/v1/point-of-sale/update-customer-data', [
        'posIdentifier' => 'pos-cust-1',
        'customer_user_id' => $customer->id,
        'first_name' => 'Gekoppeld',
    ], ['X-Site-Id' => 'site'])->assertOk()->assertJsonPath('success', true);

    $fresh = POSCart::find($cart->id);
    expect((int) $fresh->customer_user_id)->toBe((int) $customer->id)
        ->and((bool) $fresh->prices_ex_vat)->toBeTrue()
        ->and($fresh->first_name)->toBe('Gekoppeld');
});

it('weigert proforma als de instelling uit staat', function () {
    Customsetting::set('pos_allow_proforma', false);
    $cashier = posCashier($this);
    POSCart::create(['user_id' => $cashier->id, 'identifier' => 'pos-pf-0', 'products' => [posLine('a', 50.0, 1)]]);

    $this->postJson('/api/v1/point-of-sale/send-proforma', [
        'posIdentifier' => 'pos-pf-0',
        'email' => 'klant@example.com',
    ], ['X-Site-Id' => 'site'])->assertStatus(422)->assertJsonPath('success', false);
});

it('slaat een proforma op en mailt de klant', function () {
    Mail::fake();
    Customsetting::set('pos_allow_proforma', true);
    $cashier = posCashier($this);
    POSCart::create([
        'user_id' => $cashier->id,
        'identifier' => 'pos-pf-1',
        'email' => 'klant@example.com',
        'products' => [posLine('a', 50.0, 1)],
    ]);

    $this->postJson('/api/v1/point-of-sale/send-proforma', [
        'posIdentifier' => 'pos-pf-1',
        'email' => 'klant@example.com',
        'allowShipping' => true,
    ], ['X-Site-Id' => 'site'])->assertOk()->assertJsonPath('success', true);

    Mail::assertSent(ProformaCheckoutMail::class);
});
