<div class="px-8 py-8 h-[calc(100vh-80px)] overflow-y-scroll">
    <div class="grid grid-cols-10 divide-x divide-gray-500">
        <div class="sm:col-span-6 sm:pr-8 flex flex-col gap-8">
            <div>
                <p class="font-bold text-5xl">{{ Customsetting::get('site_name') }}</p>
            </div>
            <form wire:submit.prevent="selectProduct">
                <div class="w-full relative">
                    <span class="text-black absolute left-2 top-2">
                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5"
                             stroke="currentColor" class="size-6">
  <path stroke-linecap="round" stroke-linejoin="round"
        d="m21 21-5.197-5.197m0 0A7.5 7.5 0 1 0 5.196 5.196a7.5 7.5 0 0 0 10.607 10.607Z"/>
</svg>
                    </span>
                    <input autofocus wire:model.live="searchProductQuery"
                           id="search-product-query"
                           wire:keyup.debounce.500ms="updateSearchedProducts"
                           placeholder="Zoek een product op naam, SKU of barcode..."
                           class="dark:text-black w-full rounded-lg pl-10">
                    @if($searchProductQuery && count($searchedProducts ?: []) > 0)
                        <div class="absolute z-50 bg-white rounded-lg mt-2 shadow-xl">
                            <div class="overflow-y-auto max-h-96">
                                <ul class="border-t divide-y border-black/5 divide-black/5">
                                    @foreach($searchedProducts as $product)
                                        <li class="grid relative items-center grid-cols-2 gap-6 p-4 lg:grid-cols-3">
                                            @if($product->firstImage)
                                                <div class="cursor-pointer" wire:click="addProduct({{ $product->id }})">
                                                    <x-drift::image
                                                        class="object-cover aspect-[3/2] rounded-lg max-h-[100px]"
                                                        config="dashed"
                                                        :path="$product->firstImage"
                                                        :alt="$product->name"
                                                        :manipulations="[
                                                        'widen' => 300,
                                                    ]"
                                                    />
                                                </div>
                                            @endif

                                            <div wire:click="addProduct({{ $product->id }})"
                                                 class="lg:col-span-2 cursor-pointer">
                                                <p class="font-medium">
                                                    {{ $product->name }}
                                                    ({{ CurrencyHelper::formatPrice($product->currentPrice) }})
                                                </p>
                                            </div>
                                        </li>
                                    @endforeach
                                </ul>
                            </div>
                        </div>
                    @elseif($searchProductQuery && count($searchedProducts ?: []) === 0)
                        <div class="absolute z-50 bg-white rounded-lg mt-2 shadow-xl">
                            <div class="p-4">
                                <p class="text-center">Geen producten gevonden</p>
                            </div>
                        </div>
                    @endif
                </div>
            </form>
        </div>
        <div class="sm:col-span-4 sm:pl-8 flex flex-col gap-8 h-full">
            <div>
                <p class="text-5xl font-bold">Winkelwagen</p>
                @foreach($products as $product)
                    <p>
                        {{ $product['product']['name'] }} - {{ $product['quantity'] }}
                    </p>
                @endforeach
            </div>
            <div class="border-t border-gray-400 mt-auto pt-8 flex-1">
                <span class="text-2xl flex justify-between">
                    <span class="flex flex-col">
                        <span>Totaal</span>
                        <span class="text-sm">{{ collect($products)->sum('quantity') }} artikelen</span>
                    </span>
                    <span class="font-bold">{{ $total }}</span></span>
            </div>
        </div>
    </div>
    @script
    <script>
        $wire.on('focus', () => {
            document.getElementById("search-product-query").focus();
        });
    </script>
    @endscript
</div>
