<?php

namespace Dashed\DashedEcommerceCore\Jobs\AbandonedCart;

use Illuminate\Bus\Queueable;
use Illuminate\Support\Str;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Support\Facades\Mail;
use Dashed\DashedCore\Classes\Sites;
use Dashed\DashedCore\Models\Customsetting;
use Dashed\DashedEcommerceCore\Models\Cart;
use Dashed\DashedEcommerceCore\Models\DiscountCode;
use Dashed\DashedEcommerceCore\Models\AbandonedCartEmail;
use Dashed\DashedEcommerceCore\Mail\AbandonedCartEmail1Mail;
use Dashed\DashedEcommerceCore\Mail\AbandonedCartEmail2Mail;
use Dashed\DashedEcommerceCore\Mail\AbandonedCartEmail3Mail;

class SendAbandonedCartEmailJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable;

    public int $tries = 3;

    public function __construct(public readonly int $abandonedCartEmailId)
    {
    }

    public function handle(): void
    {
        $record = AbandonedCartEmail::find($this->abandonedCartEmailId);

        if (! $record || ! $record->isPending()) {
            return;
        }

        $cart = Cart::with(['items', 'items.product', 'items.product.productGroup'])->find($record->cart_id);

        // Cancel if cart no longer exists or has no items
        if (! $cart || $cart->items->isEmpty()) {
            $record->update(['cancelled_at' => now()]);
            return;
        }

        $mailable = match ($record->email_number) {
            1 => new AbandonedCartEmail1Mail($cart, $record),
            2 => new AbandonedCartEmail2Mail($cart, $record),
            3 => $this->buildEmail3($cart, $record),
            default => null,
        };

        if (! $mailable) {
            return;
        }

        Mail::to($record->email)->send($mailable);

        $record->update(['sent_at' => now()]);
    }

    private function buildEmail3(Cart $cart, AbandonedCartEmail $record): AbandonedCartEmail3Mail
    {
        $incentiveType = Customsetting::get('abandoned_cart_email3_incentive_type', null, 'amount');
        $incentiveValue = (float) Customsetting::get('abandoned_cart_email3_incentive_value', null, 5);
        $validDays = (int) Customsetting::get('abandoned_cart_email3_valid_days', null, 7);

        $discountCode = null;

        if ($incentiveType !== 'none') {
            $code = 'TERUG-' . strtoupper(Str::random(8));

            $discountCode = DiscountCode::create([
                'name' => 'Verlaten winkelwagen - ' . $record->email,
                'code' => $code,
                'type' => $incentiveType === 'percentage' ? 'percentage' : 'amount',
                'discount_amount' => $incentiveType === 'amount' ? $incentiveValue : 0,
                'discount_percentage' => $incentiveType === 'percentage' ? $incentiveValue : 0,
                'use_stock' => true,
                'stock' => 1,
                'stock_used' => 0,
                'limit_use_per_customer' => true,
                'start_date' => now(),
                'end_date' => now()->addDays($validDays),
                'site_ids' => [Sites::getActive()],
            ]);

            $record->update(['discount_code_id' => $discountCode->id]);
        }

        return new AbandonedCartEmail3Mail($cart, $record, $discountCode);
    }
}
