@php
    use Dashed\DashedEcommerceCore\Models\Quote;
    use Dashed\DashedEcommerceCore\Services\Quotes\QuotePdf;
    use Dashed\DashedEcommerceCore\Services\Quotes\QuoteConverter;

    $order = $quote->order;
    $isAccepted = $quote->status === Quote::STATUS_ACCEPTED;
    $payUrl = $order ? QuoteConverter::redirectFor($order) : null;
    $refused = $order && $order->status === 'concept' && ! $order->is_proforma;

    $bericht = match (true) {
        $isAccepted && ! $order => Translation::get('closed-accepted-choose', 'quote', 'Bedankt voor uw akkoord. U kunt de bestelling nu direct plaatsen, of later via de link in uw mail.'),
        $isAccepted && $refused => Translation::get('closed-accepted-contact', 'quote', 'Bedankt voor uw akkoord. We nemen contact met u op om de bestelling af te ronden.'),
        $isAccepted && $payUrl !== null => Translation::get('closed-accepted-pay', 'quote', 'Bedankt voor uw akkoord. Uw bestelling staat klaar om te betalen.'),
        $isAccepted => Translation::get('closed-accepted-placed', 'quote', 'Bedankt voor uw akkoord. Uw bestelling is geplaatst.'),
        $quote->status === Quote::STATUS_REJECTED => Translation::get('closed-rejected', 'quote', 'Deze offerte is afgewezen.'),
        $quote->status === Quote::STATUS_WITHDRAWN => Translation::get('closed-withdrawn', 'quote', 'Deze offerte is ingetrokken.'),
        $quote->status === Quote::STATUS_SUPERSEDED => Translation::get('closed-superseded', 'quote', 'Er is inmiddels een nieuwere versie van deze offerte verstuurd.'),
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

                    @if ($isAccepted && ! $order)
                        <form method="POST" action="{{ route('dashed.frontend.quote-order', ['hash' => $quote->hash]) }}">
                            @csrf
                            <button type="submit" class="dq-btn">
                                {{ $quote->payment_route === Quote::ROUTE_ON_ACCOUNT
                                    ? Translation::get('order-on-account', 'quote', 'Bestelling plaatsen op factuur')
                                    : Translation::get('order-and-pay', 'quote', 'Nu bestellen en betalen') }}
                            </button>
                        </form>
                        <p style="margin-top: 12px">
                            <a href="{{ url('/') }}" class="dq-textbtn">{{ Translation::get('order-later', 'quote', 'Later') }}</a>
                        </p>
                    @elseif ($payUrl)
                        <a href="{{ $payUrl }}" class="dq-btn">{{ Translation::get('to-payment', 'quote', 'Naar de betaling') }}</a>
                    @else
                        <a href="{{ url('/') }}" class="dq-btn">{{ Translation::get('to-homepage', 'quote', 'Naar de homepage') }}</a>
                    @endif

                    @if ($isAccepted && ($pdf = QuotePdf::downloadUrl($quote, accepted: true)))
                        <p style="margin-top: 16px">
                            <a href="{{ $pdf }}" class="dq-link">{{ Translation::get('download-signed-pdf', 'quote', 'Getekende offerte downloaden') }}</a>
                        </p>
                    @endif
                </div>
            </div>
        </div>
    </section>
</x-checkout-master>
