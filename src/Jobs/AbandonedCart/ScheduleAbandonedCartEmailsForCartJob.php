<?php

namespace Dashed\DashedEcommerceCore\Jobs\AbandonedCart;

use Illuminate\Bus\Queueable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Dashed\DashedEcommerceCore\Models\Cart;
use Dashed\DashedEcommerceCore\Models\AbandonedCartEmail;
use Dashed\DashedEcommerceCore\Models\AbandonedCartFlow;

class ScheduleAbandonedCartEmailsForCartJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable;

    public function __construct(public readonly int $cartId)
    {
    }

    public function handle(): void
    {
        $flow = AbandonedCartFlow::getActive();

        if (! $flow) {
            return;
        }

        $cart = Cart::with('items')->find($this->cartId);

        if (! $cart || ! $cart->abandoned_email || $cart->items->isEmpty()) {
            return;
        }

        // Cancel any previous pending emails for this cart
        AbandonedCartEmail::cancelAllForCart($this->cartId);

        $steps = $flow->steps()->where('enabled', true)->orderBy('sort_order')->get();

        if ($steps->isEmpty()) {
            return;
        }

        $cumulativeHours = 0;

        foreach ($steps as $step) {
            $cumulativeHours += $step->delay_in_hours;

            AbandonedCartEmail::create([
                'cart_id' => $this->cartId,
                'email' => $cart->abandoned_email,
                'email_number' => $step->sort_order,
                'flow_step_id' => $step->id,
                'send_at' => now()->addHours($cumulativeHours),
            ]);
        }
    }
}
