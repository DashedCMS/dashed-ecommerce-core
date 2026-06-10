<?php

use Illuminate\Support\Str;
use Illuminate\Support\Facades\Mail;
use Dashed\DashedEcommerceCore\Models\Order;
use Dashed\DashedEcommerceCore\Models\Product;
use Dashed\DashedEcommerceCore\Models\ProductGroup;
use Dashed\DashedEcommerceCore\Models\AbandonedCartFlow;
use Dashed\DashedEcommerceCore\Models\AbandonedCartEmail;
use Dashed\DashedEcommerceCore\Models\AbandonedCartFlowStep;
use Symfony\Component\Mailer\Exception\TransportException;

function mailFailureProduct(): Product
{
    $group = ProductGroup::create([
        'name' => ['en' => 'Group'], 'slug' => ['en' => 'group'],
        'short_description' => ['en' => ''], 'description' => ['en' => ''],
        'content' => ['en' => ''], 'search_terms' => ['en' => ''],
        'site_ids' => ['default'],
    ]);

    return Product::withoutEvents(fn () => Product::create([
        'name' => ['en' => 'Thing'], 'slug' => ['en' => 'thing-' . Str::random(5)],
        'site_ids' => ['default'], 'product_group_id' => $group->id,
        'use_stock' => true, 'stock' => 50, 'total_stock' => 50, 'in_stock' => true,
        'stock_status' => 'in_stock', 'price' => 30.00, 'current_price' => 30.00,
    ]));
}

it('cancels a row as mail_failed when sending throws (e.g. Postmark 406 inactive) without halting the command', function () {
    Mail::fake(); // scheduling tijdens setup mag geen echte mail sturen

    $product = mailFailureProduct();

    $flow = AbandonedCartFlow::create([
        'name' => 'F', 'is_active' => true,
        'discount_prefix' => 'P', 'triggers' => ['cancelled_order'],
    ]);
    AbandonedCartFlowStep::create([
        'flow_id' => $flow->id, 'sort_order' => 1,
        'delay_value' => 1, 'delay_unit' => 'hours',
        'subject' => 'Herstel je bestelling', 'enabled' => true,
        'blocks' => [['type' => 'text', 'data' => ['content' => '<p>Hoi</p>']]],
    ]);

    $order = Order::create([
        'email' => 'inactive@example.test', 'status' => 'pending',
        'total' => 30, 'invoice_id' => '7777',
    ]);
    $order->orderProducts()->create([
        'product_id' => $product->id, 'name' => 'Thing', 'quantity' => 1, 'price' => 30,
    ]);
    $order->markAsCancelled();

    $row = AbandonedCartEmail::where('cancelled_order_id', $order->id)->firstOrFail();

    $this->travel(2)->hours();

    // Forceer dat het versturen gooit, zoals Postmark doet bij een inactive recipient (code 406).
    Mail::shouldReceive('to')->andReturnSelf();
    Mail::shouldReceive('send')->andThrow(new TransportException(
        'Unable to send an email: ... marked as inactive ... (code 406).'
    ));

    // Het commando mag NIET stuklopen op de verzendfout.
    $this->artisan('dashed:send-abandoned-cart-emails')->assertSuccessful();

    $fresh = $row->fresh();
    expect($fresh->sent_at)->toBeNull();
    expect($fresh->cancelled_at)->not->toBeNull();
    expect($fresh->cancelled_reason)->toBe('mail_failed');
});
