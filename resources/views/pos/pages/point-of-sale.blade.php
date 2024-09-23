<div class="px-8 py-8">
    <div class="grid grid-cols-10 divide-x divide-gray-500">
        <div class="sm:col-span-5 sm:pr-8 flex flex-col gap-8">
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
                           inputmode="{{ $searchQueryInputmode }}"
                           wire:keyup.debounce.500ms="updateSearchedProducts"
                           placeholder="Zoek een product op naam, SKU of barcode..."
                           class="dark:text-black w-full rounded-lg pl-10 pr-10">
                    <p class="absolute right-2 top-2 text-black cursor-pointer" wire:click="toggleSearchQueryInputmode">
                        @if($searchQueryInputmode == 'none')
                            <span>
<svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor"
     stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="lucide lucide-keyboard-off"><path
        d="M 20 4 A2 2 0 0 1 22 6"/><path d="M 22 6 L 22 16.41"/><path d="M 7 16 L 16 16"/><path
        d="M 9.69 4 L 20 4"/><path d="M14 8h.01"/><path d="M18 8h.01"/><path d="m2 2 20 20"/><path
        d="M20 20H4a2 2 0 0 1-2-2V6a2 2 0 0 1 2-2"/><path d="M6 8h.01"/><path d="M8 12h.01"/></svg>
                        </span>
                        @else
                            <span>
                            <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24"
                                 fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"
                                 stroke-linejoin="round" class="lucide lucide-keyboard"><path d="M10 8h.01"/><path
                                    d="M12 12h.01"/><path d="M14 8h.01"/><path d="M16 12h.01"/><path d="M18 8h.01"/><path
                                    d="M6 8h.01"/><path d="M7 16h10"/><path d="M8 12h.01"/><rect width="20"
                                                                                                 height="16" x="2"
                                                                                                 y="4"
                                                                                                 rx="2"/></svg>
                        </span>
                        @endif
                    </p>
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
        <div class="sm:col-span-5 sm:pl-8 flex flex-col gap-8 h-full">
            <div class="grid gap-8">
                <div class="flex flex-wrap justify-between items-center">
                    <p class="text-5xl font-bold">Winkelwagen</p>
                    <button wire:click="clearProducts"
                            class="ml-8 h-12 w-12 bg-red-500 text-white hover:bg-primary-700 transition-all duration-300 ease-in-out p-1 rounded-full flex items-center justify-center">
                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5"
                             stroke="currentColor" class="size-6">
                            <path stroke-linecap="round" stroke-linejoin="round"
                                  d="m14.74 9-.346 9m-4.788 0L9.26 9m9.968-3.21c.342.052.682.107 1.022.166m-1.022-.165L18.16 19.673a2.25 2.25 0 0 1-2.244 2.077H8.084a2.25 2.25 0 0 1-2.244-2.077L4.772 5.79m14.456 0a48.108 48.108 0 0 0-3.478-.397m-12 .562c.34-.059.68-.114 1.022-.165m0 0a48.11 48.11 0 0 1 3.478-.397m7.5 0v-.916c0-1.18-.91-2.164-2.09-2.201a51.964 51.964 0 0 0-3.32 0c-1.18.037-2.09 1.022-2.09 2.201v.916m7.5 0a48.667 48.667 0 0 0-7.5 0"/>
                        </svg>
                    </button>
                </div>
                <div class="p-4 rounded-lg border border-gray-400 grid gap-4">
                    @if(count($products ?: []))
                        @foreach($products as $product)
                            <div class="flex flex-wrap items-center gap-4">
                                <div class="relative">
                                    <x-dashed-files::image
                                        class="object-cover rounded-lg w-20 h-20"
                                        :mediaId="$product['product']['firstImage']"/>
                                    <span class="bg-primary-500 text-white font-bold rounded-full w-6 h-6 absolute -right-2 -top-2 flex items-center justify-center border-2 border-white">{{ $product['quantity'] }}</span>
                                </div>
                                <div class="flex flex-col flex-wrap gap-1">
                                    <span class="font-bold text-lg">{{ $product['product']['name'] }}</span>
                                    <div class="flex flex-wrap gap-2">
                                        <button wire:click="changeQuantity({{ $product['id'] }}, {{ $product['quantity'] + 1 }})"
                                                class="h-12 w-12 bg-primary-500 text-white hover:bg-primary-700 transition-all duration-300 ease-in-out p-1 rounded-full flex items-center justify-center">
                                            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24"
                                                 stroke-width="1.5" stroke="currentColor" class="size-6">
                                                <path stroke-linecap="round" stroke-linejoin="round"
                                                      d="M12 4.5v15m7.5-7.5h-15"/>
                                            </svg>
                                        </button>
                                        <button wire:click="changeQuantity({{ $product['id'] }}, {{ $product['quantity'] - 1 }})"
                                                class="h-12 w-12 bg-primary-500 text-white hover:bg-primary-700 transition-all duration-300 ease-in-out p-1 rounded-full flex items-center justify-center">
                                            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24"
                                                 stroke-width="1.5" stroke="currentColor" class="size-6">
                                                <path stroke-linecap="round" stroke-linejoin="round" d="M5 12h14"/>
                                            </svg>
                                        </button>
                                        <button wire:click="changeQuantity({{ $product['id'] }}, {{ 0 }})"
                                                class="ml-8 h-12 w-12 bg-red-500 text-white hover:bg-primary-700 transition-all duration-300 ease-in-out p-1 rounded-full flex items-center justify-center">
                                            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24"
                                                 stroke-width="1.5" stroke="currentColor" class="size-6">
                                                <path stroke-linecap="round" stroke-linejoin="round"
                                                      d="m14.74 9-.346 9m-4.788 0L9.26 9m9.968-3.21c.342.052.682.107 1.022.166m-1.022-.165L18.16 19.673a2.25 2.25 0 0 1-2.244 2.077H8.084a2.25 2.25 0 0 1-2.244-2.077L4.772 5.79m14.456 0a48.108 48.108 0 0 0-3.478-.397m-12 .562c.34-.059.68-.114 1.022-.165m0 0a48.11 48.11 0 0 1 3.478-.397m7.5 0v-.916c0-1.18-.91-2.164-2.09-2.201a51.964 51.964 0 0 0-3.32 0c-1.18.037-2.09 1.022-2.09 2.201v.916m7.5 0a48.667 48.667 0 0 0-7.5 0"/>
                                            </svg>
                                        </button>
                                    </div>
                                </div>
                                <div class="ml-auto">
                                    <span class="font-bold">{{ \Dashed\DashedEcommerceCore\Classes\CurrencyHelper::formatPrice($product['price']) }}</span>
                                </div>
                            </div>
                        @endforeach
                    @else
                        <p>Geen producten geselecteerd...</p>
                    @endif
                </div>
            </div>
            <div class="mt-auto flex-1 gap-4 grid">
                <div class="grid gap-2 p-4 rounded-lg border border-gray-400">
                    <span class="text-xl font-bold flex justify-between items-center">
                    <span class="flex flex-col">
                        <span>Totaal</span>
                        <span class="text-sm font-normal">{{ collect($products)->sum('quantity') }} artikelen</span>
                    </span>
                    <span class="font-bold">{{ $total }}</span>
                </span>
                    <hr/>
                    <span class="text-sm font-bold flex justify-between items-center">
                        <span>Subtotaal</span>
                    <span class="font-bold">{{ $subTotal }}</span>
                </span>
                    <hr/>
                    <span class="text-sm font-bold flex justify-between items-center">
                        <span>BTW</span>
                    <span class="font-bold">{{ $vat }}</span>
                </span>
                </div>
                <button wire:click="submit"
                        class="px-4 py-2 text-lg uppercase rounded-lg bg-primary-500 hover:bg-primary-700 transition-all ease-in-out duration-300 text-white font-bold w-full">
                    Checkout
                </button>
            </div>
        </div>
    </div>
    @script
    <script>
        $wire.on('focus', () => {
            document.getElementById("search-product-query").focus();
        });
        document.addEventListener('touchmove', event => event.scale !== 1 && event.preventDefault(), {passive: false});
    </script>
    @endscript
</div>
