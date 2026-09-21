<?php

namespace Dashed\DashedEcommerceCore\Services\OnAccount;

use Dashed\DashedCore\Models\User;
use Dashed\DashedEcommerceCore\Models\Order;
use Dashed\DashedEcommerceCore\Models\OrderLog;
use Illuminate\Support\Facades\Cache;
use Illuminate\Contracts\Cache\LockTimeoutException;
use Dashed\DashedEcommerceCore\Models\OrderPayment;
use Dashed\DashedEcommerceCore\Models\PaymentMethod;

/**
 * Zet een order op rekening. De order loopt daarna de gewone weg van
 * waiting_for_confirmation (factuur, voorraad, fulfilment, boekhoudpush),
 * maar zonder de betaalde nulbetaling die own-methodes anders krijgen:
 * die maakte een openstaand bedrag onberekenbaar.
 */
class OnAccountOrderPlacer
{
    public static function place(Order $order, OrderPayment $payment, User $customer): void
    {
        $payment->amount = $order->total;
        $payment->status = 'pending';
        $payment->save();

        $order->payment_due_at = now()->addDays(OnAccountSettings::termDaysFor($customer));
        $order->save();

        OrderLog::createLog(orderId: $order->id, tag: 'order.on-account.placed');

        $order->changeStatus('waiting_for_confirmation');
    }

    /**
     * Sluit de pending placeholder af als er niets meer open staat. Een
     * directe update: changeStatus() op de betaling zou de orderstatus
     * aanraken en events vuren.
     */
    public static function settle(Order $order): void
    {
        if (! $order->payment_due_at || $order->outstandingAmount() > 0.004) {
            return;
        }

        OrderPayment::query()
            ->where('order_id', $order->id)
            ->where('psp', 'own')
            ->where('status', 'pending')
            ->update(['status' => 'cancelled']);
    }

    /**
     * De own-tak van een checkout. Geeft true als de order op rekening is
     * gezet; false betekent: gewone own-afhandeling (betaalde nulbetaling).
     * Weigert met een exception als de controle bij het plaatsen alsnog
     * faalt, onder een lock per klant zodat twee orders tegelijk niet samen
     * over de limiet gaan.
     *
     * @throws \Dashed\DashedEcommerceCore\Services\OnAccount\OnAccountRefused
     */
    public static function placeFromCheckout(Order $order, OrderPayment $payment, PaymentMethod $method, ?User $customer): bool
    {
        if (! $method->on_account) {
            return false;
        }

        $lock = Cache::lock('on-account:'.($customer?->id ?? 0), 10);

        try {
            return $lock->block(5, function () use ($order, $payment, $method, $customer) {
                $check = OnAccount::check($customer, $method, (float) $order->total);
                if (! $check->allowed) {
                    throw new OnAccountRefused($check);
                }

                self::place($order, $payment, $customer);

                return true;
            });
        } catch (LockTimeoutException) {
            // Een tweede order van dezelfde klant tegelijk: weigeren zoals elke
            // andere weigering, zodat de checkout de order afsluit in plaats
            // van een 500 met een verweesde pending order.
            throw new OnAccountRefused(
                new OnAccountCheck(false),
                __('Er wordt al een bestelling op rekening verwerkt, probeer het zo opnieuw.'),
            );
        }
    }
}
