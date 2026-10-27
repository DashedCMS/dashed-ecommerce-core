<?php

declare(strict_types=1);

use Livewire\Livewire;
use Illuminate\Support\Str;
use Dashed\DashedCore\Models\Role;
use Dashed\DashedCore\Models\User;
use Illuminate\Support\Facades\Bus;
use Dashed\DashedCore\Classes\Sites;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Dashed\DashedCore\Models\Customsetting;
use Dashed\DashedEcommerceCore\Models\Order;
use Dashed\DashedCore\Classes\CmsIpAllowlist;
use Dashed\DashedEcommerceCore\Models\POSCart;
use Dashed\DashedEcommerceCore\Jobs\SendInvoiceJob;
use Dashed\DashedEcommerceCore\Jobs\PrintReceiptJob;
use Dashed\DashedEcommerceCore\Models\PaymentMethod;
use Dashed\DashedEcommerceCore\Jobs\OpenCashRegisterJob;
use Dashed\DashedEcommerceCore\Filament\Pages\POS\POSPage;
use Dashed\DashedEcommerceCore\Livewire\Orders\CancelOrder;
use Dashed\DashedEcommerceCore\Livewire\Orders\CreateOrderLog;
use Dashed\DashedEcommerceCore\Filament\Pages\ShippingLabelErrors;
use Dashed\DashedEcommerceCore\Livewire\Orders\CreateTrackAndTrace;
use Dashed\DashedEcommerceCore\Livewire\Orders\ChangeOrderRetourStatus;
use Dashed\DashedEcommerceCore\Livewire\Orders\ChangeOrderFulfillmentStatus;
use Dashed\DashedEcommerceCore\Livewire\Orders\SendOrderConfirmationToEmail;
use Dashed\DashedEcommerceCore\Livewire\Orders\SendOrderToFulfillmentCompanies;
use Dashed\DashedEcommerceCore\Filament\Resources\OrderResource\Pages\ViewOrder;

/*
 * De kassa-API, de kassa zelf en de knoppen op de bestelpagina vroegen alleen
 * om een back-office account, niet om het recht dat erbij hoort. Een
 * beheerder met alleen "Bestellingen bekijken" kon zo een bestelling
 * annuleren, op betaald zetten of via de kassa-API afrekenen.
 */

beforeEach(function () {
    Mail::fake();
    Bus::fake([SendInvoiceJob::class, PrintReceiptJob::class, OpenCashRegisterJob::class]);
    Storage::fake('dashed');
    app()->setLocale('nl');
});

function hardeningGebruiker(array $rechten = [], string $rol = 'admin'): User
{
    $user = User::create([
        'first_name' => 'Test',
        'email' => 'hardening-' . Str::random(8) . '@example.com',
        'password' => bcrypt('x'),
        'role' => $rol,
    ]);

    if ($rechten !== []) {
        $user->roles()->attach(Role::create(['name' => 'Rol ' . Str::random(4), 'extra_permissions' => $rechten])->id);
    }

    return metMfa($user->fresh());
}

function hardeningOrder(array $attributes = []): Order
{
    return Order::withoutEvents(fn () => Order::create(array_merge([
        'site_id' => Sites::getActive(),
        'email' => 'klant@example.com',
        'hash' => Str::random(32),
        'invoice_id' => 'PROFORMA',
        'status' => 'pending',
        'fulfillment_status' => 'unhandled',
        'total' => 25.0,
        'subtotal' => 25.0,
        'btw' => 0,
        'locale' => 'nl',
        'ip' => '127.0.0.1',
    ], $attributes)));
}

function hardeningKassaBetaling(Order $order, array $extra = []): array
{
    $methode = PaymentMethod::create([
        'site_id' => Sites::getActive(),
        'name' => ['nl' => 'Contant'],
        'psp' => 'own',
        'type' => 'pos',
        'active' => 1,
        'is_cash_payment' => 1,
    ]);

    $cart = POSCart::create([
        'user_id' => auth()->id(),
        'identifier' => 'pos-' . Str::random(8),
        'status' => 'active',
        'products' => [],
    ]);

    return array_merge([
        'posIdentifier' => $cart->identifier,
        'order' => ['id' => $order->id],
        'paymentMethod' => ['id' => $methode->id],
        'cashPaymentAmount' => $order->total,
    ], $extra);
}

// --- Kassa-API ---------------------------------------------------------------

it('weigert de kassa-API zonder het recht view_pos', function () {
    Customsetting::set('pos_enabled', true);
    $this->actingAs(hardeningGebruiker(['view_order']));

    $this->postJson('/api/point-of-sale/search-products', ['search' => 'x'])->assertForbidden();
});

it('weigert de kassa-API zolang de kassa uit staat', function () {
    $this->actingAs(hardeningGebruiker(['view_pos']));

    $this->postJson('/api/point-of-sale/search-products', ['search' => 'x'])->assertForbidden();
});

it('laat de kassa-API door met view_pos en de kassa aan', function () {
    Customsetting::set('pos_enabled', true);
    $this->actingAs(hardeningGebruiker(['view_pos']));

    $this->postJson('/api/point-of-sale/search-products', ['search' => 'x'])->assertOk();
});

it('weigert de kassapagina zonder het recht view_pos', function () {
    Customsetting::set('pos_enabled', true);
    $this->actingAs(hardeningGebruiker(['view_order']));

    $this->get('/ecommerce/point-of-sale')->assertForbidden();
});

