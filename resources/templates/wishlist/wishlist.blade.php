<div class="container mx-auto px-4 py-12">
    <h1 class="text-2xl font-bold">{{ __('Verlanglijst') }}</h1>

    @if($message)
        <p class="mt-4 rounded bg-green-50 p-3 text-green-800">{{ $message }}</p>
    @endif

    @if($items->isEmpty())
        <p class="mt-6">{{ __('Je verlanglijst is nog leeg.') }}</p>
        <a href="{{ url('/') }}" class="mt-4 inline-block underline">{{ __('Verder winkelen') }}</a>
    @else
        <div class="mt-4 flex flex-wrap gap-3">
            <button type="button" wire:click="addAllToCart" class="rounded bg-black px-4 py-2 text-white">{{ __('Alles in winkelwagen') }}</button>
            <button type="button" wire:click="share" class="rounded border px-4 py-2">{{ __('Deel je lijst') }}</button>
            @if($shareUrl)
                <input type="text" readonly value="{{ $shareUrl }}" class="w-full max-w-md rounded border px-3 py-2" onclick="this.select()">
            @endif
        </div>

        <div class="mt-8 grid gap-6 sm:grid-cols-2 lg:grid-cols-3">
            @foreach($items as $item)
                @php($product = $item->product)
                <div class="rounded border p-4" wire:key="wishlist-item-{{ $item->id }}">
                    <a href="{{ $product->getUrl() }}">
                        @if($product->firstImage)
                            <x-dashed-files::image config="dashed" :mediaId="$product->firstImage" :alt="$product->name" :manipulations="['fit' => [400, 400]]" class="aspect-square w-full object-contain"/>
                        @endif
                        <h2 class="mt-3 font-semibold">{{ $product->name }}</h2>
                    </a>
                    <p class="mt-1">
                        {{ \Dashed\DashedEcommerceCore\Classes\CurrencyHelper::formatPrice($product->currentPrice) }}
                        @if($item->priceDropped())
                            <span class="ml-2 rounded-full bg-green-100 px-2 py-0.5 text-xs text-green-800">{{ __('Prijs gedaald') }}, {{ __('was') }} {{ \Dashed\DashedEcommerceCore\Classes\CurrencyHelper::formatPrice($item->price_at_add) }}</span>
                        @endif
                    </p>
                    @if($product->inStock())
                        <button type="button" wire:click="addToCart({{ $product->id }})" class="mt-3 w-full rounded bg-black px-3 py-2 text-white">{{ __('In winkelwagen') }}</button>
                    @else
                        <span class="mt-3 block text-sm text-gray-500">{{ __('Uitverkocht') }}</span>
                    @endif
                    <button type="button" wire:click="remove({{ $product->id }})" class="mt-2 text-sm underline">{{ __('Verwijderen') }}</button>
                </div>
            @endforeach
        </div>
    @endif

    @if(! $hasEmail)
        <form wire:submit="saveByEmail" class="mt-10 max-w-md rounded border p-4">
            <h2 class="font-semibold">{{ __('Bewaar je verlanglijst') }}</h2>
            <p class="text-sm text-gray-600">{{ __('Dan kun je hem op elk apparaat terugvinden.') }}</p>
            <input type="email" wire:model="email" placeholder="{{ __('Je e-mailadres') }}" class="mt-3 w-full rounded border px-3 py-2">
            @error('email') <p class="text-sm text-red-600">{{ $message }}</p> @enderror
            <button type="submit" class="mt-3 rounded bg-black px-4 py-2 text-white">{{ __('Bewaren') }}</button>
        </form>
    @endif
</div>
