{{--
    /bijbestellen/{order} (OrderAddOnController): "Nog iets vergeten?" na een bestelling.
    Standaardweergave; een thema overschrijft hem met <site_theme>/order-add-ons/show.blade.php.
    Variabelen: $order, $open, $reason, $orderNumber, $untilText, $alreadyAdded.
--}}
<x-master>
    <section class="py-16">
        <x-container>
            <div style="max-width: 760px; margin: 0 auto;">
                <p class="text-sm font-bold uppercase tracking-wide opacity-60">{{ str_replace(':order:', $orderNumber, Translation::get('pagina-kop-klein', 'bijbestellen', 'Bij bestelling #:order:')) }}</p>
                <h1 class="mt-2 text-3xl font-bold">{{ Translation::get('pagina-titel', 'bijbestellen', 'Nog iets vergeten?') }}</h1>

                @if($open)
                    <p class="mt-4 opacity-80">
                        {{ str_replace([':order:', ':deadline:'], [$orderNumber, $untilText], Translation::get('pagina-intro-tot', 'bijbestellen', 'Voeg het toe en het gaat zonder verzendkosten mee in de doos van bestelling #:order:. Dat kan :deadline:.')) }}
                    </p>
                    @if($alreadyAdded)
                        <p class="mt-3 text-sm font-semibold">{{ Translation::get('pagina-al-besteld', 'bijbestellen', 'Je hebt al iets bijbesteld. Nog iets erbij kan zolang we niet hebben ingepakt.') }}</p>
                    @endif

                    <livewire:order-add-ons.suggestions :order-id="$order->id"/>
                @else
                    <div class="mt-8 rounded-lg border p-6" data-order-add-on="closed">
                        <p class="font-semibold">
                            @if($reason === \Dashed\DashedEcommerceCore\Classes\OrderAddOns\Offer::REASON_STATUS_CLOSED)
                                {{ Translation::get('pagina-dicht-ingepakt', 'bijbestellen', 'Je bestelling is al ingepakt, er kan niets meer bij in de doos.') }}
                            @elseif($reason === \Dashed\DashedEcommerceCore\Classes\OrderAddOns\Offer::REASON_EXPIRED)
                                {{ Translation::get('pagina-dicht-verlopen', 'bijbestellen', 'De tijd om iets toe te voegen aan deze bestelling is voorbij.') }}
                            @else
                                {{ Translation::get('pagina-dicht', 'bijbestellen', 'Aan deze bestelling kunnen we niets meer toevoegen.') }}
                            @endif
                        </p>
                        <p class="mt-2 opacity-80">{{ Translation::get('pagina-dicht-uitleg', 'bijbestellen', 'Je kunt natuurlijk gewoon een nieuwe bestelling plaatsen; die versturen we dan apart.') }}</p>
                        <div class="mt-5">
                            <a href="{{ url('/') }}" class="button">{{ Translation::get('pagina-dicht-knop', 'bijbestellen', 'Verder winkelen') }}</a>
                        </div>
                    </div>
                @endif
            </div>
        </x-container>
    </section>
</x-master>
