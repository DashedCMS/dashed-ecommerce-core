<div>
    <div class="dvo-line">
        <div class="dvo-grow">
            <div class="dvo-strong">{{ $order->shippingMethod->name ?? __('Geen verzendmethode') }}</div>
            <div class="dvo-small dvo-muted">{{ __('Verzendmethode') }}</div>
        </div>
    </div>
    @forelse($order->trackAndTraces as $trackAndTrace)
        <div class="dvo-line">
            <div class="dvo-grow">
                <div class="dvo-strong">{{ $trackAndTrace->delivery_company ?: __('Track & Trace') }}</div>
                <div class="dvo-small dvo-muted">
                    {{ __('Aangemaakt op :datum', ['datum' => $trackAndTrace->created_at?->format('d-m-Y H:i')]) }}
                    @if($trackAndTrace->supplier)
                        · {{ __('Via: :partij', ['partij' => $trackAndTrace->supplier]) }}
                    @endif
                </div>
            </div>
            @if($trackAndTrace->url)
                <x-filament::link :href="$trackAndTrace->url" target="_blank" icon="heroicon-o-truck">{{ $trackAndTrace->code }}</x-filament::link>
            @else
                <span class="dvo-mono">{{ $trackAndTrace->code }}</span>
            @endif
        </div>
    @empty
        <div class="dvo-line dvo-muted">{{ __('Nog geen track & trace bij deze bestelling.') }}</div>
    @endforelse
</div>
