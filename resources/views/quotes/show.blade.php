<x-checkout-master>
    @livewire('quotes.quote-page', ['hash' => $quote->hash, 'preview' => $preview ?? false])
</x-checkout-master>