it('past de IP-lijst van het CMS ook toe op de kassa-API', function () {
    Customsetting::set('pos_enabled', true);
    Customsetting::set(CmsIpAllowlist::SETTING, '10.9.8.7', CmsIpAllowlist::siteId());
    $this->actingAs(hardeningGebruiker(['view_pos']));

    $this->postJson('/api/point-of-sale/search-products', ['search' => 'x'])->assertForbidden();
});

it('geeft JSON in plaats van een omleiding als de kassa-API om MFA vraagt', function () {
    Customsetting::set('pos_enabled', true);
    $user = User::create(['first_name' => 'Zonder', 'email' => 'zonder-mfa@example.com', 'password' => bcrypt('x'), 'role' => 'superadmin']);
    $this->actingAs($user);

    $this->postJson('/api/point-of-sale/search-products', ['search' => 'x'])
        ->assertForbidden()
        ->assertJsonStructure(['message', 'redirect']);
});

it('vraagt de pincode als de kassa een bestelling van buiten de kassa op betaald zet', function () {
    config()->set('dashed-ecommerce-core.security.manual_paid_pin', '1234');
    Customsetting::set('pos_enabled', true);
    $this->actingAs(hardeningGebruiker(['view_pos']));
    $order = hardeningOrder(['order_origin' => 'own']);
    $payload = hardeningKassaBetaling($order);

    $this->postJson('/api/point-of-sale/mark-as-paid', $payload)->assertStatus(422);
    expect($order->fresh()->status)->toBe('pending');

    $this->postJson('/api/point-of-sale/mark-as-paid', $payload + ['pin' => '1234'])->assertOk();
    expect($order->fresh()->status)->toBe('paid');
});

it('vraagt geen pincode bij een gewone kassaverkoop', function () {
    config()->set('dashed-ecommerce-core.security.manual_paid_pin', '1234');
    Customsetting::set('pos_enabled', true);
    $this->actingAs(hardeningGebruiker(['view_pos']));
    $order = hardeningOrder(['order_origin' => 'pos']);

    $this->postJson('/api/point-of-sale/mark-as-paid', hardeningKassaBetaling($order))->assertOk();
    expect($order->fresh()->status)->toBe('paid');
});

// --- Kassa (Livewire) ----------------------------------------------------------

it('laat de kassa geen concept van een andere site verwijderen', function () {
    Customsetting::set('pos_enabled', true);
    $this->actingAs(hardeningGebruiker(['view_pos']));
    $ander = hardeningOrder(['site_id' => 'andere-site', 'status' => 'concept']);

    Livewire::test(POSPage::class)->call('cancelConcept', $ander->id);

    expect(Order::withTrashed()->find($ander->id)->status)->toBe('concept')
        ->and(Order::find($ander->id))->not->toBeNull();
});

it('weigert de kassa zonder het recht view_pos', function () {
    Customsetting::set('pos_enabled', true);
    $this->actingAs(hardeningGebruiker(['view_order']));

    Livewire::test(POSPage::class)->assertForbidden();
});

// --- Bestelpagina --------------------------------------------------------------

it('verbergt de bewerkende knoppen op de bestelpagina zonder edit_order', function () {
    $this->actingAs(hardeningGebruiker(['view_order']));
    $order = hardeningOrder(['status' => 'cancelled']);

    Livewire::test(ViewOrder::class, ['record' => $order->getRouteKey()])
        ->assertActionHidden('togglePriority')
        ->assertActionHidden('markCancelledAsPaid')
        ->assertActionHidden('deleteDraft')
        ->assertActionHidden('copyToPos');

    expect($order->fresh()->status)->toBe('cancelled');
});

it('vraagt de pincode bij een geannuleerde bestelling op betaald zetten', function () {
    config()->set('dashed-ecommerce-core.security.manual_paid_pin', '1234');
    $this->actingAs(hardeningGebruiker(['view_order', 'edit_order']));
    $order = hardeningOrder(['status' => 'cancelled']);

    Livewire::test(ViewOrder::class, ['record' => $order->getRouteKey()])
        ->callAction('markCancelledAsPaid', ['pin' => '0000'])
        ->assertHasActionErrors();

    expect($order->fresh()->status)->toBe('cancelled');

    Livewire::test(ViewOrder::class, ['record' => $order->getRouteKey()])
        ->callAction('markCancelledAsPaid', ['pin' => '1234'])
        ->assertHasNoActionErrors();

    expect($order->fresh()->status)->toBe('paid');
});

it('verbergt de bestelknoppen zonder edit_order', function (string $component) {
    $this->actingAs(hardeningGebruiker(['view_order']));
    $order = hardeningOrder(['status' => 'paid']);

    Livewire::test($component, ['order' => $order])->assertActionHidden('action');
})->with([
    CancelOrder::class,
    ChangeOrderFulfillmentStatus::class,
    ChangeOrderRetourStatus::class,
    CreateOrderLog::class,
    CreateTrackAndTrace::class,
    SendOrderConfirmationToEmail::class,
    SendOrderToFulfillmentCompanies::class,
]);

it('laat een beheerder met edit_order de status wijzigen', function () {
    $this->actingAs(hardeningGebruiker(['view_order', 'edit_order']));
    $order = hardeningOrder(['status' => 'paid']);

    Livewire::test(ChangeOrderFulfillmentStatus::class, ['order' => $order])
        ->assertActionVisible('action');
});

it('schermt de pagina met labelfouten af zonder bestelrechten', function () {
    $this->actingAs(hardeningGebruiker(['view_product']));
    expect(ShippingLabelErrors::canAccess())->toBeFalse();

    $this->actingAs(hardeningGebruiker(['view_order', 'edit_order']));
    expect(ShippingLabelErrors::canAccess())->toBeTrue();
});
