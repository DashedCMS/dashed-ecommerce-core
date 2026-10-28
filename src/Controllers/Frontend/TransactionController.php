<?php

namespace Dashed\DashedEcommerceCore\Controllers\Frontend;

use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\View;
use Illuminate\Support\Facades\Cache;
use Dashed\DashedEcommerceCore\Models\Order;
use Dashed\DashedEcommerceCore\Models\OrderLog;
use Dashed\DashedTranslations\Models\Translation;
use Dashed\DashedEcommerceCore\Models\OrderPayment;
use Dashed\DashedEcommerceCore\Classes\ShoppingCart;
use Illuminate\Contracts\Cache\LockTimeoutException;
use Dashed\DashedEcommerceCore\Livewire\Frontend\Orders\ViewOrder;
use Dashed\DashedEcommerceCore\Services\Payments\PspStatusResolver;
use Dashed\DashedEcommerceCore\Services\Payments\ReturnPaymentLookup;

class TransactionController extends Controller
{
    public function complete(Request $request)
    {
        // Op de hash van de betaling, psp_id alleen kort na het betalen. Zie
        // ReturnPaymentLookup; de exchange hieronder zoekt bewust breder.
        $orderPayment = ReturnPaymentLookup::fromRequest($request);

        if (! $orderPayment) {
            return redirect('/')->with('error', Translation::get('order-not-found', 'checkout', 'The order could not be found'));
        }

        $order = $orderPayment->order;

        $hasAccessToOrder = false;

        if ($order) {
            $hasAccessToOrder = true;
        }

        if (! $hasAccessToOrder) {
            return redirect('/')->with('error', Translation::get('order-not-found', 'checkout', 'The order could not be found'));
        }

        $lock = Cache::lock('order.check-payment.' . $order->id, 10);

        try {
            if ($lock->get()) {
                // Terugbetaald: de betaling blijft 'paid', de creditorder van de
                // retour krijgt de terugbetaling. Zie PspStatusResolver.
                $newPaymentStatus = PspStatusResolver::apply($orderPayment, $order, false);

                if ($newPaymentStatus !== null) {
                    $order->changeStatus($newPaymentStatus);
                    $order->sendGAEcommerceHit();
                }
            }
        } catch (LockTimeoutException $e) {
            return 'timeout exception';
        } finally {
            $lock->release();
        }

        if ($order->status == 'pending') {
            return redirect('/')->with('error', Translation::get('order-status-pending', 'checkout', 'Your order is still pending'));
        }

        if ($order->status == 'cancelled') {
            return ShoppingCart::cancelledPaymentRedirect($order);
        }

        if (view()->exists('dashed.orders.view-order')) {
            seo()->metaData('metaTitle', Translation::get('complete-page-meta-title', 'complete-order', 'Your order'));

            View::share('order', $order);

            return view('dashed-core::layouts.livewire-master', [
                'livewireComponent' => ViewOrder::class,
                'parameters' => [
                    'order' => $order,
                ],
            ]);

            return view()->exists('dashed.orders.view') ? view(config('dashed-core.site_theme', 'dashed') . '.orders.view') : view(config('dashed-core.site_theme', 'dashed') . '.checkout.complete');
        } else {
            return $this->pageNotFound();
        }
    }

    public function exchange(Request $request)
    {
        $possibleIdValues = [
            'orderId',
            'order_id',
            'paymentId',
            'id',
            'transactionid',
        ];

        $orderPayment = null;

        foreach ($possibleIdValues as $possibleIdValue) {
            if (! $orderPayment) {
                $paymentId = $request->get($possibleIdValue);
                if ($paymentId) {
                    $orderPayment = OrderPayment::where('psp_id', $paymentId)->orWhere('hash', $paymentId)->first();
                }
            }
        }

        if (! $orderPayment) {
            return 'order not found';
        }

        $order = $orderPayment->order;

        $lock = Cache::lock('order.check-payment.' . $order->id, 10);

        try {
            if ($lock->get()) {
                if ($orderPayment->psp == 'own') {
                    // Alleen een order die nog op pending staat. Een herhaalde
                    // aanroep met dezelfde hash (bijvoorbeeld op een deels of
                    // helemaal betaalde order op rekening) mag hem niet
                    // terugzetten naar waiting_for_confirmation.
                    if ($order->status === 'pending') {
                        $order->changeStatus('waiting_for_confirmation');
                    }
                } else {
                    PspStatusResolver::apply($orderPayment, $order, true);
                }
            }
        } catch (LockTimeoutException $e) {
            OrderLog::createLog(orderId: $order->id, note: 'Order status could not be updated due to a lock timeout exception');

            return 'timeout exception';
        } finally {
            $lock->release();
        }

        if ($order->status == 'paid') {
            echo "TRUE| Paid";
        } elseif ($order->status == 'cancelled') {
            echo "TRUE| Canceled";
        } else {
            echo "TRUE| Pending";
        }

        return $order->status;
    }
}
