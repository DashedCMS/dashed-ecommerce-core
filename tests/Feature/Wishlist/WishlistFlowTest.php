<?php

use Illuminate\Support\Str;
use Illuminate\Support\Facades\Bus;
use Dashed\DashedEcommerceCore\Models\Cart;
use Dashed\DashedEcommerceCore\Models\Product;
use Dashed\DashedEcommerceCore\Models\Wishlist;
use Dashed\DashedEcommerceCore\Classes\WishlistHelper;
use Dashed\DashedEcommerceCore\Models\AbandonedCartFlow;
use Dashed\DashedEcommerceCore\Models\AbandonedCartEmail;
use Dashed\DashedEcommerceCore\Models\AbandonedCartFlowStep;
use Dashed\DashedEcommerceCore\Services\AbandonedCart\AbandonedCartTriggers;
use Dashed\DashedEcommerceCore\Jobs\AbandonedCart\ScheduleAbandonedCartEmailsForWishlistJob;

// Product::create vuurt via het saved-event UpdateProductInformationJob af, dat
// een productGroup verwacht; zonder groep crasht dat (zie WishlistHelperTest).
// withoutEvents() onderdrukt dat, en current_price zetten we zelf.
function flowProduct(): Product
{
    return Product::withoutEvents(fn () => Product::create([
        'name' => 'Vaas '.Str::random(4),
        'slug' => 'vaas-'.Str::lower(Str::random(8)),
        'price' => 20,
        'current_price' => 20,
        'public' => 1,
        'site_ids' => ['default'],
    ]));
}

function wishlistFlow(): AbandonedCartFlow
{
    $flow = AbandonedCartFlow::create(['name' => 'Verlanglijst', 'is_active' => true, 'triggers' => ['wishlist'], 'skip_if_paid_within_days' => 30]);
    AbandonedCartFlowStep::create(['flow_id' => $flow->id, 'sort_order' => 1, 'delay_value' => 48, 'delay_unit' => 'hours', 'subject' => ['nl' => 'Stap 1'], 'enabled' => true, 'show_products' => true, 'show_review' => false, 'incentive_enabled' => false, 'incentive_type' => 'percentage', 'incentive_value' => 0, 'incentive_valid_days' => 7]);
    AbandonedCartFlowStep::create(['flow_id' => $flow->id, 'sort_order' => 2, 'delay_value' => 120, 'delay_unit' => 'hours', 'subject' => ['nl' => 'Stap 2'], 'enabled' => true, 'show_products' => true, 'show_review' => false, 'incentive_enabled' => false, 'incentive_type' => 'percentage', 'incentive_value' => 0, 'incentive_valid_days' => 7]);

    return $flow;
}

beforeEach(function () {
    WishlistHelper::reset();
    config()->set('dashed-ecommerce-core.wishlist_flows_enabled', true);
});

it('is als trigger geregistreerd', function () {
    expect(AbandonedCartTriggers::has('wishlist'))->toBeTrue();
});

it('plant per stap een mail met cumulatieve vertraging voor een lijst met e-mail', function () {
    wishlistFlow();
    $wishlist = Wishlist::create(['email' => 'jan@example.com']);
    $wishlist->items()->create(['product_id' => flowProduct()->id, 'price_at_add' => 20]);

    (new ScheduleAbandonedCartEmailsForWishlistJob($wishlist->id))->handle();

    $mails = AbandonedCartEmail::where('wishlist_id', $wishlist->id)->orderBy('email_number')->get();
    expect($mails)->toHaveCount(2)
        ->and($mails[0]->trigger_type)->toBe('wishlist')
        ->and(abs($mails[0]->send_at->diffInHours(now())))->toBeGreaterThan(47)
        ->and(abs($mails[1]->send_at->diffInHours(now())))->toBeGreaterThan(167);
});

it('plant niets zonder e-mail, zonder publieke producten, in de cooldown of naast een lopende wagenreeks', function () {
    wishlistFlow();

    $zonderEmail = Wishlist::create([]);
    $zonderEmail->items()->create(['product_id' => flowProduct()->id, 'price_at_add' => 20]);
    (new ScheduleAbandonedCartEmailsForWishlistJob($zonderEmail->id))->handle();

    $leeg = Wishlist::create(['email' => 'leeg@example.com']);
    (new ScheduleAbandonedCartEmailsForWishlistJob($leeg->id))->handle();

    $cooldown = Wishlist::create(['email' => 'cool@example.com', 'flow_cooldown_until' => now()->addDays(10)]);
    $cooldown->items()->create(['product_id' => flowProduct()->id, 'price_at_add' => 20]);
    (new ScheduleAbandonedCartEmailsForWishlistJob($cooldown->id))->handle();

    $cart = Cart::create(['abandoned_email' => 'wagen@example.com']);
    AbandonedCartEmail::create(['cart_id' => $cart->id, 'trigger_type' => 'cart_with_email', 'email' => 'wagen@example.com', 'email_number' => 1, 'send_at' => now()->addHour()]);
    $naastWagen = Wishlist::create(['email' => 'wagen@example.com']);
    $naastWagen->items()->create(['product_id' => flowProduct()->id, 'price_at_add' => 20]);
    (new ScheduleAbandonedCartEmailsForWishlistJob($naastWagen->id))->handle();

    expect(AbandonedCartEmail::where('trigger_type', 'wishlist')->count())->toBe(0);
});

it('start de klok opnieuw bij een nieuwe toevoeging en annuleert bij leegmaken', function () {
    wishlistFlow();
    $wishlist = Wishlist::create(['email' => 'jan@example.com']);
    $a = flowProduct();
    $wishlist->items()->create(['product_id' => $a->id, 'price_at_add' => 20]);
    (new ScheduleAbandonedCartEmailsForWishlistJob($wishlist->id))->handle();
    $eerste = AbandonedCartEmail::where('wishlist_id', $wishlist->id)->pluck('id')->all();

    $wishlist->items()->create(['product_id' => flowProduct()->id, 'price_at_add' => 20]);
    (new ScheduleAbandonedCartEmailsForWishlistJob($wishlist->id))->handle();

    expect(AbandonedCartEmail::whereIn('id', $eerste)->whereNotNull('cancelled_at')->count())->toBe(2)
        ->and(AbandonedCartEmail::where('wishlist_id', $wishlist->id)->whereNull('cancelled_at')->count())->toBe(2);

    $wishlist->items()->delete();
    (new ScheduleAbandonedCartEmailsForWishlistJob($wishlist->id))->handle();

    expect(AbandonedCartEmail::where('wishlist_id', $wishlist->id)->whereNull('cancelled_at')->count())->toBe(0);
});

it('dispatcht de job als de helper een product toevoegt aan een lijst met e-mail', function () {
    Bus::fake([ScheduleAbandonedCartEmailsForWishlistJob::class]);

    wishlistHelper()->add(flowProduct());
    Bus::assertNotDispatched(ScheduleAbandonedCartEmailsForWishlistJob::class);

    wishlistHelper()->adoptEmail('jan@example.com');
    Bus::assertDispatched(ScheduleAbandonedCartEmailsForWishlistJob::class);
});
