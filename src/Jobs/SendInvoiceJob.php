<?php

namespace Dashed\DashedEcommerceCore\Jobs;

use App\Models\User;
use Illuminate\Bus\Queueable;
use Dashed\DashedCore\Classes\Mails;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Support\Facades\Mail;
use Illuminate\Queue\SerializesModels;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Dashed\DashedEcommerceCore\Models\Order;
use Dashed\DashedEcommerceCore\Classes\Orders;
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
        if (! $this->order->invoice_send_to_customer || $this->overrideCurrentStatus) {
            if ($this->sendToCustomer) {
                Orders::sendNotification($this->order, null, $this->sendByUser);
                $this->order->invoice_send_to_customer = 1;
                $this->order->save();
            }

            if ($this->sendToAdmin) {
                try {
                    foreach (Mails::getAdminNotificationEmails() as $notificationInvoiceEmail) {
                        if ($this->order->contains_pre_orders) {
                            Mail::to($notificationInvoiceEmail)->send(new AdminPreOrderConfirmationMail($this->order));
                        } else {
                            Mail::to($notificationInvoiceEmail)->send(new AdminOrderConfirmationMail($this->order));
                        }
                    }
                } catch (\Exception $e) {
                }
            }
        }
    }
}
