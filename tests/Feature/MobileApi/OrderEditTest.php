<?php

use Dashed\DashedCore\Models\User;
use Dashed\DashedEcommerceCore\Models\Order;
use Dashed\DashedEcommerceCore\Models\OrderProduct;

function makeEditableOrder(array $attributes = [], string $siteId = 'site'): Order
{
    return Order::create(array_merge([
        'site_id' => $siteId,
        'email' => 'klant@example.com',
        'invoice_id' => 'INV-' . strtoupper(uniqid()),
        'status' => 'paid',
        'first_name' => 'Oud',
        'last_name' => 'Achternaam',
        'city' => 'Amsterdam',
    ], $attributes));
}

it('bewerkt klant- en adresgegevens van een order', function () {
    $this->actingAs(User::factory()->create(['role' => 'admin']), 'sanctum');
    $order = makeEditableOrder();

    $res = $this->patchJson("/api/v1/orders/{$order->id}/details", [
        'first_name' => 'Nieuw',
        'city' => 'Zwolle',
    ], ['X-Site-Id' => 'site']);

    $res->assertOk()
        ->assertJsonPath('data.first_name', 'Nieuw')
        ->assertJsonPath('data.city', 'Zwolle');

    $fresh = $order->fresh();
    expect($fresh->first_name)->toBe('Nieuw')
        ->and($fresh->city)->toBe('Zwolle')
        ->and($fresh->last_name)->toBe('Achternaam'); // niet meegestuurde velden blijven ongewijzigd
});

it('bewerkt factuuradres en bedrijfsgegevens onafhankelijk van het verzendadres', function () {
    $this->actingAs(User::factory()->create(['role' => 'admin']), 'sanctum');
    $order = makeEditableOrder();

    $res = $this->patchJson("/api/v1/orders/{$order->id}/details", [
        'company_name' => 'Acme B.V.',
        'btw_id' => 'NL123456789B01',
        'note' => 'Bellen voor levering',
        'invoice_street' => 'Factuurstraat',
        'invoice_house_nr' => '1',
        'invoice_zip_code' => '1000AA',
        'invoice_city' => 'Rotterdam',
        'invoice_country' => 'NL',
    ], ['X-Site-Id' => 'site']);

    $res->assertOk();

    $fresh = $order->fresh();
    expect($fresh->company_name)->toBe('Acme B.V.')
        ->and($fresh->btw_id)->toBe('NL123456789B01')
        ->and($fresh->note)->toBe('Bellen voor levering')
        ->and($fresh->invoice_street)->toBe('Factuurstraat')
        ->and($fresh->invoice_city)->toBe('Rotterdam');
});

it('valideert email en weigert onbekende velden niet stilzwijgend te accepteren als leeg', function () {
    $this->actingAs(User::factory()->create(['role' => 'admin']), 'sanctum');
    $order = makeEditableOrder();

    $this->patchJson("/api/v1/orders/{$order->id}/details", [
        'email' => 'niet-een-email',
    ], ['X-Site-Id' => 'site'])->assertStatus(422);
});

it('returns 404 voor een order op een andere site', function () {
    $this->actingAs(User::factory()->create(['role' => 'admin']), 'sanctum');
    $other = makeEditableOrder([], 'other');

    $this->patchJson("/api/v1/orders/{$other->id}/details", [
        'first_name' => 'Nieuw',
    ], ['X-Site-Id' => 'site'])->assertNotFound();
});

it('weigert bewerken zonder de orders.write ability', function () {
    $this->actingAs(User::factory()->create(['role' => 'customer']), 'sanctum');
    $order = makeEditableOrder();

    $this->patchJson("/api/v1/orders/{$order->id}/details", [
        'first_name' => 'Nieuw',
    ], ['X-Site-Id' => 'site'])->assertStatus(403);
});

