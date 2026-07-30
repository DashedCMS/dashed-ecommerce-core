<?php

namespace Dashed\DashedEcommerceCore\Mail;

use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;
use Illuminate\Foundation\Bus\Dispatchable;
use Dashed\DashedEcommerceCore\Models\Order;

class OrderModifiedMail extends Mailable
{
    use Dispatchable;
    use SerializesModels;

    public function __construct(
        public Order $order,
        public ?string $note = null,
    ) {
    }

    public function build(): self
    {
        return $this
            ->subject('Je bestelling is aangepast')
            ->view('dashed-ecommerce-core::emails.order-modified', [
                'order' => $this->order,
                'note' => $this->note,
                'outstandingAmount' => $this->order->outstandingAmount(),
                'overpaidAmount' => $this->order->overpaidAmount(),
                'paymentUrl' => url('/pay/order/' . $this->order->hash . '/remainder'),
            ]);
    }
}
