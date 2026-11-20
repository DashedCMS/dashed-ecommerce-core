<div>
    <ul class="au-od-list">
        <li class="au-od-line">
            <div class="au-od-grow">
                <div class="au-od-label au-od-muted">{{ __('Verzendmethode') }}</div>
                <div class="au-od-strong">{{ $order->shippingMethod->name ?? __('Geen verzendmethode') }}</div>
            </div>
        </li>
        @forelse($order->trackAndTraces as $trackAndTrace)
            <li class="au-od-line">
                <div class="au-od-grow">
                    <div class="au-od-strong">{{ $trackAndTrace->delivery_company ?: __('Track & Trace') }}</div>
                    <div class="au-od-small au-od-muted">
                        {{ __('Aangemaakt op :datum', ['datum' => $trackAndTrace->created_at?->format('d-m-Y H:i')]) }}
                        @if($trackAndTrace->supplier)
                            · {{ __('Via: :partij', ['partij' => $trackAndTrace->supplier]) }}
                        @endif
                    </div>
                    <div class="au-od-small au-od-gap-top">
                        {{ __('Track & trace') }}:
                        @if($trackAndTrace->url)
                            <a class="au-od-link au-od-mono" href="{{ $trackAndTrace->url }}" target="_blank" rel="noopener">{{ $trackAndTrace->code }}</a>
                        @else
                            <span class="au-od-mono">{{ $trackAndTrace->code }}</span>
                        @endif
                    </div>
                </div>
            </li>
        @empty
            <li class="au-od-line au-od-muted">{{ __('Nog geen track & trace bij deze bestelling.') }}</li>
        @endforelse
    </ul>
</div>
