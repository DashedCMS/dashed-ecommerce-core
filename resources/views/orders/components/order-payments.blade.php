<div>
    @forelse($order->orderPayments as $orderPayment)
        <div class="dvo-line">
            <div class="dvo-grow">
                <div class="dvo-strong">{{ $orderPayment->payment_method ?: ($orderPayment->paymentMethod->name ?? '-') }}</div>
                <div class="dvo-small dvo-muted">
                    {{ $orderPayment->created_at?->format('d-m-Y H:i') }}
                    @if($orderPayment->psp)
                        · {{ $orderPayment->psp }}
                    @endif
                    @if($orderPayment->psp_id)
                        · <span class="dvo-mono">{{ $orderPayment->psp_id }}</span>
                    @endif
                </div>
                @if(filled($note = data_get($orderPayment->getAttribute('attributes'), 'note')))
                    <div class="dvo-small dvo-gap-top">{{ $note }}</div>
                @endif
            </div>
            <x-filament::badge :color="$this->statusColor($orderPayment->status)">{{ $this->statusLabel($orderPayment->status) }}</x-filament::badge>
            <div class="dvo-price dvo-strong">{{ \Dashed\DashedEcommerceCore\Classes\CurrencyHelper::formatPrice($orderPayment->amount) }}</div>
            @if(filled($orderPayment->psp_request) || filled($orderPayment->psp_response))
                {{ ($this->pspDataAction)(['payment' => $orderPayment->id]) }}
            @endif
        </div>
    @empty
        <div class="dvo-line dvo-muted">{{ __('Nog geen betalingen bij deze bestelling.') }}</div>
    @endforelse

    <x-filament-actions::modals />
</div>
