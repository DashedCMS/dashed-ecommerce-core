<?php

namespace Dashed\DashedEcommerceCore\Livewire\Frontend\Orders;

use Dashed\DashedEcommerceCore\Models\OrderPayment;
use Dashed\DashedTranslations\Models\Translation;
use Illuminate\Contracts\Cache\LockTimeoutException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\View;
use Livewire\Component;
use Dashed\DashedEcommerceCore\Models\Order;
use Illuminate\Database\Eloquent\Collection;

class ViewOrder extends Component
{
    public Order $order;
    public Collection $orderProducts;
    public Collection $notes;

    public function mount()
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
                $paymentId = request()->get($possibleIdValue);
                if ($paymentId) {
                    $orderPayment = OrderPayment::where('psp_id', $paymentId)->orWhere('hash', $paymentId)->first();
                }
            }
        }

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
                foreach (ecommerce()->builder('paymentServiceProviders') ?: [] as $pspId => $psp) {
                    if ($orderPayment->psp == $pspId) {
                        $newStatus = $psp['class']::getOrderStatus($orderPayment);
                        $newPaymentStatus = $orderPayment->changeStatus($newStatus);
                    }
                }

                if (isset($newPaymentStatus)) {
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
            return redirect('/')->with('error', Translation::get('order-status-cancelled', 'checkout', 'Your order is cancelled'));
        }

        $this->order = $order;
        $this->orderProducts = $order->orderProducts;
        $this->notes = $order->publicLogs;
    }

    public function render()
    {
        return view(env('SITE_THEME', 'dashed') . '.orders.view-order');
    }
}