it('geeft een breakdown-preview terug voor een bewerkbare order zonder de order te muteren', function () {
    $this->actingAs(User::factory()->create(['role' => 'admin']), 'sanctum');
    $order = makeEditableOrder(); // status 'paid' → is_modifiable true, in_place false (al betaald)
    $orderProduct = OrderProduct::create([
        'order_id' => $order->id,
        'name' => 'Testproduct',
        'quantity' => 1,
        'price' => 10.00,
        'discount' => 0,
        'vat_rate' => 21,
    ]);

    $res = $this->postJson("/api/v1/orders/{$order->id}/modify/preview", [
        'lines' => [
            [
                'order_product_id' => $orderProduct->id,
                'name' => 'Testproduct',
                'quantity' => 2,
                'price' => 20.00,
                'vat_rate' => 21,
            ],
        ],
    ], ['X-Site-Id' => 'site']);

    $res->assertOk()
        ->assertJsonStructure([
            'breakdown' => ['net', 'subtotal', 'discount', 'total', 'uncapped', 'reduced_by'],
            'in_place',
            'is_modifiable',
        ])
        ->assertJsonPath('breakdown.net', 20.0)
        ->assertJsonPath('breakdown.total', 20.0)
        ->assertJsonPath('is_modifiable', true)
        ->assertJsonPath('in_place', false); // order is al 'paid' → geen in-place-wijziging meer mogelijk

    // modifyPreview muteert de order niet: het orderproduct staat nog op de oude waarden.
    $fresh = $orderProduct->fresh();
    expect($fresh->quantity)->toBe(1)
        ->and((float) $fresh->price)->toBe(10.00)
        ->and($order->fresh()->orderProducts()->count())->toBe(1);
});

it('leidt de regelkorting af van het bronorderproduct in de preview', function () {
    $this->actingAs(User::factory()->create(['role' => 'admin']), 'sanctum');
    $order = makeEditableOrder();
    $orderProduct = OrderProduct::create([
        'order_id' => $order->id,
        'name' => 'Testproduct',
        'quantity' => 1,
        'price' => 8.00,
        'discount' => 2.00,
        'vat_rate' => 21,
    ]);

    // Zelfde regel, ongewijzigd qua prijs → volgt de bronregel, dus de
    // vaste korting van 2.00 wordt overgenomen in de breakdown.
    $res = $this->postJson("/api/v1/orders/{$order->id}/modify/preview", [
        'lines' => [
            [
                'order_product_id' => $orderProduct->id,
                'name' => 'Testproduct',
                'quantity' => 1,
                'price' => 8.00,
                'vat_rate' => 21,
            ],
        ],
    ], ['X-Site-Id' => 'site']);

    $res->assertOk()->assertJsonPath('breakdown.discount', 2.0);
});

it('valideert de regels van een modify-preview', function () {
    $this->actingAs(User::factory()->create(['role' => 'admin']), 'sanctum');
    $order = makeEditableOrder();

    $this->postJson("/api/v1/orders/{$order->id}/modify/preview", [
        'lines' => [],
    ], ['X-Site-Id' => 'site'])->assertStatus(422);
});

it('returns 404 voor een modify-preview op een order van een andere site', function () {
    $this->actingAs(User::factory()->create(['role' => 'admin']), 'sanctum');
    $other = makeEditableOrder([], 'other');

    $this->postJson("/api/v1/orders/{$other->id}/modify/preview", [
        'lines' => [
            ['name' => 'X', 'quantity' => 1, 'price' => 1],
        ],
    ], ['X-Site-Id' => 'site'])->assertNotFound();
});

it('weigert een modify-preview zonder de orders.write ability', function () {
    $this->actingAs(User::factory()->create(['role' => 'customer']), 'sanctum');
    $order = makeEditableOrder();

    $this->postJson("/api/v1/orders/{$order->id}/modify/preview", [
        'lines' => [
            ['name' => 'X', 'quantity' => 1, 'price' => 1],
        ],
    ], ['X-Site-Id' => 'site'])->assertStatus(403);
});

