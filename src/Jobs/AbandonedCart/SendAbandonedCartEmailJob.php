<?php

namespace Dashed\DashedEcommerceCore\Jobs\AbandonedCart;

use Illuminate\Support\Str;
use Illuminate\Bus\Queueable;
use Dashed\DashedCore\Classes\Sites;
use Illuminate\Support\Facades\Mail;
use Illuminate\Queue\InteractsWithQueue;
use Dashed\DashedEcommerceCore\Models\Cart;
use Dashed\DashedEcommerceCore\Models\Order;
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

        // If the recipient has a paid order within the configured cooldown
        // window, do not send the next reminder. The flow stores the cooldown
        // in days; null/0 disables the check.
        $cooldownDays = (int) ($step->flow?->skip_if_paid_within_days ?? 0);
        if ($cooldownDays > 0 && ! blank($record->email)) {
            $hasRecentPaid = Order::query()
                ->where('email', $record->email)
                ->isPaid()
                ->where('created_at', '>=', now()->subDays($cooldownDays))
                ->exists();

            if ($hasRecentPaid) {
                AbandonedCartEmail::cancelPendingForEmail(
                    $record->email,
                    'recent_paid_order',
                );

                return;
            }
        }

        $discountCode = null;

        if ($step->incentive_enabled && $step->incentive_value > 0) {
            $discountCode = $this->generateDiscountCode($step, $record->email);
            $record->update(['discount_code_id' => $discountCode->id]);
        }

        try {
            Mail::to($record->email)->send(new AbandonedCartMail($record, $step, $discountCode, $cart->locale));
        } catch (\Throwable $e) {
            report($e);
            \Illuminate\Support\Facades\Log::warning('abandoned-cart: mail kon niet verstuurd worden', [
                'abandoned_cart_email_id' => $record->id,
                'cart_id' => $cart->id,
                'flow_step_id' => $step->id,
                'error' => $e->getMessage(),
            ]);

            // Postmark levert bv. een 406 als het adres als inactive
            // is gemarkeerd (hard bounce / spam complaint). Verdere
            // stappen voor dezelfde ontvanger cancellen we via
            // cancelPendingForEmail zodat ze niet opnieuw worden
            // geprobeerd, en de job slaagt zodat hij niet eindeloos
            // retried wordt.
            \Dashed\DashedEcommerceCore\Models\AbandonedCartEmail::cancelPendingForEmail($record->email, 'mail_send_failed');

            return;
        }

        $record->update(['sent_at' => now()]);

        \Dashed\DashedEcommerceCore\Services\CartActivityLogger::abandonedEmailSent($cart, $step, $discountCode);
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
