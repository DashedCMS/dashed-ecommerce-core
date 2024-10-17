<?php

namespace Dashed\DashedEcommerceCore\Jobs;

use Dashed\DashedEcommerceCore\Models\OrderPayment;
use Illuminate\Bus\Queueable;
use Illuminate\Support\Facades\DB;
use Dashed\DashedCore\Classes\Locales;
use Illuminate\Queue\SerializesModels;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Dashed\DashedEcommerceCore\Models\Product;
use Dashed\DashedEcommerceCore\Models\ProductFilterOption;

class CheckPinTerminalPaymentStatusJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public $tries = 5;
    public $timeout = 1200;

    public OrderPayment $orderPayment;

    /**
     * Create a new job instance.
     */
    public function __construct(OrderPayment $orderPayment)
    {
        $this->orderPayment = $orderPayment;
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        $this->orderPayment->refresh();

        if ($this->orderPayment->status != 'pending') {
            return;
        }

        $transactionStatus = ecommerce()->builder('paymentServiceProviders')[$this->orderPayment->psp]['class']::getPinTerminalOrderStatus($this->orderPayment);

        if ($transactionStatus == 'cancelled' || $transactionStatus == 'paid') {
            $newPaymentStatus = $this->orderPayment->changeStatus($transactionStatus);
            $this->orderPayment->order->changeStatus($newPaymentStatus);
        } else {
            self::dispatch($this->orderPayment)->delay(now()->addSeconds(1));
        }
    }
}
