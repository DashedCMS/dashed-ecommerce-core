{{--
    Livewire order-add-ons.suggestions (Livewire\Frontend\OrderAddOns\Suggestions). Standaardweergave;
    een thema overschrijft hem met <site_theme>/order-add-ons/suggestions.blade.php.
    Variabelen: $suggestions, $inCart (product-id's), $cartUrl, $checkoutUrl, $quickAddProductId.
--}}
<div data-order-add-on-suggestions>
    @if($suggestions->isEmpty())
        <p class="mt-8 opacity-80">{{ Translation::get('geen-voorstellen', 'bijbestellen', 'We hebben nu geen voorstellen die in je doos passen. Leg zelf iets kleins in je winkelwagen: past het erbij, dan betaal je geen verzendkosten.') }}</p>
    @else
        <div class="mt-8 grid gap-2 sm:grid-cols-2">
            @foreach($suggestions as $suggestion)
                @php
                    $image = $suggestion->firstImage ?? $suggestion->productGroup?->firstImage;
                    $isInCart = in_array((int) $suggestion->id, $inCart, true);
                @endphp
                <div wire:key="order-add-on-{{ $suggestion->id }}" class="flex items-center gap-3 rounded-lg border p-2">
                    <a href="{{ $suggestion->getUrl() ?? '#' }}" class="block shrink-0 overflow-hidden rounded" style="width: 44px; height: 44px;">
                        @if($image)
                            <x-dashed-files::image class="h-full w-full object-cover" config="dashed" :mediaId="$image" :alt="$suggestion->name" :manipulations="['fit' => [120, 120]]"/>
                        @endif
                    </a>

                    <div class="min-w-0 flex-1">
                        <a href="{{ $suggestion->getUrl() ?? '#' }}" class="block truncate text-sm font-bold">{{ $suggestion->name }}</a>
                        <p class="text-sm font-semibold">{{ CurrencyHelper::formatPrice($suggestion->current_price) }}</p>
                    </div>

                    @if($isInCart)
                        <span class="shrink-0 rounded-full px-3 py-1 text-xs font-semibold">{{ Translation::get('toegevoegd', 'bijbestellen', 'Toegevoegd') }}</span>
                    @else
                        <button type="button" wire:click="addToCart({{ $suggestion->id }})" wire:loading.attr="disabled" class="shrink-0 rounded-full border px-3 py-1 text-xs font-semibold">
                            {{ Translation::get('cart.suggestions.add', 'cart', 'Voeg toe') }}
                        </button>
                    @endif
                </div>
            @endforeach
        </div>
    @endif

    <div class="mt-8 flex flex-wrap items-center gap-4">
        @if(count($inCart))
            <a href="{{ $checkoutUrl }}" class="button">{{ Translation::get('naar-afrekenen', 'bijbestellen', 'Afrekenen zonder verzendkosten') }}</a>
            <a href="{{ $cartUrl }}" class="text-sm underline">{{ Translation::get('naar-wagen', 'bijbestellen', 'Bekijk je winkelwagen') }}</a>
        @else
            <a href="{{ url('/') }}" class="text-sm underline">{{ Translation::get('zelf-zoeken', 'bijbestellen', 'Zelf iets uitzoeken') }}</a>
        @endif
    </div>
    <p class="mt-3 text-sm opacity-70">{{ Translation::get('pagina-voetnoot', 'bijbestellen', 'Je betaalt alleen de producten. Niet alles past in dezelfde doos: wat niet mee kan, zie je in je winkelwagen.') }}</p>

    @if($quickAddProductId)
        <div class="fixed inset-0 flex items-center justify-center p-4" style="z-index: 2147483647;" wire:key="order-add-on-quick-add-{{ $quickAddProductId }}">
            <div class="absolute inset-0" style="background: rgba(0,0,0,.5);" wire:click="closeQuickAdd"></div>
            <div class="relative w-full max-w-md overflow-y-auto rounded-lg bg-white p-5" style="max-height: 90vh;">
                <button type="button" wire:click="closeQuickAdd" class="absolute right-3 top-3 text-3xl leading-none" aria-label="{{ Translation::get('close', 'cart', 'Sluiten') }}">&times;</button>
                <livewire:cart.quick-add-product :product="\Dashed\DashedEcommerceCore\Models\Product::find($quickAddProductId)" :key="'order-add-on-quick-add-product-'.$quickAddProductId" />
            </div>
        </div>
    @endif
</div>
