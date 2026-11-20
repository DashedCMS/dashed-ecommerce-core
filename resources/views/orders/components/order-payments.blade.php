<div>
    <ul class="au-od-list">
        @forelse($order->orderPayments as $orderPayment)
            <li class="au-od-line">
                <div class="au-od-grow">
                    <div class="au-od-label au-od-muted">{{ __('Betaalmethode') }}</div>
                    <div class="au-od-strong">{{ $orderPayment->payment_method ?: ($orderPayment->paymentMethod->name ?? '-') }}</div>
                    <div class="au-od-small au-od-muted">
                        {{ $orderPayment->created_at?->format('d-m-Y H:i') }}
                        @if($orderPayment->psp)
                            · {{ $orderPayment->psp }}
                        @endif
                        @if($orderPayment->psp_id)
                            · <span class="au-od-mono">{{ $orderPayment->psp_id }}</span>
                        @endif
                    </div>
                    @if(filled($note = data_get($orderPayment->getAttribute('attributes'), 'note')))
                        <div class="au-od-small au-od-gap-top">{{ $note }}</div>
                    @endif
                </div>
                <x-dashed-core::aurora.badge :tone="\Dashed\DashedEcommerceCore\Classes\AuroraTone::from($this->statusColor($orderPayment->status))">{{ $this->statusLabel($orderPayment->status) }}</x-dashed-core::aurora.badge>
                <div class="au-od-price au-od-strong">{{ \Dashed\DashedEcommerceCore\Classes\CurrencyHelper::formatPrice($orderPayment->amount) }}</div>
                @if(filled($orderPayment->psp_request) || filled($orderPayment->psp_response))
                    {{ ($this->pspDataAction)(['payment' => $orderPayment->id]) }}
                @endif
            </li>
        @empty
            <li class="au-od-line au-od-muted">{{ __('Nog geen betalingen bij deze bestelling.') }}</li>
        @endforelse
    </ul>

    <x-filament-actions::modals />
</div>
