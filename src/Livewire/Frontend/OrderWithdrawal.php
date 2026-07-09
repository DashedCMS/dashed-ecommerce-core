<?php

namespace Dashed\DashedEcommerceCore\Livewire\Frontend;

use Throwable;
use Livewire\Component;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Dashed\DashedCore\Classes\Mails;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\RateLimiter;
use Dashed\DashedEcommerceCore\Classes\SKUs;
use Dashed\DashedEcommerceCore\Models\Order;
use Dashed\DashedEcommerceCore\Models\OrderLog;
use Dashed\DashedTranslations\Models\Translation;
use Dashed\DashedCore\Notifications\AdminNotifier;
use Dashed\DashedEcommerceCore\Models\OrderReturn;
use Dashed\DashedEcommerceCore\Models\ReturnReason;
use Dashed\DashedEcommerceCore\Models\OrderReturnLine;
use Dashed\DashedEcommerceCore\Support\ReturnNotifier;
use Dashed\DashedEcommerceCore\Mail\AdminNewOrderReturnMail;
use Dashed\DashedEcommerceCore\Services\OrderReturn\OrderLookupService;
use Dashed\DashedEcommerceCore\Mail\OrderReturn\OrderReturnRequestedMail;

class OrderWithdrawal extends Component
{
    public string $orderNumber = '';
    public string $email = '';
    public string $customerNote = '';

    public int $step = 1;
    public ?int $foundOrderId = null;
    public bool $notFound = false;
    public bool $completed = false;
    public ?string $completedAt = null;
    public ?string $completedOrderLabel = null;
    public ?string $rateLimitMessage = null;

    /** @var array<int, array{selected: bool, quantity: int, reason_id: int|null, note: string}> */
    public array $selectedLines = [];

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
            $this->rateLimitMessage = Translation::get('return-rate-limited', 'returns', 'Te veel pogingen. Probeer het over :seconds: seconden opnieuw.', 'text', ['seconds' => $seconds]);

