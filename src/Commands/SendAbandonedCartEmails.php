<?php

namespace Dashed\DashedEcommerceCore\Commands;

use Illuminate\Support\Str;
use Illuminate\Console\Command;
use Dashed\DashedCore\Classes\Sites;
use Illuminate\Support\Facades\Mail;
use Dashed\DashedEcommerceCore\Models\DiscountCode;
use Dashed\DashedEcommerceCore\Mail\AbandonedCartMail;
use Dashed\DashedEcommerceCore\Models\AbandonedCartEmail;
use Dashed\DashedEcommerceCore\Services\AbandonedCart\AbandonedCartSourceResolver;

class SendAbandonedCartEmails extends Command
{
    protected $signature = 'dashed:send-abandoned-cart-emails';

    protected $description = 'Send pending abandoned cart emails that are due';

    public function handle(): void
    {
        $emails = AbandonedCartEmail::query()
            ->with('flowStep.flow')
            ->whereNull('sent_at')
            ->whereNull('cancelled_at')
            ->where('send_at', '<=', now())
            ->get();

        foreach ($emails as $record) {
            $step = $record->flowStep;

            if (! $step || ! $step->enabled) {
                $record->update(['cancelled_at' => now(), 'cancelled_reason' => 'step_disabled']);

                continue;
            }

            if (! $this->sourceIsValid($record)) {
                continue;
            }

            $source = AbandonedCartSourceResolver::for($record);

            if ($source->items()->isEmpty()) {
                $record->update(['cancelled_at' => now(), 'cancelled_reason' => 'source_empty']);

                continue;
            }

            $discountCode = null;

            if ($step->incentive_enabled && $step->incentive_value > 0) {
                $discountCode = $this->generateDiscountCode($step, $record->email);
                $record->update(['discount_code_id' => $discountCode->id]);
            }

            Mail::to($record->email)->send(new AbandonedCartMail($record, $step, $discountCode, $source->locale(), $record->id));

            $record->update(['sent_at' => now()]);

            $this->info("Sent abandoned cart email #{$record->id} to {$record->email}");
        }

        $this->info("Done. {$emails->count()} email(s) processed.");
    }

    private function sourceIsValid(AbandonedCartEmail $record): bool
    {
        if ($record->trigger_type === 'cancelled_order') {
            $order = $record->cancelledOrder;

            if (! $order) {
                $record->update(['cancelled_at' => now(), 'cancelled_reason' => 'source_empty']);

                return false;
            }

            $orderWasPaid = $order->orderPayments()->where('status', 'paid')->exists();
            if ($orderWasPaid || $order->status !== 'cancelled') {
                $record->update(['cancelled_at' => now(), 'cancelled_reason' => 'source_recovered']);

                return false;
            }

            return true;
        }

        $cart = $record->cart;

        if (! $cart) {
            $record->update(['cancelled_at' => now(), 'cancelled_reason' => 'source_empty']);

            return false;
        }

        return true;
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
