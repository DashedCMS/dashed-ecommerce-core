<?php

namespace Dashed\DashedEcommerceCore\Jobs;

use App\Models\User;
use Illuminate\Bus\Queueable;
use Dashed\DashedCore\Classes\Mails;
use Illuminate\Queue\SerializesModels;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Dashed\DashedEcommerceCore\Models\Order;
use Dashed\DashedEcommerceCore\Classes\Orders;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Dashed\DashedCore\Notifications\AdminNotifier;
use Dashed\DashedEcommerceCore\Classes\OrderOrigins;
use Dashed\DashedEcommerceCore\Mail\AdminOrderConfirmationMail;
use Dashed\DashedEcommerceCore\Mail\AdminPreOrderConfirmationMail;

class SendInvoiceJob implements ShouldQueue, ShouldBeUnique
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public $tries = 3;
    public $timeout = 30;
    public $uniqueFor = 5;

    public Order $order;
    public bool $sendToCustomer = true;
    public bool $sendToAdmin = true;
    public bool $overrideCurrentStatus = false;
    public ?User $sendByUser = null;

    public function uniqueId(): string
    {
        return $this->order->id;
    }

    /**
     * Create a new job instance.
     */
    public function __construct(Order $order, ?User $sendByUser = null, bool $sendToCustomer = true, $sendToAdmin = true, $overrideCurrentStatus = false)
    {
        $this->order = $order;
        $this->sendByUser = $sendByUser;
        $this->sendToCustomer = $sendToCustomer;
        $this->sendToAdmin = $sendToAdmin;
        $this->overrideCurrentStatus = $overrideCurrentStatus;
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        $canSendCustomer = ! $this->order->invoice_send_to_customer || $this->overrideCurrentStatus;

        if ($this->sendToCustomer && $canSendCustomer) {
            if ($this->order->email) {
                Orders::sendNotification($this->order, null, $this->sendByUser);
            }
            $this->order->invoice_send_to_customer = 1;
            $this->order->save();
        }

        if ($this->sendToAdmin && OrderOrigins::shouldNotifyAdmin($this->order->order_origin, null, $this->order->site_id)) {
            $channels = OrderOrigins::channelsFor($this->order->order_origin, $this->order->site_id);

            try {
                foreach (Mails::getAdminNotificationEmails() as $notificationInvoiceEmail) {
                    if ($this->order->contains_pre_orders) {
                        AdminNotifier::send(new AdminPreOrderConfirmationMail($this->order), $notificationInvoiceEmail, $channels);
                    } else {
                        AdminNotifier::send(new AdminOrderConfirmationMail($this->order), $notificationInvoiceEmail, $channels);
                    }
                }
            } catch (\Exception $e) {
            }
        }
    }
}
