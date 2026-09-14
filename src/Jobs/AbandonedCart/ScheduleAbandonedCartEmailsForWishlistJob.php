<?php

namespace Dashed\DashedEcommerceCore\Jobs\AbandonedCart;

use Illuminate\Bus\Queueable;
use Illuminate\Queue\SerializesModels;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Dashed\DashedEcommerceCore\Models\Wishlist;
use Dashed\DashedEcommerceCore\Models\AbandonedCartFlow;
use Dashed\DashedEcommerceCore\Models\AbandonedCartEmail;

/**
 * Plant de verlanglijst-reeks in. Elke toevoeging annuleert de lopende reeks
 * en plant hem opnieuw, zodat de mail pas gaat als de bezoeker een tijd niets
 * meer toevoegt (zelfde gedrag als ScheduleAbandonedCartEmailsForCartJob).
 */
class ScheduleAbandonedCartEmailsForWishlistJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public function __construct(public readonly int $wishlistId)
    {
    }

    public function handle(): void
    {
        $wishlist = Wishlist::find($this->wishlistId);

        if (! $wishlist) {
            return;
        }

        AbandonedCartEmail::cancelAllForWishlist($wishlist->id);

        if (! $wishlist->email || $wishlist->publicItems()->isEmpty()) {
            return;
        }

        if ($wishlist->flow_cooldown_until && $wishlist->flow_cooldown_until->isFuture()) {
            return;
        }

        // Een lopende wagen- of orderreeks op hetzelfde adres wint: de wagen is
        // concreter dan de verlanglijst. Bij betaling stopt cancelPendingForEmail
        // beide.
        $andereReeks = AbandonedCartEmail::where('email', $wishlist->email)
            ->where('trigger_type', '!=', 'wishlist')
            ->whereNull('sent_at')
            ->whereNull('cancelled_at')
            ->exists();

        if ($andereReeks) {
            return;
        }

        $flows = AbandonedCartFlow::where('is_active', true)->get()
            ->filter(fn (AbandonedCartFlow $flow) => $flow->hasTrigger('wishlist'));

        foreach ($flows as $flow) {
            $steps = $flow->steps()->where('enabled', true)->orderBy('sort_order')->get();
            $cumulativeHours = 0;

            foreach ($steps as $step) {
                $cumulativeHours += $step->delay_in_hours;

                AbandonedCartEmail::create([
                    'wishlist_id' => $wishlist->id,
                    'trigger_type' => 'wishlist',
                    'email' => $wishlist->email,
                    'email_number' => $step->sort_order,
                    'flow_step_id' => $step->id,
                    'send_at' => now()->addHours($cumulativeHours),
                ]);
            }
        }
    }
}
