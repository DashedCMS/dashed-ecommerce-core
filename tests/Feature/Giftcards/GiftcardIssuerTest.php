<?php

use Illuminate\Support\Facades\Event;
use Dashed\DashedEcommerceCore\Models\Order;
use Dashed\DashedEcommerceCore\Models\Product;
use Dashed\DashedEcommerceCore\Models\DiscountCode;
use Dashed\DashedEcommerceCore\Models\OrderProduct;
use Dashed\DashedEcommerceCore\Models\ProductGroup;
use Dashed\DashedEcommerceCore\Events\GiftcardIssuedEvent;
use Dashed\DashedEcommerceCore\Services\Giftcards\GiftcardIssuer;

function giftcardIssuerOrder(array $orderAttrs = []): array
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

    $order = Order::create(array_merge([
        'email' => 'klant@example.com',
        'first_name' => 'Kim',
        'last_name' => 'Jansen',
        'status' => 'paid',
        'invoice_id' => 'INV-GC-' . strtoupper(uniqid()),
        'subtotal' => 50,
        'total' => 50,
        'btw' => 8.68,
    ], $orderAttrs));

    $line = OrderProduct::withoutEvents(fn () => OrderProduct::create([
        'order_id' => $order->id,
        'product_id' => $product->id,
        'name' => 'Bon 25',
        'quantity' => 2,
        'price' => 50,
        'vat_rate' => 21,
        'sku' => 'BON',
    ]));

    return [$order->fresh(), $line];
}

it('issues one giftcard per unit', function () {
    [$order, $line] = giftcardIssuerOrder();

    $cards = app(GiftcardIssuer::class)->issueFor($order);

    expect($cards)->toHaveCount(2)
        ->and($cards->pluck('code')->unique())->toHaveCount(2);
    foreach ($cards as $card) {
        expect($card->giftcard_type)->toBe('verkocht')
            ->and((float) $card->discount_amount)->toBe(25.0)
            ->and((float) $card->initial_amount)->toBe(25.0)
            ->and($card->source_order_product_id)->toBe($line->id)
            ->and($card->end_date)->toBeNull()
            ->and($card->name)->toBe('Cadeaubon order ' . $order->invoice_id)
            ->and($card->code)->toMatch('/^LOV-[A-Z0-9]{4}-[A-Z0-9]{4}$/');
    }
});

it('creates nothing new when issuing twice', function () {
    [$order] = giftcardIssuerOrder();

    app(GiftcardIssuer::class)->issueFor($order);
    $second = app(GiftcardIssuer::class)->issueFor($order);

    expect($second)->toHaveCount(0)
        ->and(DiscountCode::soldGiftcard()->count())->toBe(2);
});

it('dispatches an event per giftcard', function () {
    [$order] = giftcardIssuerOrder();
    Event::fake([GiftcardIssuedEvent::class]);

    app(GiftcardIssuer::class)->issueFor($order);

    Event::assertDispatchedTimes(GiftcardIssuedEvent::class, 2);
});

it('issues nothing for a credit order', function () {
    [$original] = giftcardIssuerOrder();
    [$credit] = giftcardIssuerOrder(['credit_for_order_id' => $original->id]);

    expect(app(GiftcardIssuer::class)->issueFor($credit))->toHaveCount(0);
});

it('issues nothing for a marketplace order', function () {
    [$order] = giftcardIssuerOrder(['order_origin' => 'bol']);

    expect(app(GiftcardIssuer::class)->issueFor($order))->toHaveCount(0);
});

it('issues when an order transitions to paid', function () {
    [$order] = giftcardIssuerOrder(['status' => 'pending']);

    $order->status = 'paid';
    $order->save();

    expect(DiscountCode::soldGiftcard()->count())->toBe(2);
});
