<div class="relative inline-flex w-full"
     x-data="POSData()">
    <div
        class="absolute transitiona-all duration-1000 opacity-70 -inset-px bg-gradient-to-r from-primary-300 via-primary-300 to-primary-800 rounded-xl blur-lg group-hover:opacity-100 group-hover:-inset-1 group-hover:duration-200 animate-tilt">
    </div>
    <div class="p-8 m-8 border border-white rounded-lg h-[calc(100%) - 50px] overflow-hidden bg-black/90 z-10 w-full">
        <div class="grid grid-cols-10 divide-x divide-gray-500">
            <div class="sm:col-span-5 sm:pr-8 flex flex-col gap-8">
                <div class="flex flex-wrap justify-between items-center">
                    <p class="font-bold text-5xl">{{ Customsetting::get('site_name') }}</p>
                    <div class="flex flex-wrap gap-2">
                        <button x-cloak x-show="lastOrder"
                                @click="printLastOrder"
                                class="h-12 w-12 bg-primary-500 text-white hover:bg-primary-700 transition-all duration-300 ease-in-out p-1 rounded-full flex items-center justify-center">
                            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24"
                                 stroke-width="1.5"
                                 stroke="currentColor" class="size-6">
                                <path stroke-linecap="round" stroke-linejoin="round"
                                      d="M19.5 12c0-1.232-.046-2.453-.138-3.662a4.006 4.006 0 0 0-3.7-3.7 48.678 48.678 0 0 0-7.324 0 4.006 4.006 0 0 0-3.7 3.7c-.017.22-.032.441-.046.662M19.5 12l3-3m-3 3-3-3m-12 3c0 1.232.046 2.453.138 3.662a4.006 4.006 0 0 0 3.7 3.7 48.656 48.656 0 0 0 7.324 0 4.006 4.006 0 0 0 3.7-3.7c.017-.22.032-.441.046-.662M4.5 12l3 3m-3-3-3 3"/>
                            </svg>
                        </button>
                    </div>
                </div>
                <form @submit.prevent="selectProduct">
                    <div class="w-full relative">
                    <span class="text-black absolute left-2 top-2">
                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5"
                             stroke="currentColor" class="size-6">
                          <path stroke-linecap="round" stroke-linejoin="round"
                                d="m21 21-5.197-5.197m0 0A7.5 7.5 0 1 0 5.196 5.196a7.5 7.5 0 0 0 10.607 10.607Z"/>
                        </svg>
                    </span>
                        <input autofocus x-model.debounce.50ms="searchProductQuery"
                               id="search-product-query"
                               :inputmode="searchQueryInputmode"
                               placeholder="Zoek een product op naam, SKU of barcode..."
                               class="text-black w-full rounded-lg pl-10 pr-10">
                        <p class="absolute right-2 top-2 text-black cursor-pointer"
                           @click="toggle('searchQueryInputmode')">
                                                        <span x-show="searchQueryInputmode" x-cloak>
                        <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none"
                             stroke="currentColor"
                             stroke-width="2" stroke-linecap="round" stroke-linejoin="round"
                             class="lucide lucide-keyboard-off"><path
                                d="M 20 4 A2 2 0 0 1 22 6"/><path d="M 22 6 L 22 16.41"/><path d="M 7 16 L 16 16"/><path
                                d="M 9.69 4 L 20 4"/><path d="M14 8h.01"/><path d="M18 8h.01"/><path
                                d="m2 2 20 20"/><path
                                d="M20 20H4a2 2 0 0 1-2-2V6a2 2 0 0 1 2-2"/><path d="M6 8h.01"/><path
                                d="M8 12h.01"/></svg>
                                                </span>
                            <span x-show="!searchQueryInputmode" x-cloak>
                                                    <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24"
                                                         viewBox="0 0 24 24"
                                                         fill="none" stroke="currentColor" stroke-width="2"
                                                         stroke-linecap="round"
                                                         stroke-linejoin="round" class="lucide lucide-keyboard"><path
                                                            d="M10 8h.01"/><path
                                                            d="M12 12h.01"/><path d="M14 8h.01"/><path
                                                            d="M16 12h.01"/><path d="M18 8h.01"/><path
                                                            d="M6 8h.01"/><path d="M7 16h10"/><path d="M8 12h.01"/><rect
                                                            width="20"
                                                            height="16" x="2"
                                                            y="4"
                                                            rx="2"/></svg>
                                                </span>
                        </p>
                        <div class="absolute z-50 bg-white rounded-lg mt-2 shadow-xl" x-cloak
                             x-show="!loadingSearchedProducts && searchProductQuery && searchedProducts.length">
                            <div class="overflow-y-auto max-h-96">
                                <ul class="border-t divide-y border-black/5 divide-black/5">
                                    <template x-for="product in searchedProducts">
                                        <li class="grid relative items-center grid-cols-2 gap-6 p-4 lg:grid-cols-3">
                                            <div class="cursor-pointer"
                                                 x-show="product.image"
                                                 @click="addProduct(product.id)">
                                                <img :src="product.image"
                                                     class="object-cover aspect-[3/2] rounded-lg max-h-[100px]">
                                            </div>

                                            <div @click="addProduct(product.id)"
                                                 class="lg:col-span-2 cursor-pointer text-wrap max-w-[300px] md:max-w-[400px]">
                                                <p class="font-medium text-black">
                                                    <span x-html="product.name"></span>
                                                    <span x-html="'(' + product.currentPriceFormatted + ')'"></span>
                                                </p>
                                            </div>
                                        </li>
                                    </template>
                                </ul>
                            </div>
                        </div>
                        <div class="absolute z-50 bg-white rounded-lg mt-2 shadow-xl" x-cloak
                             x-show="!loadingSearchedProducts && searchProductQuery && !searchedProducts.length">
                            <div class="p-4">
                                <p class="text-center text-black">Geen producten gevonden</p>
                            </div>
                        </div>
                        <div class="absolute z-50 bg-white rounded-lg mt-2 shadow-xl" x-cloak
                             x-show="loadingSearchedProducts">
                            <div class="p-4">
                                <p class="text-center text-black">Producten aan het laden</p>
                            </div>
                        </div>
                    </div>
                </form>
                <div class="grid gap-4 sm:grid-cols-2">
                    <button @click="toggle('customProductPopup')"
                            {{--                    <button wire:click="toggleVariable('customProductPopup')"--}}
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
                    <button @click="removeDiscount"
                            x-cloak x-show="activeDiscountCode"
                            class="text-left rounded-lg bg-red-500/40 hover:bg-red-500/70 transition-all duration-300 ease-in-out h-[150px] flex flex-col justify-between p-4 font-medium text-xl">
                                            <span>
                    <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none"
                         stroke="currentColor"
                         stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="lucide lucide-percent"><line
                            x1="19" x2="5"
                            y1="5"
                            y2="19"/><circle
                            cx="6.5" cy="6.5" r="2.5"/><circle cx="17.5" cy="17.5" r="2.5"/></svg>
                                            </span>
                        <p>Korting verwijderen</p>
                    </button>
                    <button @click="toggle('createDiscountPopup')"
                            x-cloak x-show="!activeDiscountCode"
                            class="text-left rounded-lg bg-primary-500/40 hover:bg-primary-500/70 transition-all duration-300 ease-in-out h-[150px] flex flex-col justify-between p-4 font-medium text-xl">
                                            <span>
                    <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none"
                         stroke="currentColor"
                         stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="lucide lucide-percent"><line
                            x1="19" x2="5"
                            y1="5"
                            y2="19"/><circle
                            cx="6.5" cy="6.5" r="2.5"/><circle cx="17.5" cy="17.5" r="2.5"/></svg>
                                            </span>
                        <p>Korting toepassen</p>
                    </button>
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
                    <button @click="openCashRegister"
                            x-cloak x-show="hasCashRegister"
                            class="text-left rounded-lg bg-primary-500/40 hover:bg-primary-500/70 transition-all duration-300 ease-in-out h-[150px] flex flex-col justify-between p-4 font-medium text-xl">
                                            <span>
                    <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none"
                         stroke="currentColor"
                         stroke-width="2" stroke-linecap="round" stroke-linejoin="round"
                         class="lucide lucide-hand-coins"><path
                            d="M11 15h2a2 2 0 1 0 0-4h-3c-.6 0-1.1.2-1.4.6L3 17"/><path
                            d="m7 21 1.6-1.4c.3-.4.8-.6 1.4-.6h4c1.1 0 2.1-.4 2.8-1.2l4.6-4.4a2 2 0 0 0-2.75-2.91l-4.2 3.9"/><path
                            d="m2 16 6 6"/><circle cx="16" cy="9" r="2.9"/><circle cx="6" cy="5" r="3"/></svg>
                                            </span>
                        <p>Kassa lade openen</p>
                    </button>
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
                            <button x-cloak x-show="products.length" @click="clearProducts"
                                    class="h-12 w-12 bg-red-500 text-white hover:bg-red-700 transition-all duration-300 ease-in-out p-1 rounded-full flex items-center justify-center">
                                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24"
                                     stroke-width="1.5"
                                     stroke="currentColor" class="size-6">
                                    <path stroke-linecap="round" stroke-linejoin="round"
                                          d="m14.74 9-.346 9m-4.788 0L9.26 9m9.968-3.21c.342.052.682.107 1.022.166m-1.022-.165L18.16 19.673a2.25 2.25 0 0 1-2.244 2.077H8.084a2.25 2.25 0 0 1-2.244-2.077L4.772 5.79m14.456 0a48.108 48.108 0 0 0-3.478-.397m-12 .562c.34-.059.68-.114 1.022-.165m0 0a48.11 48.11 0 0 1 3.478-.397m7.5 0v-.916c0-1.18-.91-2.164-2.09-2.201a51.964 51.964 0 0 0-3.32 0c-1.18.037-2.09 1.022-2.09 2.201v.916m7.5 0a48.667 48.667 0 0 0-7.5 0"/>
                                </svg>
                            </button>
                            {{--                            <button id="exitFullscreenBtn"--}}
                            {{--                                    class="@if(!$fullscreen) hidden @endif h-12 w-12 bg-primary-500 text-white hover:bg-primary-700 transition-all duration-300 ease-in-out p-1 rounded-full flex items-center justify-center">--}}
                            {{--                                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24"--}}
                            {{--                                     stroke-width="1.5" stroke="currentColor" class="size-6">--}}
                            {{--                                    <path stroke-linecap="round" stroke-linejoin="round"--}}
                            {{--                                          d="M9 9V4.5M9 9H4.5M9 9 3.75 3.75M9 15v4.5M9 15H4.5M9 15l-5.25 5.25M15 9h4.5M15 9V4.5M15 9l5.25-5.25M15 15h4.5M15 15v4.5m0-4.5 5.25 5.25"/>--}}
                            {{--                                </svg>--}}
                            {{--                            </button>--}}
                            {{--                            <button id="fullscreenBtn"--}}
                            {{--                                    class="@if($fullscreen) hidden @endif h-12 w-12 bg-primary-500 text-white hover:bg-primary-700 transition-all duration-300 ease-in-out p-1 rounded-full flex items-center justify-center">--}}
                            {{--                                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24"--}}
                            {{--                                     stroke-width="1.5" stroke="currentColor" class="size-6">--}}
                            {{--                                    <path stroke-linecap="round" stroke-linejoin="round"--}}
                            {{--                                          d="M3.75 3.75v4.5m0-4.5h4.5m-4.5 0L9 9M3.75 20.25v-4.5m0 4.5h4.5m-4.5 0L9 15M20.25 3.75h-4.5m4.5 0v4.5m0-4.5L15 9m5.25 11.25h-4.5m4.5 0v-4.5m0 4.5L15 15"/>--}}
                            {{--                                </svg>--}}
                            {{--                            </button>--}}
                        </div>
                    </div>
                    {{--                <div class="p-4 rounded-lg border border-gray-400 grid gap-4">--}}
                    <div
                        class="p-4 rounded-lg border border-gray-400 h-[calc(100vh-550px)] overflow-y-auto">

                        <div x-show="products.length > 0" x-cloak class="flex flex-col gap-4">
                            <template x-for="product in products">
                                <div class="flex flex-wrap items-center gap-4">
                                    <div class="relative">
                                        <img :src="product.image" x-cloak x-show="product.image"
                                             class="object-cover rounded-lg w-20 h-20">
                                        <img x-cloak x-show="product.customProduct === true"
                                             src="https://placehold.co/400x400/{{ str(collect(collect(filament()->getPanels())->first()->getColors())->first())->replace('#', '') }}/fff?text=Aangepaste%20verkoop"
                                             class="object-cover rounded-lg w-20 h-20">
                                        <span class="bg-primary-500 text-white font-bold rounded-full w-6 h-6 absolute -right-2 -top-2 flex items-center justify-center border-2 border-white"
                                              x-html="product.quantity">
                                        </span>
                                    </div>
                                    <div class="flex flex-col flex-wrap gap-1 flex-1">
                                        <span class="font-bold text-lg word-wrap" x-html="product.name"></span>
                                        <div class="flex flex-wrap gap-2">
                                            <button
                                                @click="changeQuantity(product.identifier, product.quantity + 1)"
                                                class="h-12 w-12 bg-primary-500 text-white hover:bg-primary-700 transition-all duration-300 ease-in-out p-1 rounded-full flex items-center justify-center">
                                                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24"
                                                     stroke-width="1.5" stroke="currentColor" class="size-6">
                                                    <path stroke-linecap="round" stroke-linejoin="round"
                                                          d="M12 4.5v15m7.5-7.5h-15"/>
                                                </svg>
                                            </button>
                                            <button
                                                @click="changeQuantity(product.identifier, product.quantity - 1)"
                                                class="h-12 w-12 bg-primary-500 text-white hover:bg-primary-700 transition-all duration-300 ease-in-out p-1 rounded-full flex items-center justify-center">
                                                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24"
                                                     stroke-width="1.5" stroke="currentColor" class="size-6">
                                                    <path stroke-linecap="round" stroke-linejoin="round" d="M5 12h14"/>
                                                </svg>
                                            </button>
                                            <button
                                                @click="changeQuantity(product.identifier, 0)"
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
                                                                        <span class="font-bold">
{{--                                                                            {{ \Dashed\DashedEcommerceCore\Classes\CurrencyHelper::formatPrice($product['price']) }}--}}
                                                                        </span>
                                    </div>
                                </div>
                            </template>
                            {{--                            @endforeach--}}
                        </div>
                        <p x-show="products.length === 0">Geen producten geselecteerd...</p>
                    </div>
                </div>
                <div class="mt-auto flex-1 gap-4 grid">
                    <div class="grid gap-2 p-4 rounded-lg border border-gray-400">
                        <div class="text-xl font-bold grid gap-2">
                            <div class="flex items-center justify-between">
                                <div class="flex flex-col">
                                    <span>Totaal</span>
                                    <span class="text-sm font-normal"
                                          x-html="totalQuantity() + ' artikelen'">0 artikelen</span>
                                </div>
                                <span class="font-bold" x-html="total"></span>
                            </div>
                            <hr>
                            <div x-show="activeDiscountCode" x-cloak>
                                <div class="text-sm font-bold flex justify-between items-center mb-2">
                                    <span>Korting</span>
                                    <span class="font-bold" x-html="discount"></span>
                                </div>
                                <hr/>
                            </div>
                            {{--                        <hr/>--}}
                            {{--                        <span class="text-sm font-bold flex justify-between items-center">--}}
                            {{--                        <span>Subtotaal</span>--}}
                            {{--                    <span class="font-bold">{{ $subTotal }}</span>--}}
                            {{--                </span>--}}
                            <template x-for="value,percentage in vatPercentages" x-show="vatPercentages.length">
                                <div class="text-sm font-bold flex justify-between items-center">
                                    <span x-html="'BTW ' + percentage + '%'"></span>
                                    <span class="font-bold" x-html="value"></span>
                                </div>
                            </template>
                            <hr x-cloak x-show="vatPercentages.length > 1"/>
                            <div x-cloak x-show="vatPercentages.length > 1"
                                 class="text-sm font-bold flex justify-between items-center">
                                <span>BTW</span>
                                <span class="font-bold" x-html="vat"></span>
                            </div>
                        </div>
                    </div>
                    <button @click="toggle('checkoutPopup')"
                            class="px-4 py-2 text-lg uppercase rounded-lg bg-primary-500 hover:bg-primary-700 transition-all ease-in-out duration-300 text-white font-bold w-full">
                        Checkout
                    </button>
                </div>
            </div>
        </div>
    </div>
    <div
        x-show="customProductPopup"
        x-cloak
        x-transition.opacity.scale.origin
        class="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50 text-black">
        <div class="absolute h-full w-full" @click="toggle('customProductPopup')"></div>
        <div class="bg-white rounded-lg p-8 grid gap-4 relative">
            <div class="absolute top-2 right-2 text-black cursor-pointer"
                 @click="toggle('customProductPopup')">
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
    <div
        x-show="createDiscountPopup"
        x-cloak
        x-transition.opacity.scale.origin
        class="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50 text-black">
        <div class="absolute h-full w-full" @click="toggle('createDiscountPopup')"></div>
        <div class="bg-white rounded-lg p-8 grid gap-4 relative">
            <div class="bg-white rounded-lg p-8 grid gap-4">
                <div class="absolute top-2 right-2 text-black cursor-pointer"
                     @click="toggle('createDiscountPopup')">
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
    {{--    @if($searchOrderPopup)--}}
    {{--        <div class="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50 text-black">--}}
    {{--            <div class="absolute h-full w-full" wire:click="toggleVariable('searchOrderPopup')"></div>--}}
    {{--            <div class="bg-white rounded-lg p-8 grid gap-4 relative">--}}
    {{--                <div class="bg-white rounded-lg p-8 grid gap-4">--}}
    {{--                    <div class="absolute top-2 right-2 text-black cursor-pointer"--}}
    {{--                         wire:click="toggleVariable('searchOrderPopup')">--}}
    {{--                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5"--}}
    {{--                             stroke="currentColor" class="size-10">--}}
    {{--                            <path stroke-linecap="round" stroke-linejoin="round"--}}
    {{--                                  d="m9.75 9.75 4.5 4.5m0-4.5-4.5 4.5M21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z"/>--}}
    {{--                        </svg>--}}
    {{--                    </div>--}}
    {{--                    <p class="text-3xl font-bold">Zoek een bestelling op ID</p>--}}
    {{--                    <form wire:submit.prevent="submitSearchOrderForm">--}}
    {{--                        <div class="grid gap-4">--}}
    {{--                            {{ $this->searchOrderForm }}--}}
    {{--                            <div>--}}
    {{--                                <button type="submit"--}}
    {{--                                        class="px-4 py-2 text-lg uppercase rounded-lg bg-primary-500 hover:bg-primary-700 transition-all ease-in-out duration-300 text-white font-bold w-full">--}}
    {{--                                    Zoeken--}}
    {{--                                </button>--}}
    {{--                            </div>--}}
    {{--                        </div>--}}
    {{--                    </form>--}}
    {{--                </div>--}}
    {{--            </div>--}}
    {{--        </div>--}}
    {{--    @endif--}}
    <div
        x-show="checkoutPopup"
        x-cloak
        x-transition.opacity.scale.origin
        class="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50 text-black">
        <div class="absolute h-full w-full" @click="toggle('checkoutPopup')"></div>
        <div class="bg-white rounded-lg p-8 grid gap-4 relative sm:min-w-[800px]">
            <div class="bg-white rounded-lg p-8 grid gap-4">
                <div class="absolute top-2 right-2 text-black cursor-pointer"
                     @click="toggle('checkoutPopup')">
                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5"
                         stroke="currentColor" class="size-10">
                        <path stroke-linecap="round" stroke-linejoin="round"
                              d="m9.75 9.75 4.5 4.5m0-4.5-4.5 4.5M21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z"/>
                    </svg>
                </div>
                <div>
                    <p class="text-3xl font-bold" x-html="'Totaal: ' + total"></p>
                    <p class="text-xl text-gray-400">Selecteer betalingsoptie</p>
                </div>
                <div class="grid gap-8" x-show="paymentMethods.length">
                    <template x-for="paymentMethod in paymentMethods">
                        <button @click="selectPaymentMethod(paymentMethod.id)"
                                class="p-4 text-2xl uppercase rounded-lg bg-primary-500 hover:bg-primary-700 transition-all ease-in-out duration-300 text-white font-bold w-full flex items-center flex-wrap justify-between">
                            <img
                                x-show="paymentMethod.image"
                                :src="paymentMethod.image"
                                class="h-20 mr-2">
                            <img
                                x-show="!paymentMethod.image"
                                :src="'https://placehold.co/400x400/000/{{ str(collect(collect(filament()->getPanels())->first()->getColors())->first())->replace('#', '') }}?text=' + paymentMethod.name"
                                class="object-cover rounded-lg h-20">
                            <span x-html="paymentMethod.name"></span>
                        </button>
                    </template>
                </div>
                <div class="p-4" x-show="!paymentMethods.length">
                    <p class="text-center text-black">Geen betaalmethodes gevonden</p>
                </div>
            </div>
        </div>
    </div>
    <div
        x-show="paymentPopup"
        x-cloak
        x-transition.opacity.scale.origin
        class="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50 text-black">
        <div class="absolute h-full w-full" @click="toggle('paymentPopup')"></div>
        <div class="bg-white rounded-lg p-8 grid gap-4 relative sm:min-w-[800px]">
            <div class="bg-white rounded-lg p-8 grid gap-4">
                <div class="absolute top-2 right-2 text-black cursor-pointer"
                     @click="toggle('paymentPopup')">
                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5"
                         stroke="currentColor" class="size-10">
                        <path stroke-linecap="round" stroke-linejoin="round"
                              d="m9.75 9.75 4.5 4.5m0-4.5-4.5 4.5M21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z"/>
                    </svg>
                </div>
                <div>
                    <p class="text-3xl font-bold" x-html="'Totaal: ' + total"></p>
                    <p class="text-xl text-gray-400" x-html="'Betaalmethode: ' + chosenPaymentMethod.name"></p>
                </div>
                <div class="flex flex-col gap-4" x-show="chosenPaymentMethod.isCashPayment">
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
                        <input wire:model="cashPaymentAmount"
                               type="number"
                               min="0"
                               max="100000"
                               required
                               class="text-black w-full rounded-lg pl-4 pr-4"
                               placeholder="Anders...">
                    </form>
                </div>
                {{--                @if($isPinTerminalPayment)--}}
                {{--                    @if($pinTerminalStatus == 'pending')--}}
                {{--                        <div>--}}
                {{--                            <p class="text-3xl">{{ Translation::get('pin-transaction-started', 'point-of-sale', 'De klant mag nu pinnen.') }}</p>--}}
                {{--                            @if($order->paidAmount > 0)--}}
                {{--                                <p class="text-xl text-gray-400">Al--}}
                {{--                                    betaald: {{ \Dashed\DashedEcommerceCore\Classes\CurrencyHelper::formatPrice($order->paidAmount) }}</p>--}}
                {{--                            @endif--}}
                {{--                        </div>--}}
                {{--                    @elseif($pinTerminalStatus == 'waiting_for_clearance')--}}
                {{--                        <p class="text-3xl">{{ Translation::get('pin-terminal-in-use', 'point-of-sale', 'De pin terminal is in gebruik, wacht tot deze vrijgegeven is.') }}</p>--}}
                {{--                        <button wire:click="startPinTerminalPayment"--}}
                {{--                                class="w-full px-4 py-4 text-lg uppercase rounded-lg bg-primary-500 hover:bg-primary-700 transition-all ease-in-out duration-300 text-white font-bold w-full flex items-center justify-center gap-1">--}}
                {{--                            <span>Start betaling opnieuw</span>--}}
                {{--                        </button>--}}
                {{--                    @elseif($pinTerminalStatus == 'timed_out')--}}
                {{--                        <p class="text-3xl">{{ Translation::get('pin-terminal-payment-timed-out', 'point-of-sale', 'De betaling is niet optijd voltooid, probeer het opnieuw.') }}</p>--}}
                {{--                        <button wire:click="startPinTerminalPayment"--}}
                {{--                                class="w-full px-4 py-4 text-lg uppercase rounded-lg bg-primary-500 hover:bg-primary-700 transition-all ease-in-out duration-300 text-white font-bold w-full flex items-center justify-center gap-1">--}}
                {{--                            <span>Start betaling opnieuw</span>--}}
                {{--                        </button>--}}
                {{--                    @elseif($pinTerminalStatus == 'cancelled_by_customer')--}}
                {{--                        <p class="text-3xl">{{ Translation::get('pin-terminal-payment-timed-out', 'point-of-sale', 'De betaling is niet voltooid door de klant.') }}</p>--}}
                {{--                        <button wire:click="startPinTerminalPayment"--}}
                {{--                                class="w-full px-4 py-4 text-lg uppercase rounded-lg bg-primary-500 hover:bg-primary-700 transition-all ease-in-out duration-300 text-white font-bold w-full flex items-center justify-center gap-1">--}}
                {{--                            <span>Start betaling opnieuw</span>--}}
                {{--                        </button>--}}
                {{--                    @elseif($pinTerminalError)--}}
                {{--                        <p class="text-3xl">{{ $pinTerminalErrorMessage }}</p>--}}
                {{--                        <button wire:click="startPinTerminalPayment"--}}
                {{--                                class="w-full px-4 py-4 text-lg uppercase rounded-lg bg-primary-500 hover:bg-primary-700 transition-all ease-in-out duration-300 text-white font-bold w-full flex items-center justify-center gap-1">--}}
                {{--                            <span>Probeer betaling opnieuw</span>--}}
                {{--                        </button>--}}
                {{--                    @endif--}}
                {{--                @endif--}}
                {{--                <div class="grid md:grid-cols-2 gap-4 mt-16">--}}
                {{--                    @if($isPinTerminalPayment && $pinTerminalStatus == 'pending')--}}
                {{--                        <button wire:poll.1s="checkPinTerminalPayment" disabled--}}
                {{--                                class="md:col-span-2 px-4 py-4 text-lg uppercase rounded-lg bg-primary-500 hover:bg-primary-700 transition-all ease-in-out duration-300 text-white font-bold w-full flex items-center justify-center gap-1">--}}
                {{--                            <svg class="animate-spin -ml-1 mr-3 h-5 w-5 text-white"--}}
                {{--                                 xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">--}}
                {{--                                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor"--}}
                {{--                                        stroke-width="4"></circle>--}}
                {{--                                <path class="opacity-75" fill="currentColor"--}}
                {{--                                      d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>--}}
                {{--                            </svg>--}}
                {{--                            <span>Wachten op betaling...</span>--}}
                {{--                        </button>--}}
                {{--                    @elseif(!$isPinTerminalPayment)--}}
                {{--                        <button wire:click="closePayment"--}}
                {{--                                class="px-4 py-4 text-lg uppercase rounded-lg bg-red-500 hover:bg-red-700 transition-all ease-in-out duration-300 text-white font-bold w-full">--}}
                {{--                            Annuleren--}}
                {{--                        </button>--}}
                {{--                        @if(!$cashPaymentAmount)--}}
                {{--                            <button disabled--}}
                {{--                                    class="px-4 py-4 text-lg uppercase rounded-lg bg-primary-700 transition-all ease-in-out duration-300 text-white font-bold w-full">--}}
                {{--                                Vul een bedrag in--}}
                {{--                            </button>--}}
                {{--                        @elseif($cashPaymentAmount < $totalUnformatted)--}}
                {{--                            <button wire:click="createPaymentWithExtraPayment"--}}
                {{--                                    class="px-4 py-4 text-lg uppercase rounded-lg bg-primary-500 hover:bg-primary-700 transition-all ease-in-out duration-300 text-white font-bold w-full">--}}
                {{--                                Restbedrag bijpinnen--}}
                {{--                            </button>--}}
                {{--                        @else--}}
                {{--                            <button wire:click="markAsPaid"--}}
                {{--                                    class="px-4 py-4 text-lg uppercase rounded-lg bg-primary-500 hover:bg-primary-700 transition-all ease-in-out duration-300 text-white font-bold w-full">--}}
                {{--                                Markeer als betaald--}}
                {{--                            </button>--}}
                {{--                        @endif--}}
                {{--                    @endif--}}
                {{--                </div>--}}
            </div>
        </div>
    </div>
    {{--    @if($orderConfirmationPopup)--}}
    {{--        <div class="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50 text-black">--}}
    {{--            <div class="absolute h-full w-full" wire:click="closeOrderConfirmation"></div>--}}
    {{--            <div class="bg-white rounded-lg p-8 grid gap-4 relative sm:min-w-[800px]">--}}
    {{--                <div class="bg-white rounded-lg p-8 grid gap-4">--}}
    {{--                    <div class="absolute top-2 right-2 text-black cursor-pointer"--}}
    {{--                         wire:click="closeOrderConfirmation">--}}
    {{--                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5"--}}
    {{--                             stroke="currentColor" class="size-10">--}}
    {{--                            <path stroke-linecap="round" stroke-linejoin="round"--}}
    {{--                                  d="m9.75 9.75 4.5 4.5m0-4.5-4.5 4.5M21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z"/>--}}
    {{--                        </svg>--}}
    {{--                    </div>--}}
    {{--                    <div>--}}
    {{--                        <p class="text-3xl font-bold">Bestelling {{ $order->invoice_id }} afgerond--}}
    {{--                            - {{ \Dashed\DashedEcommerceCore\Classes\CurrencyHelper::formatPrice($order->total) }}</p>--}}
    {{--                        <p class="text-xl text-gray-400">--}}
    {{--                            Betaalmethode: {{ $order->orderPayments()->first()->paymentMethod->name }}--}}
    {{--                        </p>--}}
    {{--                    </div>--}}
    {{--                    @if($order->orderPayments()->first()->paymentMethod->is_cash_payment)--}}
    {{--                        <div class="flex flex-col gap-4">--}}
    {{--                            <p class="text-xl font-bold">Betaling overzicht</p>--}}
    {{--                            @foreach($order->orderPayments()->where('status', 'paid')->get() as $orderPayment)--}}
    {{--                                <div--}}
    {{--                                    class="flex flex-wrap items-center justify-between border border-gray-400 rounded-lg p-4 gap-4">--}}
    {{--                                    <div class="flex flex-col">--}}
    {{--                                        <p class="font-bold text-lg">Betaling {{ $loop->iteration }}</p>--}}
    {{--                                        <p class="text-gray-400">{{ $orderPayment->paymentMethod->name }}</p>--}}
    {{--                                    </div>--}}
    {{--                                    <div class="flex flex-col">--}}
    {{--                                        <p class="font-bold text-xl">{{ \Dashed\DashedEcommerceCore\Classes\CurrencyHelper::formatPrice($orderPayment->amount) }}</p>--}}
    {{--                                        @if($loop->first)--}}
    {{--                                            @if($order->total < $order->paidAmount)--}}
    {{--                                                <p class="text-warning-500 font-bold text-xl">--}}
    {{--                                                    Wisselgeld--}}
    {{--                                                    verschuldigd: {{ \Dashed\DashedEcommerceCore\Classes\CurrencyHelper::formatPrice($orderPayment->amount - $order->total) }}--}}
    {{--                                                </p>--}}
    {{--                                            @endif--}}
    {{--                                        @endif--}}
    {{--                                    </div>--}}
    {{--                                </div>--}}
    {{--                            @endforeach--}}
    {{--                        </div>--}}
    {{--                    @endif--}}
    {{--                    <div class="grid gap-4 mt-16">--}}
    {{--                        <button wire:click="closeOrderConfirmation"--}}
    {{--                                class="px-4 py-4 text-lg uppercase rounded-lg bg-primary-500 hover:bg-primary-700 transition-all ease-in-out duration-300 text-white font-bold w-full">--}}
    {{--                            Terug naar POS--}}
    {{--                        </button>--}}
    {{--                    </div>--}}
    {{--                </div>--}}
    {{--            </div>--}}
    {{--        </div>--}}
    {{--    @endif--}}
    {{--    @if($showOrder)--}}
    {{--        <div class="fixed inset-0 flex items-center justify-center z-50 text-black">--}}
    {{--            <div class="absolute h-full w-full" wire:click="closeShowOrder"></div>--}}
    {{--            <div class="bg-white h-[95%] w-[95%] rounded-lg p-8 grid gap-4 relative">--}}
    {{--                <div class="bg-white rounded-lg p-8 grid gap-4 overflow-y-scroll">--}}
    {{--                    <div class="absolute top-2 right-2 text-black cursor-pointer"--}}
    {{--                         wire:click="closeShowOrder">--}}
    {{--                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5"--}}
    {{--                             stroke="currentColor" class="size-10">--}}
    {{--                            <path stroke-linecap="round" stroke-linejoin="round"--}}
    {{--                                  d="m9.75 9.75 4.5 4.5m0-4.5-4.5 4.5M21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z"/>--}}
    {{--                        </svg>--}}
    {{--                    </div>--}}
    {{--                    <div>--}}
    {{--                        <div class="grid gap-4">--}}
    {{--                            <div>--}}
    {{--                                <h3 class="font-bold text-2xl">Bestelling #{{ $showOrder->invoice_id }}--}}
    {{--                                    van {{$showOrder->name}}</h3>--}}
    {{--                            </div>--}}
    {{--                            <div class="flex flex-wrap items-center gap-2">--}}
    {{--                                <button wire:click="printReceipt(@js($showOrder), true)"--}}
    {{--                                        class="px-4 py-4 text-lg rounded-lg bg-primary-500 hover:bg-primary-700 transition-all ease-in-out duration-300 text-white font-bold">--}}
    {{--                                    Print bon--}}
    {{--                                </button>--}}
    {{--                                @if(!$showOrder->credit_for_order_id)--}}
    {{--                                    <div>--}}
    {{--                                        @livewire('cancel-order', ['order' => $showOrder, 'isPos' => true, 'buttonText'--}}
    {{--                                        => 'Retour aanmaken', 'buttonClass' => 'px-4 py-4 text-lg uppercase rounded-lg--}}
    {{--                                        bg-primary-500 hover:bg-primary-700 transition-all ease-in-out duration-300--}}
    {{--                                        text-white font-bold'])--}}
    {{--                                    </div>--}}
    {{--                                @endif--}}
    {{--                            </div>--}}
    {{--                            <div>--}}
    {{--                                @livewire('order-view-statusses', ['order' => $showOrder])--}}
    {{--                            </div>--}}
    {{--                            <div>--}}
    {{--                                @livewire('order-order-products-list', ['order' => $showOrder])--}}
    {{--                            </div>--}}
    {{--                            <div>--}}
    {{--                                @livewire('order-shipping-information-list', ['order' => $showOrder])--}}
    {{--                            </div>--}}
    {{--                            <div>--}}
    {{--                                @livewire('order-payment-information-list', ['order' => $showOrder])--}}
    {{--                            </div>--}}
    {{--                            <div>--}}
    {{--                                @livewire('order-payments-list', ['order' => $showOrder])--}}
    {{--                            </div>--}}
    {{--                            <div>--}}
    {{--                                @livewire('order-logs-list', ['order' => $showOrder])--}}
    {{--                            </div>--}}
    {{--                        </div>--}}
    {{--                    </div>--}}
    {{--                </div>--}}
    {{--            </div>--}}
    {{--        </div>--}}
    {{--    @endif--}}
    @script
    <script>
        Alpine.data('POSData', () => ({
            cartInstance: 'point-of-sale',
            orderOrigin: 'pos',
            posIdentifier: '',
            userId: {{ auth()->user()->id }},
            searchQueryInputmode: false,
            searchProductQuery: '',
            lastOrder: null,
            products: [],
            loadingSearchedProducts: false,
            discountCode: null,
            discount: null,
            vat: null,
            vatPercentages: [],
            subTotal: null,
            total: null,
            activeDiscountCode: null,
            searchedProducts: [],
            paymentMethods: [],
            order: null,
            suggestedCashPaymentAmounts: [],
            chosenPaymentMethod: null,
            isPinTerminalPayment: false,
            totalQuantity() {
                return this.products.reduce((sum, product) => sum + product.quantity, 0);
            },

            customProductPopup: false,
            createDiscountPopup: false,
            checkoutPopup: false,
            paymentPopup: false,

            hasCashRegister: {{ Customsetting::get('cash_register_available', null, false) ? 'true' : 'false' }},

            toggle(variable) {
                if (variable in this) {
                    this[variable] = !this[variable];
                }
            },

            async openCashRegister() {
                try {
                    let response = await fetch('{{ route('api.point-of-sale.open-cash-register') }}', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                            'Accept': 'application/json',
                        }
                    });

                    let data = await response.json();

                    this.focus();

                    if (!response.ok) {
                        return $wire.dispatch('notify', {
                            type: 'danger',
                            message: data.message,
                        })
                    }

                    $wire.dispatch('notify', {
                        type: 'success',
                        message: 'De kassa is geopend'
                    })
                } catch (error) {
                    return $wire.dispatch('notify', {
                        type: 'danger',
                        message: 'De kassa kon niet worden geopend'
                    })
                }
            },

            async initialize() {
                try {
                    let response = await fetch('{{ route('api.point-of-sale.initialize') }}', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                            'Accept': 'application/json',
                        },
                        body: JSON.stringify({
                            userId: this.userId
                        })
                    });

                    let data = await response.json();

                    if (!response.ok) {
                        return $wire.dispatch('notify', {
                            type: 'danger',
                            message: data.message,
                        })
                    }

                    this.posIdentifier = data.posIdentifier;
                    this.products = data.products;
                    this.lastOrder = data.lastOrder;
                    this.retrieveCart();
                } catch (error) {
                    return $wire.dispatch('notify', {
                        type: 'danger',
                        message: 'De winkelwagen kon niet worden gestart'
                    })
                }
            },

            async retrieveCart() {
                try {
                    let response = await fetch('{{ route('api.point-of-sale.retrieve-cart') }}', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                            'Accept': 'application/json',
                        },
                        body: JSON.stringify({
                            cartInstance: this.cartInstance,
                            posIdentifier: this.posIdentifier,
                            discountCode: this.discountCode,
                        })
                    });

                    let data = await response.json();

                    if (!response.ok) {
                        return $wire.dispatch('notify', {
                            type: 'danger',
                            message: data.message,
                        })
                    }

                    this.products = data.products;
                    this.discountCode = data.discountCode;
                    this.activeDiscountCode = data.activeDiscountCode;
                    this.discount = data.discount;
                    this.vat = data.vat;
                    this.vatPercentages = data.vatPercentages;
                    this.subTotal = data.subTotal;
                    this.total = data.total;
                    this.paymentMethods = data.paymentMethods;
                } catch (error) {
                    return $wire.dispatch('notify', {
                        type: 'danger',
                        message: 'De winkelwagen kon niet worden opgehaald'
                    })
                }
            },

            async printLastOrder() {
                try {
                    let response = await fetch('{{ route('api.point-of-sale.print-receipt') }}', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                            'Accept': 'application/json',
                        },
                        body: JSON.stringify({
                            orderId: this.lastOrder.id,
                            isCopy: true,
                        })
                    });

                    let data = await response.json();

                    this.focus();

                    if (!response.ok) {
                        return $wire.dispatch('notify', {
                            type: 'danger',
                            message: data.message,
                        })
                    }
                } catch (error) {
                    return $wire.dispatch('notify', {
                        type: 'danger',
                        message: 'De bon kon niet worden geprint'
                    })
                }
            },

            async updateSearchedProducts() {
                try {
                    let response = await fetch('{{ route('api.point-of-sale.search-products') }}', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                            'Accept': 'application/json',
                        },
                        body: JSON.stringify({
                            search: this.searchProductQuery,
                        })
                    });

                    let data = await response.json();

                    this.searchedProducts = data.products;

                    if (!response.ok) {
                        return $wire.dispatch('notify', {
                            type: 'danger',
                            message: data.message,
                        })
                    }
                } catch (error) {
                    return $wire.dispatch('notify', {
                        type: 'danger',
                        message: 'De gezochte producten konden niet worden opgehaald'
                    })
                }
            },

            async addProduct(productId) {
                try {
                    let response = await fetch('{{ route('api.point-of-sale.add-product') }}', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                            'Accept': 'application/json',
                        },
                        body: JSON.stringify({
                            productSearchQuery: this.searchProductQuery,
                            productId: productId,
                            posIdentifier: this.posIdentifier,
                        })
                    });

                    let data = await response.json();
                    this.focus();

                    this.searchedProducts = [];
                    this.searchProductQuery = '';

                    if (!response.ok) {
                        return $wire.dispatch('notify', {
                            type: 'danger',
                            message: data.message,
                        })
                    }

                    this.products = data.products;
                    this.retrieveCart();
                } catch (error) {
                    return $wire.dispatch('notify', {
                        type: 'danger',
                        message: 'De gezochte producten konden niet worden opgehaald'
                    })
                }
            },

            async selectProduct() {
                try {
                    let response = await fetch('{{ route('api.point-of-sale.select-product') }}', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                            'Accept': 'application/json',
                        },
                        body: JSON.stringify({
                            productSearchQuery: this.searchProductQuery,
                            posIdentifier: this.posIdentifier,
                        })
                    });

                    let data = await response.json();
                    this.focus();

                    this.searchedProducts = [];
                    this.searchProductQuery = '';

                    if (!response.ok) {
                        return $wire.dispatch('notify', {
                            type: 'danger',
                            message: data.message,
                        })
                    }

                    this.products = data.products;
                    this.retrieveCart();
                } catch (error) {
                    return $wire.dispatch('notify', {
                        type: 'danger',
                        message: 'Het gezochte product konden niet worden opgehaald'
                    })
                }
            },

            async changeQuantity(productIdentifier, quantity) {
                try {
                    let response = await fetch('{{ route('api.point-of-sale.change-quantity') }}', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                            'Accept': 'application/json',
                        },
                        body: JSON.stringify({
                            posIdentifier: this.posIdentifier,
                            productIdentifier: productIdentifier,
                            quantity: quantity,
                        })
                    });

                    let data = await response.json();
                    this.focus();

                    if (!response.ok) {
                        return $wire.dispatch('notify', {
                            type: 'danger',
                            message: data.message,
                        })
                    }

                    this.products = data.products;
                    this.retrieveCart();
                } catch (error) {
                    return $wire.dispatch('notify', {
                        type: 'danger',
                        message: 'De gezochte producten konden niet worden opgehaald'
                    })
                }
            },

            async clearProducts() {
                try {
                    let response = await fetch('{{ route('api.point-of-sale.clear-products') }}', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                            'Accept': 'application/json',
                        },
                        body: JSON.stringify({
                            posIdentifier: this.posIdentifier,
                        })
                    });

                    let data = await response.json();
                    this.focus();

                    if (!response.ok) {
                        return $wire.dispatch('notify', {
                            type: 'danger',
                            message: data.message,
                        })
                    }

                    this.products = data.products;
                    this.retrieveCart();
                } catch (error) {
                    return $wire.dispatch('notify', {
                        type: 'danger',
                        message: 'De winkelmand kon niet worden geleegd'
                    })
                }
            },

            async removeDiscount() {
                try {
                    let response = await fetch('{{ route('api.point-of-sale.remove-discount') }}', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                            'Accept': 'application/json',
                        },
                        body: JSON.stringify({
                            posIdentifier: this.posIdentifier,
                        })
                    });

                    let data = await response.json();
                    this.focus();

                    if (!response.ok) {
                        return $wire.dispatch('notify', {
                            type: 'danger',
                            message: data.message,
                        })
                    }

                    this.retrieveCart();
                } catch (error) {
                    return $wire.dispatch('notify', {
                        type: 'danger',
                        message: 'De korting kon niet worden verwijderd'
                    })
                }
            },

            async selectPaymentMethod(paymentMethodId) {
                try {
                    let response = await fetch('{{ route('api.point-of-sale.select-payment-method') }}', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                            'Accept': 'application/json',
                        },
                        body: JSON.stringify({
                            posIdentifier: this.posIdentifier,
                            cartInstance: this.cartInstance,
                            orderOrigin: this.orderOrigin,
                            paymentMethodId: paymentMethodId,
                        })
                    });

                    let data = await response.json();
                    this.focus();

                    if (!response.ok) {
                        return $wire.dispatch('notify', {
                            type: 'danger',
                            message: data.message,
                        })
                    }

                    this.isPinTerminalPayment = data.isPinTerminalPayment;
                    this.chosenPaymentMethod = data.paymentMethod;
                    this.suggestedCashPaymentAmounts = data.suggestedCashPaymentAmounts;
                    this.order = data.order;

                    this.toggle('checkoutPopup');
                    this.toggle('paymentPopup');

                    if (this.isPinTerminalPayment) {
                        this.startPinTerminalPayment();
                    }

                } catch (error) {
                    return $wire.dispatch('notify', {
                        type: 'danger',
                        message: 'De betaalmethode kon niet worden geselecteerd'
                    })
                }
            },

            async startPinTerminalPayment() {
                try {
                    let response = await fetch('{{ route('api.point-of-sale.start-pin-terminal-payment') }}', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                            'Accept': 'application/json',
                        },
                        body: JSON.stringify({
                            posIdentifier: this.posIdentifier,
                            order: this.order,
                            paymentMethod: this.paymentMethod,
                            userId: this.userId,
                        })
                    });

                    let data = await response.json();
                    this.focus();

                    if (!response.ok) {
                        return $wire.dispatch('notify', {
                            type: 'danger',
                            message: data.message,
                        })
                    }

                    // this.isPinTerminalPayment = data.isPinTerminalPayment;
                    // this.paymentMethod = data.paymentMethod;
                    // this.suggestedCashPaymentAmounts = data.suggestedCashPaymentAmounts;
                    // this.order = data.order;

                    // this.toggle('checkoutPopup');
                    // this.toggle('paymentPopup');

                } catch (error) {
                    return $wire.dispatch('notify', {
                        type: 'danger',
                        message: 'De pin betaling kon niet worden gestart'
                    })
                }
            },

            focus() {
                document.getElementById("search-product-query").focus();
            },

            init() {
                $wire.on('toggle', (variable) => {
                    this.toggle(variable[0]);
                })

                $wire.on('addCustomProduct', (variable) => {
                    this.customProductPopup = false;
                    this.products.push(variable[0]);
                    this.focus();
                    this.retrieveCart();
                })

                $wire.on('discountCodeCreated', (variable) => {
                    this.createDiscountPopup = false;
                    this.focus();
                    this.retrieveCart();
                })

                this.initialize();

                $watch('searchProductQuery', (value, oldValue) => {
                    this.loadingSearchedProducts = true;
                    if (value.length > 2) {
                        this.updateSearchedProducts();
                    } else {
                        this.searchedProducts = [];
                    }
                    this.loadingSearchedProducts = false;
                });
            }
        }));
    </script>
    @endscript
</div>
