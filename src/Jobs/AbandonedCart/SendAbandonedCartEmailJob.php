<?php

namespace Dashed\DashedEcommerceCore\Jobs\AbandonedCart;

use Illuminate\Support\Str;
use Illuminate\Bus\Queueable;
use Dashed\DashedCore\Classes\Sites;
use Illuminate\Support\Facades\Mail;
use Illuminate\Queue\InteractsWithQueue;
use Dashed\DashedEcommerceCore\Models\Cart;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Dashed\DashedEcommerceCore\Models\DiscountCode;
use Dashed\DashedEcommerceCore\Mail\AbandonedCartMail;
use Dashed\DashedEcommerceCore\Models\AbandonedCartEmail;

class SendAbandonedCartEmailJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;

    public int $tries = 3;

    public function __construct(public readonly int $abandonedCartEmailId)
    {
    }

    public function handle(): void
    {
        $record = AbandonedCartEmail::with('flowStep.flow')->find($this->abandonedCartEmailId);

        if (! $record || ! $record->isPending()) {
            return;
        }

        $step = $record->flowStep;

        if (! $step || ! $step->enabled) {
            $record->update(['cancelled_at' => now()]);

            return;
        }

        $cart = Cart::with(['items', 'items.product', 'items.product.productGroup'])->find($record->cart_id);

        if (! $cart || $cart->items->isEmpty()) {
            $record->update(['cancelled_at' => now()]);

            return;
        }

        $discountCode = null;

        if ($step->incentive_enabled && $step->incentive_value > 0) {
            $discountCode = $this->generateDiscountCode($step, $record->email);
            $record->update(['discount_code_id' => $discountCode->id]);
        }

        Mail::to($record->email)->send(new AbandonedCartMail($cart, $step, $discountCode, $cart->locale));

        $record->update(['sent_at' => now()]);
    }

    private function generateDiscountCode($step, string $email): DiscountCode
    {
        $prefix = $step->flow?->discount_prefix ?: 'TERUG';
        $code = $prefix . '-' . strtoupper(Str::random(8));

        return DiscountCode::create([
            'name' => 'Verlaten winkelwagen - ' . $email,
            'code' => $code,
            'type' => $step->incentive_type === 'percentage' ? 'percentage' : 'amount',
            'discount_amount' => $step->incentive_type === 'amount' ? $step->incentive_value : 0,
            'discount_percentage' => $step->incentive_type === 'percentage' ? $step->incentive_value : 0,
            'use_stock' => true,
            'stock' => 1,
            'stock_used' => 0,
            'limit_use_per_customer' => true,
            'start_date' => now(),
            'end_date' => now()->addDays($step->incentive_valid_days),
            'site_ids' => [Sites::getActive()],
        ]);
    }
}
