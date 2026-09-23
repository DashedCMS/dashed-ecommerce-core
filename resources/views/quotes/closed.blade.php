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
    <section class="py-[clamp(40px,6vw,80px)]">
        <x-container>
            <div class="mx-auto max-w-md text-center">
                <h1 class="font-display text-[clamp(26px,3vw,38px)] text-black">
                    {{ Translation::get('quote', 'quote', 'Offerte') }} {{ $quote->displayNumber() }}
                </h1>
                <p class="mt-4">{{ $bericht }}</p>

                @if ($quote->order)
                    <a href="{{ route('dashed.frontend.proforma-checkout', ['orderHash' => $quote->order->hash]) }}"
                       class="button button--primary mt-8 inline-flex">
                        {{ Translation::get('to-payment', 'quote', 'Naar de betaling') }}
                    </a>
                @else
                    <a href="{{ url('/') }}" class="button button--primary mt-8 inline-flex">
                        {{ Translation::get('to-homepage', 'quote', 'Naar de homepage') }}
                    </a>
                @endif
            </div>
        </x-container>
    </section>
</x-checkout-master>
