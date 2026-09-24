@php
    $bericht = match ($quote->status) {
        \Dashed\DashedEcommerceCore\Models\Quote::STATUS_ACCEPTED => Translation::get('closed-accepted', 'quote', 'U heeft deze offerte al geaccepteerd. Wij nemen het vanaf hier over.'),
        \Dashed\DashedEcommerceCore\Models\Quote::STATUS_REJECTED => Translation::get('closed-rejected', 'quote', 'Deze offerte is afgewezen.'),
        \Dashed\DashedEcommerceCore\Models\Quote::STATUS_WITHDRAWN => Translation::get('closed-withdrawn', 'quote', 'Deze offerte is ingetrokken.'),
        \Dashed\DashedEcommerceCore\Models\Quote::STATUS_SUPERSEDED => Translation::get('closed-superseded', 'quote', 'Er is inmiddels een nieuwere versie van deze offerte verstuurd.'),
        default => Translation::get('closed-expired', 'quote', 'Deze offerte is verlopen. Neem gerust contact met ons op voor een nieuwe.'),
    };
@endphp

<x-checkout-master>
    <section class="dq">
        @include('dashed-ecommerce-core::quotes.partials.styles', [
            'color' => \Dashed\DashedEcommerceCore\Services\Quotes\QuoteBranding::for($quote)->color(),
        ])

        <div class="dq-wrap" style="max-width: 560px">
            <div class="dq-card">
                <div class="dq-head dq-center" style="display: block">
                    <p class="dq-eyebrow">{{ Translation::get('quote', 'quote', 'Offerte') }} {{ $quote->displayNumber() }}</p>
                    @if ($quote->title)
                        <h1 class="dq-h1">{{ $quote->title }}</h1>
                    @endif
                    <p class="dq-sub" style="font-size: 17px; color: #3f3f46">{{ $bericht }}</p>

                    @if ($quote->order)
                        <a href="{{ route('dashed.frontend.proforma-checkout', ['orderHash' => $quote->order->hash]) }}" class="dq-btn">
                            {{ Translation::get('to-payment', 'quote', 'Naar de betaling') }}
                        </a>
                    @else
                        <a href="{{ url('/') }}" class="dq-btn">
                            {{ Translation::get('to-homepage', 'quote', 'Naar de homepage') }}
                        </a>
                    @endif

                    @if ($quote->status === \Dashed\DashedEcommerceCore\Models\Quote::STATUS_ACCEPTED && ($pdf = \Dashed\DashedEcommerceCore\Services\Quotes\QuotePdf::downloadUrl($quote, accepted: true)))
                        <p style="margin-top: 16px">
                            <a href="{{ $pdf }}" class="dq-link">{{ Translation::get('download-signed-pdf', 'quote', 'Getekende offerte downloaden') }}</a>
                        </p>
                    @endif
                </div>
            </div>
        </div>
    </section>
</x-checkout-master>
