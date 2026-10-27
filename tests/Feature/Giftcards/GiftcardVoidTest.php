<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Event;
use Dashed\DashedCore\Models\Customsetting;
use Dashed\DashedEcommerceCore\Models\Order;
use Dashed\DashedEcommerceCore\Models\Product;
use Dashed\DashedEcommerceCore\Models\OrderLog;
use Dashed\DashedEcommerceCore\Models\OrderReturn;
use Dashed\DashedEcommerceCore\Models\DiscountCode;
use Dashed\DashedEcommerceCore\Models\OrderProduct;
use Dashed\DashedEcommerceCore\Models\ProductGroup;
use Dashed\DashedEcommerceCore\Models\OrderReturnLine;
use Dashed\DashedEcommerceCore\Events\Orders\OrderCancelledEvent;
use Dashed\DashedEcommerceCore\Events\Orders\OrderModifiedEvent;
use Dashed\DashedEcommerceCore\Services\OrderReturn\ReturnProcessor;
use Dashed\DashedEcommerceCore\Events\Orders\OrderMarkedAsPaidEvent;
use Dashed\DashedEcommerceCore\Events\Orders\OrderReturnProcessedEvent;
use Dashed\DashedEcommerceCore\Events\Orders\OrderFulfillmentStatusChangedEvent;

require_once __DIR__ . '/../../Support/InvoiceHarness.php';

/**
 * Een gekochte cadeaubon die gecrediteerd wordt (annuleren of retour) mag
 * daarna niet meer bruikbaar zijn.
 */
beforeEach(function () {
    bootInvoiceHarness();
    Customsetting::set('taxes_prices_include_taxes', 1);
    Bus::fake();
    Mail::fake();
    Event::fake([OrderMarkedAsPaidEvent::class, OrderCancelledEvent::class, OrderFulfillmentStatusChangedEvent::class, OrderModifiedEvent::class, OrderReturnProcessedEvent::class]);
});

/** Betaalde order met 2 bonnen van € 25, uitgegeven via de statuswijziging. */
function vervalBonOrder(): array
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

    $product = Product::withoutEvents(fn () => Product::create([
        'product_group_id' => $group->id,
        'name' => ['en' => 'Bon 25'],
        'slug' => ['en' => 'bon-25-' . uniqid()],
        'site_ids' => ['default'],
        'current_price' => 25,
        'price' => 25,
        'vat_rate' => 21,
    ]));

    $order = Order::create([
        'email' => 'klant@example.com',
        'status' => 'pending',
        'invoice_id' => 'INV-VOID-' . strtoupper(uniqid()),
        'total' => 50,
        'subtotal' => 50,
        'btw' => 8.68,
        'fulfillment_status' => 'handled',
    ]);
    $line = OrderProduct::withoutEvents(fn () => OrderProduct::create([
        'order_id' => $order->id, 'product_id' => $product->id, 'name' => 'Bon 25', 'quantity' => 2, 'price' => 50, 'vat_rate' => 21, 'sku' => 'BON',
    ]));
    $order->orderPayments()->create(['status' => 'paid', 'amount' => 50, 'psp' => 'own', 'payment_method' => 'iDEAL']);
    $order->status = 'paid';
    $order->save();

    return [$order->fresh(), $line];
}

function vervalCrediteer(Order $order, OrderProduct $line, int $quantity): Order
{
    $line = $line->fresh();
    $line->refundQuantity = $quantity;

    return $order->fresh()->markAsCancelledWithCredit(
        sendCustomerEmail: false,
        productsMustBeReturned: false,
        restock: false,
        refundDiscountCosts: false,
        extraOrderLineName: null,
        extraOrderLinePrice: 0,
        chosenOrderProducts: [$line],
        fulfillmentStatus: 'handled',
        paymentMethodId: null,
    );
}

it('voids the newest unused giftcard for each credited unit', function () {
    [$order, $line] = vervalBonOrder();
    [$oldest, $newest] = DiscountCode::soldGiftcard()->orderBy('id')->get()->all();

    vervalCrediteer($order, $line, 1);

    expect((float) $newest->fresh()->discount_amount)->toBe(0.0)
        ->and($newest->fresh()->end_date)->not->toBeNull()
        ->and($newest->fresh()->logs()->where('tag', 'giftcard.voided')->exists())->toBeTrue()
        ->and((float) $oldest->fresh()->discount_amount)->toBe(25.0)
        ->and($oldest->fresh()->end_date)->toBeNull();

    // Een tweede annulering vervalt de andere bon, niet dezelfde opnieuw.
    vervalCrediteer($order, $line, 1);

    expect((float) $oldest->fresh()->discount_amount)->toBe(0.0)
        ->and($oldest->fresh()->end_date)->not->toBeNull();
});

it('zeroes a partly used giftcard and warns on the order', function () {
    [$order, $line] = vervalBonOrder();
    $cards = DiscountCode::soldGiftcard()->orderBy('id')->get();
    $cards[1]->forceFill(['discount_amount' => 10, 'used_amount' => 15])->save();

    vervalCrediteer($order, $line, 1);

    expect((float) $cards[1]->fresh()->discount_amount)->toBe(0.0)
        ->and((float) $cards[1]->fresh()->used_amount)->toBe(15.0)
        ->and(OrderLog::where('order_id', $order->id)->where('tag', 'order.giftcard.voided-used')->exists())->toBeTrue()
        ->and((float) $cards[0]->fresh()->discount_amount)->toBe(25.0);
});

it('never touches a promo giftcard', function () {
    [$order, $line] = vervalBonOrder();
    DiscountCode::soldGiftcard()->get()->each->forceDelete();

    $promo = new DiscountCode();
    $promo->forceFill([
        'site_ids' => ['default'],
        'name' => 'Promo',
        'code' => 'PROMO-' . strtoupper(uniqid()),
        'type' => 'amount',
        'discount_amount' => 25,
        'initial_amount' => 25,
        'is_giftcard' => 1,
        'giftcard_type' => 'promo',
        'source_order_product_id' => $line->id,
    ]);
    DiscountCode::withoutEvents(fn () => $promo->save());

    vervalCrediteer($order, $line, 2);

    expect((float) $promo->fresh()->discount_amount)->toBe(25.0)
        ->and($promo->fresh()->end_date)->toBeNull();
});

it('voids the giftcard when a return is processed', function () {
    [$order, $line] = vervalBonOrder();
    $newest = DiscountCode::soldGiftcard()->orderByDesc('id')->first();

    $return = OrderReturn::create(['order_id' => $order->id, 'email' => 'klant@example.com', 'status' => OrderReturn::STATUS_APPROVED, 'approved_at' => now()]);
    $returnLine = OrderReturnLine::create(['order_return_id' => $return->id, 'order_product_id' => $line->id, 'quantity' => 1]);
    app(ReturnProcessor::class)->process($return->fresh(), [['order_return_line_id' => $returnLine->id, 'quantity' => 1]], ['restock' => false]);

    expect((float) $newest->fresh()->discount_amount)->toBe(0.0)
        ->and(DiscountCode::soldGiftcard()->where('discount_amount', '>', 0)->count())->toBe(1);
});
