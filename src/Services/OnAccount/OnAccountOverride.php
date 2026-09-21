<?php

namespace Dashed\DashedEcommerceCore\Services\OnAccount;

use Illuminate\Support\Facades\DB;
use Dashed\DashedCore\Models\User;
use Dashed\DashedEcommerceCore\Models\Order;
use Dashed\DashedCore\Classes\AdminActionMonitor;
use Dashed\DashedEcommerceCore\Models\OrderLog;
use Dashed\DashedEcommerceCore\Models\PaymentMethod;

/**
 * Een beheerder zet een order op rekening, in het CMS of aan de kassa. Hij
 * mag een weigering doorzetten; dat wordt vastgelegd en gemeld, want het
 * omzeilt precies de grens die de limiet en de blokkade trekken.
 */
class OnAccountOverride
{
    public static function placeByAdmin(Order $order, User $customer, bool $override): OnAccountCheck
    {
        $method = PaymentMethod::query()
            ->where('site_id', $order->site_id)
            ->where('on_account', true)
            ->where('active', 1)
            ->whereIn('id', DB::table('dashed__payment_method_users')->where('user_id', $customer->id)->select('payment_method_id'))
            ->orderBy('order')
            ->first();

        if (! $method) {
            return new OnAccountCheck(false, OnAccount::NOT_ENABLED);
        }

        $check = OnAccount::check($customer, $method, (float) $order->total);

        if (! $check->allowed && ! $override) {
            return $check;
        }

        $payment = $order->orderPayments()->create([
            'psp' => 'own',
            'payment_method_id' => $method->id,
            'payment_method' => $method->name,
            'amount' => $order->total,
            'status' => 'pending',
        ]);

        if (! $check->allowed) {
            OrderLog::createLog(orderId: $order->id, tag: 'order.on-account.override', note: $check->reason);

            // AdminActionMonitor::alert() voegt zelf al "Door" toe (met
            // gebruikers-id), dus dat hoeft hier niet nog een keer.
            AdminActionMonitor::alert(__('Order op rekening doorgezet'), [
                __('Bestelling') => (string) $order->id,
                __('Klant') => $customer->email,
                __('Reden van weigering') => (string) $check->reason,
            ]);
        }

        OnAccountOrderPlacer::place($order, $payment, $customer);

        return new OnAccountCheck(true);
    }
}
