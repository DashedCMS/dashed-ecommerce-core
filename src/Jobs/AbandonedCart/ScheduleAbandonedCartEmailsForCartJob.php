<?php

namespace Dashed\DashedEcommerceCore\Jobs\AbandonedCart;

use Carbon\Carbon;
use Illuminate\Bus\Queueable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Dashed\DashedCore\Models\Customsetting;
use Dashed\DashedEcommerceCore\Models\Cart;
use Dashed\DashedEcommerceCore\Models\AbandonedCartEmail;

class ScheduleAbandonedCartEmailsForCartJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable;

    public function __construct(public readonly int $cartId)
    {
    }

    public function handle(): void
    {
        if (! Customsetting::get('abandoned_cart_emails_enabled', null, false)) {
            return;
        }

        $cart = Cart::with('items')->find($this->cartId);

        if (! $cart || ! $cart->abandoned_email || $cart->items->isEmpty()) {
            return;
        }

        // Cancel any previous pending emails for this cart
        AbandonedCartEmail::cancelAllForCart($this->cartId);

        $email = $cart->abandoned_email;
        $delay1 = (int) Customsetting::get('abandoned_cart_email1_delay_hours', null, 1);
        $delay2 = (int) Customsetting::get('abandoned_cart_email2_delay_hours', null, 24);
        $delay3 = (int) Customsetting::get('abandoned_cart_email3_delay_hours', null, 72);
        $email3Enabled = (bool) Customsetting::get('abandoned_cart_email3_enabled', null, false);

        $record1 = AbandonedCartEmail::create([
            'cart_id' => $this->cartId,
            'email' => $email,
            'email_number' => 1,
        ]);

        $record2 = AbandonedCartEmail::create([
            'cart_id' => $this->cartId,
            'email' => $email,
            'email_number' => 2,
        ]);

        SendAbandonedCartEmailJob::dispatch($record1->id)->delay(now()->addHours($delay1));
        SendAbandonedCartEmailJob::dispatch($record2->id)->delay(now()->addHours($delay1 + $delay2));

        if ($email3Enabled) {
            $record3 = AbandonedCartEmail::create([
                'cart_id' => $this->cartId,
                'email' => $email,
                'email_number' => 3,
            ]);

            SendAbandonedCartEmailJob::dispatch($record3->id)->delay(now()->addHours($delay1 + $delay2 + $delay3));
        }
    }
}
