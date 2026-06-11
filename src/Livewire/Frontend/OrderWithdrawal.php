<?php

namespace Dashed\DashedEcommerceCore\Livewire\Frontend;

use Livewire\Component;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\RateLimiter;
use Dashed\DashedEcommerceCore\Models\Order;
use Dashed\DashedEcommerceCore\Models\OrderLog;
use Dashed\DashedEcommerceCore\Models\OrderReturn;
use Dashed\DashedEcommerceCore\Mail\OrderReturn\OrderReturnRequestedMail;
use Dashed\DashedEcommerceCore\Services\OrderReturn\OrderLookupService;

class OrderWithdrawal extends Component
{
    public string $orderNumber = '';
    public string $email = '';
    public string $customerNote = '';

    public int $step = 1;
    public ?int $foundOrderId = null;
    public bool $notFound = false;
    public bool $completed = false;
    public ?string $rateLimitMessage = null;

    /** @var array<string, mixed> */
    public array $blockData = [];

    public function mount(array $blockData = []): void
    {
        $this->blockData = $blockData;
    }

    public function search(): void
    {
        $this->notFound = false;
        $this->rateLimitMessage = null;

        $this->validate([
            'orderNumber' => 'required|string|max:191',
            'email' => 'required|email|max:191',
        ]);

        $throttleKey = 'order-withdrawal:' . request()->ip();
        if (RateLimiter::tooManyAttempts($throttleKey, 5)) {
            $seconds = RateLimiter::availableIn($throttleKey);
            $this->rateLimitMessage = __('Te veel pogingen. Probeer het over :seconds seconden opnieuw.', ['seconds' => $seconds]);

            return;
        }
        RateLimiter::hit($throttleKey, 60);

        $order = app(OrderLookupService::class)->find($this->orderNumber, $this->email);

        if (! $order) {
            $this->notFound = true;

            return;
        }

        $this->foundOrderId = $order->id;
        $this->step = 2;
    }

    public function confirm(): void
    {
        $this->rateLimitMessage = null;

        $confirmThrottleKey = 'order-withdrawal-confirm:' . request()->ip();
        if (RateLimiter::tooManyAttempts($confirmThrottleKey, 10)) {
            $seconds = RateLimiter::availableIn($confirmThrottleKey);
            $this->rateLimitMessage = __('Te veel pogingen. Probeer het over :seconds seconden opnieuw.', ['seconds' => $seconds]);

            return;
        }
        RateLimiter::hit($confirmThrottleKey, 60);

        $order = app(OrderLookupService::class)->find($this->orderNumber, $this->email);

        if (! $order || ($this->foundOrderId && $order->id !== $this->foundOrderId)) {
            $this->step = 1;
            $this->foundOrderId = null;
            $this->notFound = true;

            return;
        }

        DB::transaction(function () use ($order) {
            $existing = OrderReturn::where('order_id', $order->id)
                ->open()
                ->lockForUpdate()
                ->first();

            if ($existing) {
                return;
            }

            $return = OrderReturn::create([
                'order_id' => $order->id,
                'site_id' => $order->site_id,
                'email' => $order->email,
                'customer_note' => $this->customerNote ?: null,
            ]);

            $order->update(['retour_status' => 'waiting_for_return']);

            $log = new OrderLog();
            $log->order_id = $order->id;
            $log->tag = 'order.return-requested';
            $log->save();

            Mail::to($order->email)->queue(new OrderReturnRequestedMail($return));
        });

        $this->completed = true;
    }

    public function getOrderProperty(): ?Order
    {
        return $this->foundOrderId ? Order::with('orderProducts')->find($this->foundOrderId) : null;
    }

    public function render()
    {
        return view('dashed-ecommerce-core::livewire.frontend.order-withdrawal');
    }
}
