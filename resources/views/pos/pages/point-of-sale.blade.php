<div class="relative inline-flex w-full">
    {{--<div class="h-full overflow-y-scroll bg-red-500">--}}
    <div
        class="absolute transitiona-all duration-1000 opacity-70 -inset-px bg-gradient-to-r from-[#44BCFF] via-[#FF44EC] to-[#FF675E] rounded-xl blur-lg group-hover:opacity-100 group-hover:-inset-1 group-hover:duration-200 animate-tilt">
    </div>
    <div class="p-8 m-8 border border-white rounded-lg h-[calc(100%) - 50px] overflow-hidden bg-black/90 z-10 w-full">
        <div class="grid grid-cols-10 divide-x divide-gray-500">
            <div class="sm:col-span-5 sm:pr-8 flex flex-col gap-8">
                <div class="flex flex-wrap justify-between items-center">
                    <p class="font-bold text-5xl">{{ Customsetting::get('site_name') }}</p>
                    <div class="flex flex-wrap gap-2">
                        @if($lastOrder)
                            <button
                                wire:click="printLastOrder"
                                class="h-12 w-12 bg-primary-500 text-white hover:bg-primary-700 transition-all duration-300 ease-in-out p-1 rounded-full flex items-center justify-center">
                                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24"
                                     stroke-width="1.5"
                                     stroke="currentColor" class="size-6">
                                    <path stroke-linecap="round" stroke-linejoin="round"
                                          d="M19.5 12c0-1.232-.046-2.453-.138-3.662a4.006 4.006 0 0 0-3.7-3.7 48.678 48.678 0 0 0-7.324 0 4.006 4.006 0 0 0-3.7 3.7c-.017.22-.032.441-.046.662M19.5 12l3-3m-3 3-3-3m-12 3c0 1.232.046 2.453.138 3.662a4.006 4.006 0 0 0 3.7 3.7 48.656 48.656 0 0 0 7.324 0 4.006 4.006 0 0 0 3.7-3.7c.017-.22.032-.441.046-.662M4.5 12l3 3m-3-3-3 3"/>
                                </svg>
                            </button>
                        @endif
                    </div>
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
                               class="text-black w-full rounded-lg pl-10 pr-10">
                        <p class="absolute right-2 top-2 text-black cursor-pointer"
                           wire:click="toggleSearchQueryInputmode">
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
                                                    <div class="cursor-pointer"
                                                         wire:click="addProduct({{ $product->id }})">
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
                                                     class="lg:col-span-2 cursor-pointer text-wrap max-w-[300px] md:max-w-[400px]">
                                                    <p class="font-medium text-black">
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
                                    <p class="text-center text-black">Geen producten gevonden</p>
                                </div>
                            </div>
                        @endif
                    </div>
                </form>
                <div class="grid gap-4 sm:grid-cols-2">
                    <button wire:click="toggleVariable('customProductPopup')"
                            class="text-left rounded-lg bg-primary-500/40 hover:bg-primary-500/70 transition-all duration-300 ease-in-out h-[150px] flex flex-col justify-between p-4 font-medium text-xl">
                        <span>
                            <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24"
                                 fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"
                                 stroke-linejoin="round" class="lucide lucide-shopping-cart"><circle cx="8" cy="21"
                                                                                                     r="1"/><circle
                                    cx="19" cy="21" r="1"/><path
                                    d="M2.05 2.05h2l2.66 12.42a2 2 0 0 0 2 1.58h9.78a2 2 0 0 0 1.95-1.57l1.65-7.43H5.12"/></svg>
                        </span>
                        <p>Aangepaste verkoop toevoegen</p>
                    </button>
                    @if($activeDiscountCode)
                        <button wire:click="removeDiscount"
                                class="text-left rounded-lg bg-red-500/40 hover:bg-red-500/70 transition-all duration-300 ease-in-out h-[150px] flex flex-col justify-between p-4 font-medium text-xl">
                        <span>
<svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor"
     stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="lucide lucide-percent"><line x1="19" x2="5"
                                                                                                         y1="5"
                                                                                                         y2="19"/><circle
        cx="6.5" cy="6.5" r="2.5"/><circle cx="17.5" cy="17.5" r="2.5"/></svg>
                        </span>
                            <p>Korting verwijderen</p>
                        </button>
                    @else
                        <button wire:click="toggleVariable('createDiscountPopup')"
                                class="text-left rounded-lg bg-primary-500/40 hover:bg-primary-500/70 transition-all duration-300 ease-in-out h-[150px] flex flex-col justify-between p-4 font-medium text-xl">
                        <span>
<svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor"
     stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="lucide lucide-percent"><line x1="19" x2="5"
                                                                                                         y1="5"
                                                                                                         y2="19"/><circle
        cx="6.5" cy="6.5" r="2.5"/><circle cx="17.5" cy="17.5" r="2.5"/></svg>
                        </span>
                            <p>Korting toepassen</p>
                        </button>
                    @endif
                    <button
                        class="text-left rounded-lg bg-primary-500/40 hover:bg-primary-500/70 transition-all duration-300 ease-in-out h-[150px] flex flex-col justify-between p-4 font-medium text-xl">
                        <span>
<svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"
     class="size-6">
  <path stroke-linecap="round" stroke-linejoin="round"
        d="M15.75 6a3.75 3.75 0 1 1-7.5 0 3.75 3.75 0 0 1 7.5 0ZM4.501 20.118a7.5 7.5 0 0 1 14.998 0A17.933 17.933 0 0 1 12 21.75c-2.676 0-5.216-.584-7.499-1.632Z"/>
</svg>
                        </span>
                        <p>Klant toevoegen</p>
                    </button>
                    @if(Customsetting::get('cash_register_available', null, false))
                        <button wire:click="openCashRegister"
                                class="text-left rounded-lg bg-primary-500/40 hover:bg-primary-500/70 transition-all duration-300 ease-in-out h-[150px] flex flex-col justify-between p-4 font-medium text-xl">
                        <span>
<svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor"
     stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="lucide lucide-hand-coins"><path
        d="M11 15h2a2 2 0 1 0 0-4h-3c-.6 0-1.1.2-1.4.6L3 17"/><path
        d="m7 21 1.6-1.4c.3-.4.8-.6 1.4-.6h4c1.1 0 2.1-.4 2.8-1.2l4.6-4.4a2 2 0 0 0-2.75-2.91l-4.2 3.9"/><path
        d="m2 16 6 6"/><circle cx="16" cy="9" r="2.9"/><circle cx="6" cy="5" r="3"/></svg>
                        </span>
                            <p>Kassa lade openen</p>
                        </button>
                    @endif
                    <button wire:click="toggleVariable('searchOrderPopup')"
                            class="focus-search-order text-left rounded-lg bg-primary-500/40 hover:bg-primary-500/70 transition-all duration-300 ease-in-out h-[150px] flex flex-col justify-between p-4 font-medium text-xl">
                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5"
                             stroke="currentColor" class="size-6">
                            <path stroke-linecap="round" stroke-linejoin="round"
                                  d="m21 21-5.197-5.197m0 0A7.5 7.5 0 1 0 5.196 5.196a7.5 7.5 0 0 0 10.607 10.607Z"/>
                        </svg>

                        <p>Zoek bestelling</p>
                    </button>
                </div>
            </div>
            <div class="sm:col-span-5 sm:pl-8 flex flex-col gap-8">
                <div class="flex flex-col gap-8">
                    <div class="flex flex-wrap justify-between items-center">
                        <p class="text-5xl font-bold">Winkelwagen</p>
                        <div class="flex gap-4">
                            @if(count($products ?: []))
                                <button wire:click="clearProducts"
                                        class="h-12 w-12 bg-red-500 text-white hover:bg-red-700 transition-all duration-300 ease-in-out p-1 rounded-full flex items-center justify-center">
                                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24"
                                         stroke-width="1.5"
                                         stroke="currentColor" class="size-6">
                                        <path stroke-linecap="round" stroke-linejoin="round"
                                              d="m14.74 9-.346 9m-4.788 0L9.26 9m9.968-3.21c.342.052.682.107 1.022.166m-1.022-.165L18.16 19.673a2.25 2.25 0 0 1-2.244 2.077H8.084a2.25 2.25 0 0 1-2.244-2.077L4.772 5.79m14.456 0a48.108 48.108 0 0 0-3.478-.397m-12 .562c.34-.059.68-.114 1.022-.165m0 0a48.11 48.11 0 0 1 3.478-.397m7.5 0v-.916c0-1.18-.91-2.164-2.09-2.201a51.964 51.964 0 0 0-3.32 0c-1.18.037-2.09 1.022-2.09 2.201v.916m7.5 0a48.667 48.667 0 0 0-7.5 0"/>
                                    </svg>
                                </button>
                            @endif
                            <button id="exitFullscreenBtn"
                                    class="@if(!$fullscreen) hidden @endif h-12 w-12 bg-primary-500 text-white hover:bg-primary-700 transition-all duration-300 ease-in-out p-1 rounded-full flex items-center justify-center">
                                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24"
                                     stroke-width="1.5" stroke="currentColor" class="size-6">
                                    <path stroke-linecap="round" stroke-linejoin="round"
                                          d="M9 9V4.5M9 9H4.5M9 9 3.75 3.75M9 15v4.5M9 15H4.5M9 15l-5.25 5.25M15 9h4.5M15 9V4.5M15 9l5.25-5.25M15 15h4.5M15 15v4.5m0-4.5 5.25 5.25"/>
                                </svg>
                            </button>
                            <button id="fullscreenBtn"
                                    class="@if($fullscreen) hidden @endif h-12 w-12 bg-primary-500 text-white hover:bg-primary-700 transition-all duration-300 ease-in-out p-1 rounded-full flex items-center justify-center">
                                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24"
                                     stroke-width="1.5" stroke="currentColor" class="size-6">
                                    <path stroke-linecap="round" stroke-linejoin="round"
                                          d="M3.75 3.75v4.5m0-4.5h4.5m-4.5 0L9 9M3.75 20.25v-4.5m0 4.5h4.5m-4.5 0L9 15M20.25 3.75h-4.5m4.5 0v4.5m0-4.5L15 9m5.25 11.25h-4.5m4.5 0v-4.5m0 4.5L15 15"/>
                                </svg>
                            </button>
                        </div>
                    </div>
                    {{--                <div class="p-4 rounded-lg border border-gray-400 grid gap-4">--}}
                    <div
                        class="p-4 rounded-lg border border-gray-400 flex flex-col gap-4 h-[calc(100vh-550px)] overflow-y-auto">
                        @if(count($products ?: []))
                            @foreach($products as $product)
                                @if(!$loop->first)
                                    <hr class="bg-gray-400">
                                @endif
                                <div class="flex flex-wrap items-center gap-4">
                                    <div class="relative">
                                        @if($product['product'] ?? false)
                                            <x-dashed-files::image
                                                class="object-cover rounded-lg w-20 h-20"
                                                :mediaId="$product['product']['firstImage']"/>
                                        @else
                                            <img
                                                src="https://placehold.co/400x400/{{ str(collect(collect(filament()->getPanels())->first()->getColors())->first())->replace('#', '') }}/fff?text=Aangepaste%20verkoop"
                                                class="object-cover rounded-lg w-20 h-20">
                                        @endif
                                        <span
                                            class="bg-primary-500 text-white font-bold rounded-full w-6 h-6 absolute -right-2 -top-2 flex items-center justify-center border-2 border-white">{{ $product['quantity'] }}</span>
                                    </div>
                                    <div class="flex flex-col flex-wrap gap-1">
                                        <span
                                            class="font-bold text-lg">{{ $product['product']['name'] ?? $product['name'] }}</span>
                                        <div class="flex flex-wrap gap-2">
                                            <button
                                                wire:click="changeQuantity('{{ $product['id'] ?: $product['customId'] }}', {{ $product['quantity'] + 1 }})"
                                                class="h-12 w-12 bg-primary-500 text-white hover:bg-primary-700 transition-all duration-300 ease-in-out p-1 rounded-full flex items-center justify-center">
                                                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24"
                                                     stroke-width="1.5" stroke="currentColor" class="size-6">
                                                    <path stroke-linecap="round" stroke-linejoin="round"
                                                          d="M12 4.5v15m7.5-7.5h-15"/>
                                                </svg>
                                            </button>
                                            <button
                                                wire:click="changeQuantity('{{ $product['id'] ?: $product['customId'] }}', {{ $product['quantity'] - 1 }})"
                                                class="h-12 w-12 bg-primary-500 text-white hover:bg-primary-700 transition-all duration-300 ease-in-out p-1 rounded-full flex items-center justify-center">
                                                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24"
                                                     stroke-width="1.5" stroke="currentColor" class="size-6">
                                                    <path stroke-linecap="round" stroke-linejoin="round" d="M5 12h14"/>
                                                </svg>
                                            </button>
                                            <button
                                                wire:click="changeQuantity('{{ $product['id'] ?: $product['customId'] }}', {{ 0 }})"
                                                class="ml-8 h-12 w-12 bg-red-500 text-white hover:bg-red-700 transition-all duration-300 ease-in-out p-1 rounded-full flex items-center justify-center">
                                                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24"
                                                     stroke-width="1.5" stroke="currentColor" class="size-6">
                                                    <path stroke-linecap="round" stroke-linejoin="round"
                                                          d="m14.74 9-.346 9m-4.788 0L9.26 9m9.968-3.21c.342.052.682.107 1.022.166m-1.022-.165L18.16 19.673a2.25 2.25 0 0 1-2.244 2.077H8.084a2.25 2.25 0 0 1-2.244-2.077L4.772 5.79m14.456 0a48.108 48.108 0 0 0-3.478-.397m-12 .562c.34-.059.68-.114 1.022-.165m0 0a48.11 48.11 0 0 1 3.478-.397m7.5 0v-.916c0-1.18-.91-2.164-2.09-2.201a51.964 51.964 0 0 0-3.32 0c-1.18.037-2.09 1.022-2.09 2.201v.916m7.5 0a48.667 48.667 0 0 0-7.5 0"/>
                                                </svg>
                                            </button>
                                        </div>
                                    </div>
                                    <div class="ml-auto">
                                        <span
                                            class="font-bold">{{ \Dashed\DashedEcommerceCore\Classes\CurrencyHelper::formatPrice($product['price']) }}</span>
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
                        @if($activeDiscountCode)
                </span>
                        <hr/>
                        <span class="text-sm font-bold flex justify-between items-center">
                        <span>Korting</span>
                    <span class="font-bold">{{ $discount }}</span>
                            @endif
                </span>
                        {{--                        <hr/>--}}
                        {{--                        <span class="text-sm font-bold flex justify-between items-center">--}}
                        {{--                        <span>Subtotaal</span>--}}
                        {{--                    <span class="font-bold">{{ $subTotal }}</span>--}}
                        {{--                </span>--}}
                        @foreach($vatPercentages as $percentage => $value)
                            @if($loop->first)
                                <hr/>
                            @endif
                            <span class="text-sm font-bold flex justify-between items-center">
                        <span>BTW {{ number_format($percentage, 0) }}%</span>
                    <span
                        class="font-bold">{{ \Dashed\DashedEcommerceCore\Classes\CurrencyHelper::formatPrice($value) }}</span>
                </span>
                        @endforeach
                        @if(!count($vatPercentages))
                            <hr/>
                        @endif
                        @if(count($vatPercentages) > 1)
                            <span class="text-sm font-bold flex justify-between items-center">
                        <span>BTW</span>
                    <span class="font-bold">{{ $vat }}</span>
                            @endif
                </span>
                    </div>
                    <button wire:click="initiateCheckout"
                            class="px-4 py-2 text-lg uppercase rounded-lg bg-primary-500 hover:bg-primary-700 transition-all ease-in-out duration-300 text-white font-bold w-full">
                        Checkout
                    </button>
                </div>
            </div>
        </div>
    </div>
    @if($customProductPopup)
        <div class="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50 text-black">
            <div class="absolute h-full w-full" wire:click="toggleVariable('customProductPopup')"></div>
            <div class="bg-white rounded-lg p-8 grid gap-4 relative">
                <div class="absolute top-2 right-2 text-black cursor-pointer"
                     wire:click="toggleVariable('customProductPopup')">
                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5"
                         stroke="currentColor" class="size-10">
                        <path stroke-linecap="round" stroke-linejoin="round"
                              d="m9.75 9.75 4.5 4.5m0-4.5-4.5 4.5M21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z"/>
                    </svg>
                </div>
                <p class="text-3xl font-bold">Aangepaste verkoop toevoegen</p>
                <form wire:submit.prevent="submitCustomProductForm">
                    <div class="grid gap-4">
                        {{ $this->customProductForm }}
                        <div>
                            <button type="submit"
                                    class="px-4 py-2 text-lg uppercase rounded-lg bg-primary-500 hover:bg-primary-700 transition-all ease-in-out duration-300 text-white font-bold w-full">
                                Toevoegen
                            </button>
                        </div>
                    </div>
                </form>
            </div>
        </div>
    @endif
    @if($createDiscountPopup)
        <div class="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50 text-black">
            <div class="absolute h-full w-full" wire:click="toggleVariable('createDiscountPopup')"></div>
            <div class="bg-white rounded-lg p-8 grid gap-4 relative">
                <div class="bg-white rounded-lg p-8 grid gap-4">
                    <div class="absolute top-2 right-2 text-black cursor-pointer"
                         wire:click="toggleVariable('createDiscountPopup')">
                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5"
                             stroke="currentColor" class="size-10">
                            <path stroke-linecap="round" stroke-linejoin="round"
                                  d="m9.75 9.75 4.5 4.5m0-4.5-4.5 4.5M21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z"/>
                        </svg>
                    </div>
                    <p class="text-3xl font-bold">Korting toepassen op winkelwagen</p>
                    <form wire:submit.prevent="submitCreateDiscountForm">
                        <div class="grid gap-4">
                            {{ $this->createDiscountForm }}
                            <div>
                                <button type="submit"
                                        class="px-4 py-2 text-lg uppercase rounded-lg bg-primary-500 hover:bg-primary-700 transition-all ease-in-out duration-300 text-white font-bold w-full">
                                    Toevoegen
                                </button>
                            </div>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    @endif
    @if($searchOrderPopup)
        <div class="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50 text-black">
            <div class="absolute h-full w-full" wire:click="toggleVariable('searchOrderPopup')"></div>
            <div class="bg-white rounded-lg p-8 grid gap-4 relative">
                <div class="bg-white rounded-lg p-8 grid gap-4">
                    <div class="absolute top-2 right-2 text-black cursor-pointer"
                         wire:click="toggleVariable('searchOrderPopup')">
                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5"
                             stroke="currentColor" class="size-10">
                            <path stroke-linecap="round" stroke-linejoin="round"
                                  d="m9.75 9.75 4.5 4.5m0-4.5-4.5 4.5M21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z"/>
                        </svg>
                    </div>
                    <p class="text-3xl font-bold">Zoek een bestelling op ID</p>
                    <form wire:submit.prevent="submitSearchOrderForm">
                        <div class="grid gap-4">
                            {{ $this->searchOrderForm }}
                            <div>
                                <button type="submit"
                                        class="px-4 py-2 text-lg uppercase rounded-lg bg-primary-500 hover:bg-primary-700 transition-all ease-in-out duration-300 text-white font-bold w-full">
                                    Zoeken
                                </button>
                            </div>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    @endif
    @if($checkoutPopup)
        <div class="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50 text-black">
            <div class="absolute h-full w-full" wire:click="closeCheckout"></div>
            <div class="bg-white rounded-lg p-8 grid gap-4 relative sm:min-w-[800px]">
                <div class="bg-white rounded-lg p-8 grid gap-4">
                    <div class="absolute top-2 right-2 text-black cursor-pointer"
                         wire:click="closeCheckout">
                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5"
                             stroke="currentColor" class="size-10">
                            <path stroke-linecap="round" stroke-linejoin="round"
                                  d="m9.75 9.75 4.5 4.5m0-4.5-4.5 4.5M21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z"/>
                        </svg>
                    </div>
                    <div>
                        <p class="text-3xl font-bold">Totaal: {{ $total }}</p>
                        <p class="text-xl text-gray-400">Selecteer betalingsoptie</p>
                    </div>
                    @if(count($posPaymentMethods))
                        <div class="grid gap-8">
                            @foreach($posPaymentMethods as $paymentMethod)
                                <button wire:click="selectPaymentMethod('{{ $paymentMethod['id'] }}')"
                                        class="p-4 text-2xl uppercase rounded-lg bg-primary-500 hover:bg-primary-700 transition-all ease-in-out duration-300 text-white font-bold w-full flex items-center flex-wrap justify-between">
                                    @if($paymentMethod['image'])
                                        <img
                                            src="{{ mediaHelper()->getSingleMedia($paymentMethod['image'], 'original')->url ?? '' }}"
                                            class="h-20 mr-2">
                                    @else
                                        <img
                                            src="https://placehold.co/400x400/000/{{ str(collect(collect(filament()->getPanels())->first()->getColors())->first())->replace('#', '') }}?text={{ $paymentMethod['name'] }}"
                                            class="object-cover rounded-lg h-20">
                                    @endif
                                    <span>{{ $paymentMethod['name'] }}</span>
                                </button>
                            @endforeach
                        </div>
                    @else
                        <div class="p-4">
                            <p class="text-center text-black">Geen betaalmethodes gevonden</p>
                        </div>
                    @endif
                </div>
            </div>
        </div>
    @endif
    @if($paymentPopup)
        <div class="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50 text-black">
            <div class="absolute h-full w-full" wire:click="closePayment"></div>
            <div class="bg-white rounded-lg p-8 grid gap-4 relative sm:min-w-[800px]">
                <div class="bg-white rounded-lg p-8 grid gap-4">
                    <div class="absolute top-2 right-2 text-black cursor-pointer"
                         wire:click="closePayment">
                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5"
                             stroke="currentColor" class="size-10">
                            <path stroke-linecap="round" stroke-linejoin="round"
                                  d="m9.75 9.75 4.5 4.5m0-4.5-4.5 4.5M21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z"/>
                        </svg>
                    </div>
                    <div>
                        <p class="text-3xl font-bold">Totaal: {{ $total }}</p>
                        <p class="text-xl text-gray-400">Betaalmethode: {{ $paymentMethod->name }}</p>
                    </div>
                    @if($paymentMethod->is_cash_payment)
                        <div class="flex flex-col gap-4">
                            <div class="flex flex-col gap-2">
                                <p class="text-xl font-bold">Ontvangen bedrag</p>
                                <div class="grid md:grid-cols-2 gap-4">
                                    @foreach($suggestedCashPaymentAmounts as $suggestedCashPaymentAmount)
                                        <button wire:click="setCashPaymentAmount({{ $suggestedCashPaymentAmount }})"
                                                class="p-4 text-2xl uppercase rounded-lg bg-primary-500 hover:bg-primary-700 transition-all ease-in-out duration-300 text-white font-bold w-full">
                                            {{ \Dashed\DashedEcommerceCore\Classes\CurrencyHelper::formatPrice($suggestedCashPaymentAmount) }}
                                        </button>
                                    @endforeach
                                </div>
                            </div>
                            <form wire:submit.prevent="markAsPaid">
                                {{ $this->cashPaymentForm }}
                                {{--                                <input wire:model="cashPaymentAmount"--}}
                                {{--                                       type="number"--}}
                                {{--                                       min="0"--}}
                                {{--                                       max="100000"--}}
                                {{--                                       required--}}
                                {{--                                       class="text-black w-full rounded-lg pl-4 pr-4"--}}
                                {{--                                       placeholder="Anders...">--}}
                            </form>
                        </div>
                    @endif
                    @if($isPinTerminalPayment)
                        @if($pinTerminalStatus == 'pending')
                            <div>
                                <p class="text-3xl">{{ Translation::get('pin-transaction-started', 'point-of-sale', 'De klant mag nu pinnen.') }}</p>
                                @if($order->paidAmount > 0)
                                    <p class="text-xl text-gray-400">Al
                                        betaald: {{ \Dashed\DashedEcommerceCore\Classes\CurrencyHelper::formatPrice($order->paidAmount) }}</p>
                                @endif
                            </div>
                        @elseif($pinTerminalStatus == 'waiting_for_clearance')
                            <p class="text-3xl">{{ Translation::get('pin-terminal-in-use', 'point-of-sale', 'De pin terminal is in gebruik, wacht tot deze vrijgegeven is.') }}</p>
                            <button wire:click="startPinTerminalPayment"
                                    class="w-full px-4 py-4 text-lg uppercase rounded-lg bg-primary-500 hover:bg-primary-700 transition-all ease-in-out duration-300 text-white font-bold w-full flex items-center justify-center gap-1">
                                <span>Start betaling opnieuw</span>
                            </button>
                        @elseif($pinTerminalStatus == 'timed_out')
                            <p class="text-3xl">{{ Translation::get('pin-terminal-payment-timed-out', 'point-of-sale', 'De betaling is niet optijd voltooid, probeer het opnieuw.') }}</p>
                            <button wire:click="startPinTerminalPayment"
                                    class="w-full px-4 py-4 text-lg uppercase rounded-lg bg-primary-500 hover:bg-primary-700 transition-all ease-in-out duration-300 text-white font-bold w-full flex items-center justify-center gap-1">
                                <span>Start betaling opnieuw</span>
                            </button>
                        @elseif($pinTerminalStatus == 'cancelled_by_customer')
                            <p class="text-3xl">{{ Translation::get('pin-terminal-payment-timed-out', 'point-of-sale', 'De betaling is niet voltooid door de klant.') }}</p>
                            <button wire:click="startPinTerminalPayment"
                                    class="w-full px-4 py-4 text-lg uppercase rounded-lg bg-primary-500 hover:bg-primary-700 transition-all ease-in-out duration-300 text-white font-bold w-full flex items-center justify-center gap-1">
                                <span>Start betaling opnieuw</span>
                            </button>
                        @elseif($pinTerminalError)
                            <p class="text-3xl">{{ $pinTerminalErrorMessage }}</p>
                            <button wire:click="startPinTerminalPayment"
                                    class="w-full px-4 py-4 text-lg uppercase rounded-lg bg-primary-500 hover:bg-primary-700 transition-all ease-in-out duration-300 text-white font-bold w-full flex items-center justify-center gap-1">
                                <span>Probeer betaling opnieuw</span>
                            </button>
                        @endif
                    @endif
                    <div class="grid md:grid-cols-2 gap-4 mt-16">
                        @if($isPinTerminalPayment && $pinTerminalStatus == 'pending')
                            <button wire:poll.1s="checkPinTerminalPayment" disabled
                                    class="md:col-span-2 px-4 py-4 text-lg uppercase rounded-lg bg-primary-500 hover:bg-primary-700 transition-all ease-in-out duration-300 text-white font-bold w-full flex items-center justify-center gap-1">
                                <svg class="animate-spin -ml-1 mr-3 h-5 w-5 text-white"
                                     xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor"
                                            stroke-width="4"></circle>
                                    <path class="opacity-75" fill="currentColor"
                                          d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                                </svg>
                                <span>Wachten op betaling...</span>
                            </button>
                        @elseif(!$isPinTerminalPayment)
                            <button wire:click="closePayment"
                                    class="px-4 py-4 text-lg uppercase rounded-lg bg-red-500 hover:bg-red-700 transition-all ease-in-out duration-300 text-white font-bold w-full">
                                Annuleren
                            </button>
                            @if(!$cashPaymentAmount)
                                <button disabled
                                        class="px-4 py-4 text-lg uppercase rounded-lg bg-primary-700 transition-all ease-in-out duration-300 text-white font-bold w-full">
                                    Vul een bedrag in
                                </button>
                            @elseif($cashPaymentAmount < $totalUnformatted)
                                <button wire:click="createPaymentWithExtraPayment"
                                        class="px-4 py-4 text-lg uppercase rounded-lg bg-primary-500 hover:bg-primary-700 transition-all ease-in-out duration-300 text-white font-bold w-full">
                                    Restbedrag bijpinnen
                                </button>
                            @else
                                <button wire:click="markAsPaid"
                                        class="px-4 py-4 text-lg uppercase rounded-lg bg-primary-500 hover:bg-primary-700 transition-all ease-in-out duration-300 text-white font-bold w-full">
                                    Markeer als betaald
                                </button>
                            @endif
                        @endif
                    </div>
                </div>
            </div>
        </div>
    @endif
    @if($orderConfirmationPopup)
        <div class="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50 text-black">
            <div class="absolute h-full w-full" wire:click="closeOrderConfirmation"></div>
            <div class="bg-white rounded-lg p-8 grid gap-4 relative sm:min-w-[800px]">
                <div class="bg-white rounded-lg p-8 grid gap-4">
                    <div class="absolute top-2 right-2 text-black cursor-pointer"
                         wire:click="closeOrderConfirmation">
                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5"
                             stroke="currentColor" class="size-10">
                            <path stroke-linecap="round" stroke-linejoin="round"
                                  d="m9.75 9.75 4.5 4.5m0-4.5-4.5 4.5M21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z"/>
                        </svg>
                    </div>
                    <div>
                        <p class="text-3xl font-bold">Bestelling {{ $order->invoice_id }} afgerond
                            - {{ \Dashed\DashedEcommerceCore\Classes\CurrencyHelper::formatPrice($order->total) }}</p>
                        <p class="text-xl text-gray-400">
                            Betaalmethode: {{ $order->orderPayments()->first()->paymentMethod->name }}
                        </p>
                    </div>
                    @if($order->orderPayments()->first()->paymentMethod->is_cash_payment)
                        <div class="flex flex-col gap-4">
                            <p class="text-xl font-bold">Betaling overzicht</p>
                            @foreach($order->orderPayments()->where('status', 'paid')->get() as $orderPayment)
                                <div
                                    class="flex flex-wrap items-center justify-between border border-gray-400 rounded-lg p-4 gap-4">
                                    <div class="flex flex-col">
                                        <p class="font-bold text-lg">Betaling {{ $loop->iteration }}</p>
                                        <p class="text-gray-400">{{ $orderPayment->paymentMethod->name }}</p>
                                    </div>
                                    <div class="flex flex-col">
                                        <p class="font-bold text-xl">{{ \Dashed\DashedEcommerceCore\Classes\CurrencyHelper::formatPrice($orderPayment->amount) }}</p>
                                        @if($loop->first)
                                            @if($order->total < $order->paidAmount)
                                                <p class="text-warning-500 font-bold text-xl">
                                                    Wisselgeld
                                                    verschuldigd: {{ \Dashed\DashedEcommerceCore\Classes\CurrencyHelper::formatPrice($orderPayment->amount - $order->total) }}
                                                </p>
                                            @endif
                                        @endif
                                    </div>
                                </div>
                            @endforeach
                        </div>
                    @endif
                    <div class="grid gap-4 mt-16">
                        <button wire:click="closeOrderConfirmation"
                                class="px-4 py-4 text-lg uppercase rounded-lg bg-primary-500 hover:bg-primary-700 transition-all ease-in-out duration-300 text-white font-bold w-full">
                            Terug naar POS
                        </button>
                    </div>
                </div>
            </div>
        </div>
    @endif
    @if($showOrder)
        <div class="fixed inset-0 flex items-center justify-center z-50 text-black">
            <div class="absolute h-full w-full" wire:click="closeShowOrder"></div>
            <div class="bg-white h-[95%] w-[95%] rounded-lg p-8 grid gap-4 relative">
                <div class="bg-white rounded-lg p-8 grid gap-4 overflow-y-scroll">
                    <div class="absolute top-2 right-2 text-black cursor-pointer"
                         wire:click="closeShowOrder">
                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5"
                             stroke="currentColor" class="size-10">
                            <path stroke-linecap="round" stroke-linejoin="round"
                                  d="m9.75 9.75 4.5 4.5m0-4.5-4.5 4.5M21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z"/>
                        </svg>
                    </div>
                    <div>
                        <div class="grid gap-4">
                            <div>
                                <h3 class="font-bold text-2xl">Bestelling #{{ $showOrder->invoice_id }}
                                    van {{$showOrder->name}}</h3>
                            </div>
                            <div class="flex flex-wrap items-center gap-2">
                                <button wire:click="printReceipt(@js($showOrder), true)"
                                        class="px-4 py-4 text-lg rounded-lg bg-primary-500 hover:bg-primary-700 transition-all ease-in-out duration-300 text-white font-bold">
                                    Print bon
                                </button>
                                @if(!$showOrder->credit_for_order_id)
                                    <div>
                                        @livewire('cancel-order', ['order' => $showOrder, 'isPos' => true, 'buttonText'
                                        => 'Retour aanmaken', 'buttonClass' => 'px-4 py-4 text-lg uppercase rounded-lg
                                        bg-primary-500 hover:bg-primary-700 transition-all ease-in-out duration-300
                                        text-white font-bold'])
                                    </div>
                                @endif
                            </div>
                            <div>
                                @livewire('order-view-statusses', ['order' => $showOrder])
                            </div>
                            <div>
                                @livewire('order-order-products-list', ['order' => $showOrder])
                            </div>
                            <div>
                                @livewire('order-shipping-information-list', ['order' => $showOrder])
                            </div>
                            <div>
                                @livewire('order-payment-information-list', ['order' => $showOrder])
                            </div>
                            <div>
                                @livewire('order-payments-list', ['order' => $showOrder])
                            </div>
                            <div>
                                @livewire('order-logs-list', ['order' => $showOrder])
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    @endif
    @script
    <script>
        $wire.on('focus', () => {
            document.getElementById("search-product-query").focus();
        });
        $wire.on('focusSearchOrder', () => {
            document.getElementById("searchOrderData.order_id").focus();
        });

        function requestFullscreen() {
            const elem = document.documentElement; // Can be any element, here we use the whole document

            if (elem.requestFullscreen) {
                elem.requestFullscreen();
            } else if (elem.mozRequestFullScreen) { // For Firefox
                elem.mozRequestFullScreen();
            } else if (elem.webkitRequestFullscreen) { // For Chrome, Safari, and Opera
                elem.webkitRequestFullscreen();
            } else if (elem.msRequestFullscreen) { // For Internet Explorer/Edge
                elem.msRequestFullscreen();
            }
        }

        function exitFullscreen() {
            if (document.exitFullscreen) {
                document.exitFullscreen();
            } else if (document.mozCancelFullScreen) {
                document.mozCancelFullScreen();
            } else if (document.webkitExitFullscreen) {
                document.webkitExitFullscreen();
            } else if (document.msExitFullscreen) {
                document.msExitFullscreen();
            }
        }

        document.addEventListener('fullscreenchange', () => {
            isFullscreen();
        });

        function isFullscreen() {
            var isFullscreen = document.fullscreenElement ||
                document.webkitFullscreenElement ||
                document.mozFullScreenElement ||
                document.msFullscreenElement;
            if (isFullscreen) {
                $wire.dispatch('fullscreenValue', {fullscreen: true});
            } else {
                $wire.dispatch('fullscreenValue', {fullscreen: false});
            }
        }

        document.getElementById("fullscreenBtn").addEventListener("click", function () {
            requestFullscreen();
        });
        document.getElementById("exitFullscreenBtn").addEventListener("click", function () {
            exitFullscreen();
        });
    </script>
    @endscript
</div>