it('past een productwijziging in-place toe voor een onbetaalde, bewerkbare order', function () {
    $this->actingAs(User::factory()->create(['role' => 'admin']), 'sanctum');
    // Onbetaald, geen echt factuurnummer, geen geslaagde betaling → canModifyInPlace() true.
    $order = makeEditableOrder(['status' => 'pending', 'invoice_id' => 'PROFORMA']);
    $orderProduct = OrderProduct::create([
        'order_id' => $order->id,
        'name' => 'Testproduct',
        'quantity' => 1,
        'price' => 10.00,
        'discount' => 0,
        'vat_rate' => 21,
    ]);

    $res = $this->patchJson("/api/v1/orders/{$order->id}/modify", [
        'lines' => [
            [
                'order_product_id' => $orderProduct->id,
                'name' => 'Testproduct',
                'quantity' => 3,
                'price' => 30.00,
                'vat_rate' => 21,
            ],
        ],
        'send_customer_email' => false,
    ], ['X-Site-Id' => 'site']);

    $res->assertOk()
        ->assertJsonPath('replaced', false)
        ->assertJsonPath('replacement_order_id', null)
        ->assertJsonPath('order.id', $order->id);

    $fresh = $order->fresh();
    expect($fresh->orderProducts()->count())->toBe(1);
    $line = $fresh->orderProducts()->first();
    expect($line->quantity)->toBe(3)
        ->and((float) $line->price)->toBe(30.00)
        ->and((float) $fresh->total)->toBe(30.00);
});

it('vervangt een betaalde order door een nieuwe met creditorder bij een productwijziging', function () {
    $this->actingAs(User::factory()->create(['role' => 'admin']), 'sanctum');
    // Al betaald met een echt factuurnummer → canModifyInPlace() false, isModifiable() true.
    $order = makeEditableOrder(['status' => 'paid']);
    $orderProduct = OrderProduct::create([
        'order_id' => $order->id,
        'name' => 'Testproduct',
        'quantity' => 1,
        'price' => 10.00,
        'discount' => 0,
        'vat_rate' => 21,
    ]);

    $res = $this->patchJson("/api/v1/orders/{$order->id}/modify", [
        'lines' => [
            [
                'order_product_id' => $orderProduct->id,
                'name' => 'Testproduct',
                'quantity' => 2,
                'price' => 20.00,
                'vat_rate' => 21,
            ],
        ],
        'send_customer_email' => false,
    ], ['X-Site-Id' => 'site']);

    $res->assertOk()->assertJsonPath('replaced', true);
    $replacementId = $res->json('replacement_order_id');
    expect($replacementId)->not->toBeNull()
        ->and($res->json('order.id'))->toBe($replacementId);

    $fresh = $order->fresh();
    expect($fresh->replaced_by_order_id)->toBe($replacementId);

    $replacement = Order::find($replacementId);
    expect($replacement)->not->toBeNull()
        ->and($replacement->orderProducts()->count())->toBe(1)
        ->and((float) $replacement->total)->toBe(20.00);
});

it('weigert een modify voor een order die niet meer bewerkbaar is', function () {
    $this->actingAs(User::factory()->create(['role' => 'admin']), 'sanctum');
    $order = makeEditableOrder(['status' => 'cancelled']);

    $res = $this->patchJson("/api/v1/orders/{$order->id}/modify", [
        'lines' => [
            ['name' => 'X', 'quantity' => 1, 'price' => 1],
        ],
    ], ['X-Site-Id' => 'site']);

    $res->assertStatus(422)->assertJsonPath('success', false);
});

it('returns 404 bij modify van een order op een andere site', function () {
    $this->actingAs(User::factory()->create(['role' => 'admin']), 'sanctum');
    $other = makeEditableOrder(['status' => 'pending', 'invoice_id' => 'PROFORMA'], 'other');

    $this->patchJson("/api/v1/orders/{$other->id}/modify", [
        'lines' => [
            ['name' => 'X', 'quantity' => 1, 'price' => 1],
        ],
    ], ['X-Site-Id' => 'site'])->assertNotFound();
});

it('weigert modify zonder de orders.write ability', function () {
    $this->actingAs(User::factory()->create(['role' => 'customer']), 'sanctum');
    $order = makeEditableOrder(['status' => 'pending', 'invoice_id' => 'PROFORMA']);

    $this->patchJson("/api/v1/orders/{$order->id}/modify", [
        'lines' => [
            ['name' => 'X', 'quantity' => 1, 'price' => 1],
        ],
    ], ['X-Site-Id' => 'site'])->assertStatus(403);
});
