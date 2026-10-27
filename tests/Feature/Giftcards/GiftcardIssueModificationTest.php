<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Event;
use Dashed\DashedCore\Models\Customsetting;
use Dashed\DashedEcommerceCore\Models\Order;
use Dashed\DashedEcommerceCore\Models\Product;
use Dashed\DashedEcommerceCore\Models\DiscountCode;
use Dashed\DashedEcommerceCore\Models\OrderProduct;
use Dashed\DashedEcommerceCore\Models\ProductGroup;
use Dashed\DashedEcommerceCore\Classes\OrderModificationService;
use Dashed\DashedEcommerceCore\Services\Giftcards\GiftcardIssuer;
use Dashed\DashedEcommerceCore\Events\Orders\OrderCancelledEvent;
use Dashed\DashedEcommerceCore\Events\Orders\OrderModifiedEvent;
use Dashed\DashedEcommerceCore\Events\Orders\OrderMarkedAsPaidEvent;
use Dashed\DashedEcommerceCore\Events\Orders\OrderFulfillmentStatusChangedEvent;

require_once __DIR__ . '/../../Support/InvoiceHarness.php';

/**
 * Een betaalde order met cadeaubonnen die gewijzigd wordt: de vervanger
 * krijgt de bestaande bonnen, niet een tweede set.
 */
beforeEach(function () {
    bootInvoiceHarness();
    Customsetting::set('taxes_prices_include_taxes', 1);
    Bus::fake();
    Mail::fake();
    Event::fake([OrderMarkedAsPaidEvent::class, OrderCancelledEvent::class, OrderFulfillmentStatusChangedEvent::class, OrderModifiedEvent::class]);
});

function wijzigBonProduct(): Product
{
    $group = ProductGroup::create([
        'name' => ['en' => 'Bon'],
        'slug' => ['en' => 'bon-' . uniqid()],
        'short_description' => ['en' => ''],
        'description' => ['en' => ''],
        'content' => ['en' => ''],
        'search_terms' => ['en' => ''],
        'site_ids' => ['default'],
    ]);
    $group->is_giftcard_product = true;
    $group->save();

    return Product::withoutEvents(fn () => Product::create([
        'product_group_id' => $group->id,
        'name' => ['en' => 'Bon 25'],
        'slug' => ['en' => 'bon-25-' . uniqid()],
        'site_ids' => ['default'],
        'current_price' => 25,
        'price' => 25,
        'vat_rate' => 21,
    ]));
}

/** Betaalde order met 2 bonnen van € 25 en de bonnen al uitgegeven. */
function wijzigBonOrder(Product $product, string $invoiceId = 'PROFORMA'): array
{
    $order = Order::create([
        'email' => 'klant@example.com',
        'status' => 'pending',
        'invoice_id' => $invoiceId,
        'total' => 50,
        'subtotal' => 50,
        'btw' => 8.68,
    ]);
    $line = OrderProduct::withoutEvents(fn () => OrderProduct::create([
        'order_id' => $order->id, 'product_id' => $product->id, 'name' => 'Bon 25', 'quantity' => 2, 'price' => 50, 'vat_rate' => 21, 'sku' => 'BON',
    ]));
    $order->orderPayments()->create(['status' => 'paid', 'amount' => 50, 'psp' => 'own', 'payment_method' => 'iDEAL']);
    $order->status = 'paid';
    $order->save();

    return [$order->fresh(), $line];
}

function wijzigBonRegel(Product $product, OrderProduct $source, int $quantity): array
{
    return ['order_product_id' => $source->id, 'product_id' => $product->id, 'name' => 'Bon 25', 'quantity' => $quantity, 'price' => 25 * $quantity, 'vat_rate' => 21, 'product_extras' => []];
}

it('moves the existing giftcards to the replacement instead of issuing new ones', function () {
    $product = wijzigBonProduct();
    [$order, $line] = wijzigBonOrder($product);
    $codes = DiscountCode::soldGiftcard()->pluck('code')->sort()->values()->all();
    expect($codes)->toHaveCount(2);

    $newOrder = OrderModificationService::replaceWithNewOrder($order, [wijzigBonRegel($product, $line, 2)], ['credit_old_order' => false]);
    $newLine = $newOrder->orderProducts()->first();

    expect($newOrder->status)->toBe('paid')
        ->and(DiscountCode::soldGiftcard()->count())->toBe(2)
        ->and(DiscountCode::soldGiftcard()->pluck('code')->sort()->values()->all())->toBe($codes)
        ->and(DiscountCode::soldGiftcard()->pluck('source_order_product_id')->unique()->all())->toBe([$newLine->id]);
});

it('only issues the extra giftcard when the replacement has more', function () {
    $product = wijzigBonProduct();
    [$order, $line] = wijzigBonOrder($product);

    $newOrder = OrderModificationService::replaceWithNewOrder($order, [wijzigBonRegel($product, $line, 3)], ['credit_old_order' => false]);
    $newOrder->orderPayments()->create(['status' => 'paid', 'amount' => 25, 'psp' => 'own']);
    $newOrder->changeStatus('paid');

    $newLine = $newOrder->orderProducts()->first();

    expect(DiscountCode::soldGiftcard()->count())->toBe(3)
        ->and(DiscountCode::soldGiftcard()->where('source_order_product_id', $newLine->id)->count())->toBe(3);
});

it('keeps the cards usable when the old order is credited', function () {
    $product = wijzigBonProduct();
    [$order, $line] = wijzigBonOrder($product, 'INV-MOD-' . strtoupper(uniqid()));

    $newOrder = OrderModificationService::replaceWithNewOrder($order, [wijzigBonRegel($product, $line, 2)], ['credit_old_order' => true]);
    $newLine = $newOrder->orderProducts()->first();

    expect($newOrder->status)->toBe('paid')
        ->and(DiscountCode::soldGiftcard()->count())->toBe(2)
        ->and(DiscountCode::soldGiftcard()->where('source_order_product_id', $newLine->id)->count())->toBe(2)
        ->and(DiscountCode::soldGiftcard()->sum('discount_amount'))->toEqual(50);
});

it('keeps the cards on the order when its lines are rewritten in place', function () {
    $product = wijzigBonProduct();
    [$order, $line] = wijzigBonOrder($product);

    OrderModificationService::writeLines($order, [wijzigBonRegel($product, $line, 2)]);
    $newLine = $order->orderProducts()->first();

    expect(app(GiftcardIssuer::class)->issueFor($order->fresh()))->toHaveCount(0)
        ->and(DiscountCode::soldGiftcard()->count())->toBe(2)
        ->and(DiscountCode::soldGiftcard()->where('source_order_product_id', $newLine->id)->count())->toBe(2);
});
