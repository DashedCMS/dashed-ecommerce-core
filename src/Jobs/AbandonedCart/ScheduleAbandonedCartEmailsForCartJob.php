<?php

namespace Dashed\DashedEcommerceCore\Jobs\AbandonedCart;

use Illuminate\Bus\Queueable;
use Illuminate\Queue\InteractsWithQueue;
use Dashed\DashedEcommerceCore\Models\Cart;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Dashed\DashedEcommerceCore\Models\AbandonedCartFlow;
use Dashed\DashedEcommerceCore\Models\AbandonedCartEmail;

class ScheduleAbandonedCartEmailsForCartJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;

    public function __construct(public readonly int $cartId)
    {
    }

    public function handle(): void
    {
        $cart = Cart::with('items')->find($this->cartId);

        if (! $cart || ! $cart->abandoned_email || $cart->items->isEmpty()) {
            return;
        }

        AbandonedCartEmail::cancelAllForCart($this->cartId);

        $flows = AbandonedCartFlow::where('is_active', true)
            ->get()
            ->filter(fn (AbandonedCartFlow $flow) => $flow->hasTrigger('cart_with_email'));

        if ($flows->isEmpty()) {
            return;
        }

        foreach ($flows as $flow) {
            $steps = $flow->steps()->where('enabled', true)->orderBy('sort_order')->get();

            if ($steps->isEmpty()) {
                continue;
            }

            $cumulativeHours = 0;

            foreach ($steps as $step) {
                $cumulativeHours += $step->delay_in_hours;

                AbandonedCartEmail::create([
                    'cart_id' => $this->cartId,
                    'trigger_type' => 'cart_with_email',
                    'email' => $cart->abandoned_email,
                    'email_number' => $step->sort_order,
                    'flow_step_id' => $step->id,
                    'send_at' => now()->addHours($cumulativeHours),
                ]);
            }

            \Dashed\DashedEcommerceCore\Services\CartActivityLogger::abandonedEmailsScheduled($cart, $flow, $steps->count());
        }
    }
}