            return;
        }
        RateLimiter::hit($throttleKey, 60);

        $order = app(OrderLookupService::class)->find($this->orderNumber, $this->email);

        if (! $order) {
            $this->notFound = true;

            return;
        }

        $this->foundOrderId = $order->id;
        $order->loadMissing('orderProducts.product');
        $this->initSelectedLines($order);
        $this->step = 2;
    }

    protected function initSelectedLines(Order $order): void
    {
        $this->selectedLines = [];
        foreach ($order->orderProducts as $product) {
            if (in_array($product->sku, SKUs::nonReturnable(), true)) {
                continue;
            }

            $this->selectedLines[$product->id] = [
                'selected' => false,
                'quantity' => (int) ($product->quantity ?: 1),
                'reason_id' => null,
                'note' => '',
            ];
        }
    }

    public function getReturnableProductsProperty()
    {
        $order = $this->order;

        if (! $order) {
            return collect();
        }

        return $order->orderProducts->reject(fn ($product) => in_array($product->sku, SKUs::nonReturnable(), true));
    }

    public function selectAllLines(): void
    {
        foreach ($this->selectedLines as $productId => $line) {
            $this->selectedLines[$productId]['selected'] = true;
        }
    }

    public function getReasonsProperty()
    {
        return ReturnReason::active()->get();
    }

    public function confirm(): void
    {
        $this->rateLimitMessage = null;

        $confirmThrottleKey = 'order-withdrawal-confirm:' . request()->ip();
        if (RateLimiter::tooManyAttempts($confirmThrottleKey, 10)) {
            $seconds = RateLimiter::availableIn($confirmThrottleKey);
            $this->rateLimitMessage = Translation::get('return-rate-limited', 'returns', 'Te veel pogingen. Probeer het over :seconds: seconden opnieuw.', 'text', ['seconds' => $seconds]);

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

        $order->loadMissing('orderProducts');
        $productsById = $order->orderProducts
            ->reject(fn ($product) => in_array($product->sku, SKUs::nonReturnable(), true))
            ->keyBy('id');

        $chosen = [];
        foreach ($this->selectedLines as $productId => $line) {
            if (empty($line['selected'])) {
                continue;
            }
            $product = $productsById->get($productId);
            if (! $product) {
                continue;
            }
            $maxQty = (int) ($product->quantity ?: 1);
            $qty = (int) ($line['quantity'] ?? 1);
            if ($qty < 1 || $qty > $maxQty) {
                $this->addError('lines', Translation::get('return-quantity-invalid', 'returns', 'Het aantal voor :product: moet tussen 1 en :max: liggen.', 'text', ['product' => $product->name, 'max' => $maxQty]));

                return;
            }
            $reasonId = $line['reason_id'] ?? null;
            $reasonId = ($reasonId === null || $reasonId === '') ? null : (int) $reasonId;

            $chosen[] = [
                'order_product_id' => $product->id,
                'quantity' => $qty,
                'reason_id' => $reasonId,
                'note' => (string) ($line['note'] ?? ''),
            ];
        }

        if (empty($chosen)) {
            $this->addError('lines', Translation::get('return-select-one', 'returns', 'Selecteer minimaal een product om te retourneren.'));

            return;
        }

        $resolvedReturn = null;
        $createdReturn = null;

        DB::transaction(function () use ($order, $chosen, &$resolvedReturn, &$createdReturn) {
            $existing = OrderReturn::where('order_id', $order->id)
                ->open()
                ->lockForUpdate()
                ->first();

            if ($existing) {
                $resolvedReturn = $existing;

                return;
            }

            $return = OrderReturn::create([
                'order_id' => $order->id,
                'site_id' => $order->site_id,
                'email' => $order->email,
                'customer_note' => $this->customerNote ?: null,
            ]);

            $resolvedReturn = $return;
            $createdReturn = $return;

            $activeReasonIds = ReturnReason::active()->pluck('id')->all();
            foreach ($chosen as $row) {
                OrderReturnLine::create([
                    'order_return_id' => $return->id,
                    'order_product_id' => $row['order_product_id'],
                    'quantity' => $row['quantity'],
                    'return_reason_id' => in_array($row['reason_id'], $activeReasonIds, true) ? $row['reason_id'] : null,
                    'reason_note' => $row['note'] !== '' ? $row['note'] : null,
                ]);
            }

            $order->update(['retour_status' => 'waiting_for_return']);

            $log = new OrderLog();
            $log->order_id = $order->id;
            $log->tag = 'order.return-requested';
            $log->save();

            Mail::to($order->email)->queue(new OrderReturnRequestedMail($return));

            try {
                AdminNotifier::send(new AdminNewOrderReturnMail($return), Mails::getAdminNotificationEmails());
            } catch (Throwable $notifyError) {
                Log::warning('OrderWithdrawal could not notify admins of new return', [
                    'order_id' => $order->id,
                    'error' => $notifyError->getMessage(),
                ]);
            }

            if (app(\Dashed\DashedEcommerceCore\Services\OrderReturn\ReturnAutoAcceptEvaluator::class)->shouldAutoAccept($return->fresh('lines'))) {
                $return->auto_accepted = true;
                $return->save();
                $return->approve();
            }
        });

        // App-push: auto-goedgekeurd krijgt zijn eigen melding, anders 'nieuw
        // verzoek'. Alleen voor een NIEUW aangemaakte retour (niet als er al een
        // open retour was) en buiten de transactie (best-effort).
        if ($createdReturn) {
            $createdReturn->auto_accepted
                ? ReturnNotifier::autoApproved($createdReturn)
                : ReturnNotifier::requested($createdReturn);
        }

        $this->completedAt = optional($resolvedReturn?->requested_at)->format('d-m-Y H:i');
        $this->completedOrderLabel = $order->invoice_id ?: (string) $order->id;
        $this->completed = true;
    }

    public function getOrderProperty(): ?Order
    {
        return $this->foundOrderId ? Order::with('orderProducts.product')->find($this->foundOrderId) : null;
    }

    public function render()
    {
        return view('dashed-ecommerce-core::livewire.frontend.order-withdrawal');
    }
}
