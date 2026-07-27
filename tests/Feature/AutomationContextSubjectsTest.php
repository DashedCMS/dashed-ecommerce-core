<?php

declare(strict_types=1);

use Dashed\DashedCore\Models\User;
use Dashed\DashedEcommerceCore\Models\Order;
use Dashed\DashedEcommerceCore\Models\Product;
use Dashed\DashedEcommerceCore\Support\Automation\AutomationContext;

/**
 * Task 1 (B3): AutomationContext::forProduct/forCustomer/for() — geen
 * Product::factory()/Order::factory() in dit package (zie ook
 * AutomationEngineTest/TimeAnchorsTest), dus we bouwen models met ::create().
 * Product::create() wrapt in Product::withoutEvents(): de `saved`-listener
 * dispatcht UpdateProductInformationJob (MySQL-only GREATEST()) synchroon
 * onder de sync-queue-config van de testsuite — ongerelateerd aan dit werk,
 * dus overslaan i.p.v. faken.
 */
function automationContextProduct(array $attributes = []): Product
{
    return Product::withoutEvents(fn () => Product::create(array_merge([
        'name' => 'Widget',
        'slug' => 'widget-'.uniqid(),
        'site_ids' => ['main'],
        'stock' => 4,
        'price' => 9.99,
        'sku' => 'W-1',
    ], $attributes)));
}

function automationContextOrder(array $attributes = []): Order
{
    return Order::create(array_merge([
        'email' => 'a@b.nl',
        'status' => 'concept',
    ], $attributes));
}

it('forProduct vult voorraad/prijs/naam/sku', function () {
    $product = automationContextProduct(['name' => 'Widget', 'stock' => 4, 'price' => 9.99, 'sku' => 'W-1']);

    expect(AutomationContext::forProduct($product))->toBe([
        'stock' => 4,
        'price' => 9.99,
        'name' => 'Widget',
        'sku' => 'W-1',
    ]);
});

it('forCustomer telt bestellingen van dezelfde klant (gast, via email)', function () {
    $o1 = automationContextOrder(['email' => 'a@b.nl', 'total' => 10]);
    $o2 = automationContextOrder(['email' => 'a@b.nl', 'total' => 20]);
    // Andere klant mag niet meetellen.
    automationContextOrder(['email' => 'someone-else@b.nl', 'total' => 1000]);

    $ctx = AutomationContext::forCustomer($o2);

    expect($ctx['order_count'])->toBe(2)
        ->and($ctx['total_spend'])->toBe(30.0)
        ->and($ctx['email'])->toBe('a@b.nl')
        ->and($ctx['is_registered'])->toBeFalse();

    // De eerste order (o1) telt hetzelfde mee, want beide zijn dezelfde klant.
    expect($o1->id)->not->toBe($o2->id);
});

it('forCustomer telt alleen bestellingen op dezelfde site als de order', function () {
    $sameSite1 = automationContextOrder(['email' => 'a@b.nl', 'total' => 10]);
    $sameSite2 = automationContextOrder(['email' => 'a@b.nl', 'total' => 20]);

    // Order::boot() overschrijft site_id bij create() altijd met Sites::getActive();
    // forceFill()->save() (buiten de creating-hook om) is de manier om 'm daarna
    // naar een andere site te verplaatsen, zoals ook TimeAnchorsTest doet voor created_at.
    $otherSite = automationContextOrder(['email' => 'a@b.nl', 'total' => 1000]);
    $otherSite->forceFill(['site_id' => 'other-site'])->save();

    expect($otherSite->fresh()->site_id)->not->toBe($sameSite2->site_id);

    $ctx = AutomationContext::forCustomer($sameSite2->fresh());

    expect($ctx['order_count'])->toBe(2)
        ->and($ctx['total_spend'])->toBe(30.0);
});

it('forCustomer telt op user_id voor geregistreerde klanten', function () {
    $user = User::factory()->create();

    $o1 = automationContextOrder(['user_id' => $user->id, 'email' => $user->email, 'total' => 15]);
    $o2 = automationContextOrder(['user_id' => $user->id, 'email' => $user->email, 'total' => 25]);

    $ctx = AutomationContext::forCustomer($o2);

    expect($ctx['order_count'])->toBe(2)
        ->and($ctx['total_spend'])->toBe(40.0)
        ->and($ctx['is_registered'])->toBeTrue();

    expect($o1->user_id)->toBe($user->id);
});

it('for() dispatcht op type en is fail-closed voor onbekende subjects', function () {
    $product = automationContextProduct();
    $order = automationContextOrder();

    expect(AutomationContext::for($product))->toHaveKey('stock');
    expect(AutomationContext::for($order))->toHaveKey('total');
    expect(AutomationContext::for(new User()))->toBe([]);
});

it('for() geeft extra door aan forOrder, niet aan forProduct', function () {
    $order = automationContextOrder();

    $ctx = AutomationContext::for($order, ['old_status' => 'concept']);

    expect($ctx['old_status'])->toBe('concept');
});
