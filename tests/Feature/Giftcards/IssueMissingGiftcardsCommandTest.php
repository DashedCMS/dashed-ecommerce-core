<?php

use Illuminate\Support\Facades\DB;
use Dashed\DashedEcommerceCore\Models\Order;
use Dashed\DashedEcommerceCore\Models\Product;
use Dashed\DashedEcommerceCore\Models\DiscountCode;
use Dashed\DashedEcommerceCore\Models\OrderProduct;
use Dashed\DashedEcommerceCore\Models\ProductGroup;

/**
 * Vangnet voor de uitgifte: betaalde orders met cadeaubonregels zonder
 * (alle) bonnen krijgen ze alsnog, en een tweede run doet niets.
 */
function vangnetBonOrder(array $orderAttrs = []): Order
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

    // Meteen als betaald aangemaakt: de observer (updated) ziet geen overgang.
    $order = Order::create(array_merge([
        'email' => 'klant@example.com',
        'status' => 'paid',
        'invoice_id' => 'INV-SWEEP-' . strtoupper(uniqid()),
        'subtotal' => 50,
        'total' => 50,
        'btw' => 8.68,
    ], $orderAttrs));

    OrderProduct::withoutEvents(fn () => OrderProduct::create([
        'order_id' => $order->id, 'product_id' => $product->id, 'name' => 'Bon 25', 'quantity' => 2, 'price' => 50, 'vat_rate' => 21, 'sku' => 'BON',
    ]));

    return $order;
}

it('issues missing giftcards for recent paid orders and is idempotent', function () {
    $order = vangnetBonOrder();
    expect(DiscountCode::soldGiftcard()->count())->toBe(0);

    $this->artisan('dashed:issue-missing-giftcards')->assertSuccessful();

    expect(DiscountCode::soldGiftcard()->count())->toBe(2);

    $this->artisan('dashed:issue-missing-giftcards')->assertSuccessful();

    expect(DiscountCode::soldGiftcard()->count())->toBe(2);
});

it('skips unpaid, credit and old orders', function () {
    vangnetBonOrder(['status' => 'pending']);
    $original = vangnetBonOrder(['status' => 'cancelled']);
    vangnetBonOrder(['status' => 'return', 'credit_for_order_id' => $original->id]);
    $old = vangnetBonOrder();
    DB::table('dashed__orders')->where('id', $old->id)->update(['created_at' => now()->subDays(40), 'updated_at' => now()->subDays(40)]);

    $this->artisan('dashed:issue-missing-giftcards')->assertSuccessful();

    expect(DiscountCode::soldGiftcard()->count())->toBe(0);
});
