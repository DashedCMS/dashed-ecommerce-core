<?php

namespace Dashed\DashedEcommerceCore\Livewire\Frontend;

use Throwable;
use Livewire\Component;
use Livewire\Attributes\Locked;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\RateLimiter;
use Dashed\DashedEcommerceCore\Classes\Mails;
use Dashed\DashedEcommerceCore\Models\OrderLog;
use Dashed\DashedEcommerceCore\Models\OrderReturn;
use Dashed\DashedCore\Notifications\AdminNotifier;
use Dashed\DashedEcommerceCore\Support\ReturnNotifier;
use Dashed\DashedEcommerceCore\Models\OrderReturnMessage;
use Dashed\DashedEcommerceCore\Mail\AdminNewOrderReturnReplyMail;

class OrderReturnThread extends Component
{
    #[Locked]
    public string $hash = '';

    #[Locked]
    public ?int $orderReturnId = null;

    public string $reply = '';

    public ?string $rateLimitMessage = null;

    protected function rules(): array
    {
        return [
            'reply' => 'required|string|max:2000',
        ];
    }

    public function mount(string $hash): void
    {
        $this->hash = $hash;
        $this->orderReturnId = OrderReturn::query()->where('hash', $hash)->value('id');
    }

    public function send(): void
    {
        $this->rateLimitMessage = null;

        $return = $this->resolveReturn();
        if (! $return) {
            return;
        }

        $this->validate();

        $throttleKey = 'return-reply:' . $this->hash . '|' . request()->ip();
        if (RateLimiter::tooManyAttempts($throttleKey, 5)) {
            $this->rateLimitMessage = 'Te veel berichten. Probeer het over ' . RateLimiter::availableIn($throttleKey) . ' seconden opnieuw.';

            return;
        }
        RateLimiter::hit($throttleKey, 60);

        $return->messages()->create([
            'sender' => OrderReturnMessage::SENDER_CUSTOMER,
            'message' => $this->reply,
        ]);

        $this->notifyAdmin($return, $this->reply);

        $this->reply = '';
    }

    protected function notifyAdmin(OrderReturn $return, string $message): void
    {
        try {
            AdminNotifier::send(new AdminNewOrderReturnReplyMail($return, $message), Mails::getAdminNotificationEmails());
        } catch (Throwable $e) {
            Log::warning('OrderReturnThread kon de beheerders niet notificeren: ' . $e->getMessage());
        }

        try {
            $log = new OrderLog();
            $log->order_id = $return->order_id;
            $log->tag = 'order.return-customer-replied';
            $log->save();
        } catch (Throwable $e) {
            report($e);
        }

        ReturnNotifier::customerReplied($return);
    }

    protected function resolveReturn(): ?OrderReturn
    {
        if (! $this->orderReturnId || $this->hash === '') {
            return null;
        }

        // De hash is de onvervalsbare sleutel: altijd meecontroleren, zodat een
        // (theoretisch) gemanipuleerde orderReturnId nooit een ander retour raakt.
        return OrderReturn::query()
            ->where('id', $this->orderReturnId)
            ->where('hash', $this->hash)
            ->first();
    }

    public function render()
    {
        $return = $this->resolveReturn();

        return view('dashed-ecommerce-core::livewire.frontend.order-return-thread', [
            'orderReturn' => $return,
            'messages' => $return ? $return->messages()->get() : collect(),
        ]);
    }
}
