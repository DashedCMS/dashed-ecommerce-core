<div class="relative w-full h-full"
     x-data="POSData()">
    <div class="p-8 border-4 border-primary-500 overflow-hidden bg-black/90 z-10 w-full h-full">
        <div class="grid grid-cols-10 divide-x divide-primary-500 h-full">
            <div class="sm:col-span-7 sm:pr-8 flex flex-col gap-8 overflow-y-auto">
                <div class="flex flex-wrap justify-between items-center">
                    <p class="font-bold text-5xl">{{ Customsetting::get('site_name') }}</p>
                    <div class="flex flex-wrap gap-4">
                        <button x-cloak x-show="lastOrder"
                                x-bind:disabled="loading"
                                x-bind:class="loading ? 'bg-primary-900' : 'bg-primary-500 hover:bg-primary-700'"
                                @click="printLastOrder"
                                class="h-12 w-12 text-white transition-all duration-300 ease-in-out p-1 rounded-full flex items-center justify-center">
                            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24"
                                 stroke-width="1.5"
                                 stroke="currentColor" class="size-6">
                                <path stroke-linecap="round" stroke-linejoin="round"
                                      d="M19.5 12c0-1.232-.046-2.453-.138-3.662a4.006 4.006 0 0 0-3.7-3.7 48.678 48.678 0 0 0-7.324 0 4.006 4.006 0 0 0-3.7 3.7c-.017.22-.032.441-.046.662M19.5 12l3-3m-3 3-3-3m-12 3c0 1.232.046 2.453.138 3.662a4.006 4.006 0 0 0 3.7 3.7 48.656 48.656 0 0 0 7.324 0 4.006 4.006 0 0 0 3.7-3.7c.017-.22.032-.441.046-.662M4.5 12l3 3m-3-3-3 3"/>
                            </svg>
                        </button>
                        <button id="exitFullscreenBtn" @click="toggleFullscreen"
                                x-bind:disabled="loading"
                                x-show="isFullscreen"
                                x-cloak
                                x-bind:class="loading ? 'bg-primary-900' : 'bg-primary-500 hover:bg-primary-700'"
                                class="h-12 w-12 text-white transition-all duration-300 ease-in-out p-1 rounded-full flex items-center justify-center">
                            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24"
                                 stroke-width="1.5" stroke="currentColor" class="size-6">
                                <path stroke-linecap="round" stroke-linejoin="round"
                                      d="M9 9V4.5M9 9H4.5M9 9 3.75 3.75M9 15v4.5M9 15H4.5M9 15l-5.25 5.25M15 9h4.5M15 9V4.5M15 9l5.25-5.25M15 15h4.5M15 15v4.5m0-4.5 5.25 5.25"/>
                            </svg>
                        </button>
                        <button id="fullscreenBtn" @click="toggleFullscreen"
                                x-bind:disabled="loading"
                                x-show="!isFullscreen"
                                x-bind:class="loading ? 'bg-primary-900' : 'bg-primary-500 hover:bg-primary-700'"
                                class="h-12 w-12 text-white transition-all duration-300 ease-in-out p-1 rounded-full flex items-center justify-center">
                            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24"
                                 stroke-width="1.5" stroke="currentColor" class="size-6">
                                <path stroke-linecap="round" stroke-linejoin="round"
                                      d="M3.75 3.75v4.5m0-4.5h4.5m-4.5 0L9 9M3.75 20.25v-4.5m0 4.5h4.5m-4.5 0L9 15M20.25 3.75h-4.5m4.5 0v4.5m0-4.5L15 9m5.25 11.25h-4.5m4.5 0v-4.5m0 4.5L15 15"/>
                            </svg>
                        </button>
                        <button x-cloak x-show="products.length" @click="clearProducts"
                                x-bind:disabled="loading"
                                x-bind:class="loading ? 'bg-red-900' : 'bg-red-500 hover:bg-red-700'"
                                class="h-12 w-12  text-white transition-all duration-300 ease-in-out p-1 rounded-full flex items-center justify-center">
                            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24"
                                 stroke-width="1.5"
                                 stroke="currentColor" class="size-6">
                                <path stroke-linecap="round" stroke-linejoin="round"
                                      d="m14.74 9-.346 9m-4.788 0L9.26 9m9.968-3.21c.342.052.682.107 1.022.166m-1.022-.165L18.16 19.673a2.25 2.25 0 0 1-2.244 2.077H8.084a2.25 2.25 0 0 1-2.244-2.077L4.772 5.79m14.456 0a48.108 48.108 0 0 0-3.478-.397m-12 .562c.34-.059.68-.114 1.022-.165m0 0a48.11 48.11 0 0 1 3.478-.397m7.5 0v-.916c0-1.18-.91-2.164-2.09-2.201a51.964 51.964 0 0 0-3.32 0c-1.18.037-2.09 1.022-2.09 2.201v.916m7.5 0a48.667 48.667 0 0 0-7.5 0"/>
                            </svg>
                        </button>
                    </div>
                </div>
                <form @submit.prevent="selectProduct">
                    <div class="w-full relative">
                    <span class="text-black absolute left-2.5 top-2">
                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5"
                             stroke="currentColor" class="size-6">
                          <path stroke-linecap="round" stroke-linejoin="round"
                                d="m21 21-5.197-5.197m0 0A7.5 7.5 0 1 0 5.196 5.196a7.5 7.5 0 0 0 10.607 10.607Z"/>
                        </svg>
                    </span>
                        <input autofocus x-model="searchProductQuery"
                               id="search-product-query"
                               x-bind:class="loading ? 'bg-gray-200' : 'bg-white'"
                               :inputmode="!searchQueryInputmode ? 'text' : 'none'"
                               placeholder="Zoek een product op naam, SKU of barcode..."
                               class="text-black w-full border-2 border-primary-500 rounded-lg px-10 py-1 text-xl">
                        <p class="absolute right-2.5 top-2 text-black cursor-pointer"
                           @click="updateSearchQueryInputmode">
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
                    </div>
                </form>
                <div class="z-50 bg-primary-500 rounded-lg shadow-xl grow overflow-y-auto" x-cloak
                     x-show="!loadingSearchedProducts && searchProductQuery && searchedProducts.length">
                    <div class="grid grid-cols-4 overflow-y-auto bg-primary-500 gap-0.5">
                        <template x-for="product in searchedProducts">
                            <div class="grid relative items-center gap-6 p-4 text-black bg-white cursor-pointer"
                                 @click="addProduct(product.id)">
                                <div class="mx-auto"
                                     x-show="product.image">
                                    <img :src="product.image"
                                         class="object-cover aspect-3/2 rounded-lg max-h-[100px]">
                                </div>

                                <div class="text-wrap">
                                    <p>
                                        <span x-html="product.name"></span>
                                    </p>
                                    <p>
                                        <span class="font-bold" x-html="product.currentPrice"></span>
                                    </p>
                                    <p x-show="product.stock >= 3" class="text-green-500">
                                        <span class="font-bold" x-html="product.stock + ' op voorraad'"></span>
                                    </p>
                                    <p x-show="product.stock < 3 && product.stock > 0" class="text-orange-500">
                                        <span class="font-bold" x-html="product.stock + ' op voorraad'"></span>
                                    </p>
                                    <p x-show="product.stock < 1" class="text-red-500">
                                        <span class="font-bold" x-html="'uitverkocht'"></span>
                                    </p>
                                </div>
                            </div>
                        </template>
                    </div>
                </div>
                <div class="z-50 bg-white rounded-lg mt-2 shadow-xl" x-cloak
                     x-show="!loadingSearchedProducts && searchProductQuery && !searchedProducts.length">
                    <div class="p-4">
                        <p x-show="searchProductQuery.length >= 3" class="text-center text-black">Geen producten gevonden</p>
                        <p x-show="searchProductQuery.length < 3" class="text-center text-black">Vul een zoekterm in...</p>
                    </div>
                </div>
                <div class="z-50 bg-white rounded-lg mt-2 shadow-xl" x-cloak
                     x-show="loadingSearchedProducts">
                    <div class="p-4">
                        <p class="text-center text-black">Producten aan het laden</p>
                    </div>
                </div>
                <div class="grid gap-4 sm:grid-cols-2" x-cloak x-show="!searchProductQuery">
                    <button @click="toggle('customProductPopup')"
                            x-bind:class="loading ? 'bg-primary-900' : 'bg-primary-500 hover:bg-primary-700'"
                            x-bind:disabled="loading"
                            class="text-left rounded-lg transition-all duration-300 ease-in-out gap-8 flex flex-col justify-between p-4 font-medium text-xl">
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
                            x-bind:disabled="loading"
                            x-cloak x-show="activeDiscountCode"
                            x-bind:class="loading ? 'bg-red-900' : 'bg-red-500 hover:bg-red-700'"
                            class="text-left rounded-lg  transition-all duration-300 ease-in-out gap-8 flex flex-col justify-between p-4 font-medium text-xl">
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
                            x-bind:disabled="loading"
                            x-bind:class="loading ? 'bg-primary-900' : 'bg-primary-500 hover:bg-primary-700'"
                            x-cloak x-show="!activeDiscountCode"
                            class="text-left rounded-lg transition-all duration-300 ease-in-out gap-8 flex flex-col justify-between p-4 font-medium text-xl">
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
                        @click="toggle('customerDataPopup')"
                        x-bind:disabled="loading"
                        x-bind:class="loading ? 'bg-primary-900' : 'bg-primary-500 hover:bg-primary-700'"
                        class="text-left rounded-lg transition-all duration-300 ease-in-out gap-8 flex flex-col justify-between p-4 font-medium text-xl">
                        <span>
                            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5"
                                 stroke="currentColor"
                                 class="size-6">
                              <path stroke-linecap="round" stroke-linejoin="round"
                                    d="M15.75 6a3.75 3.75 0 1 1-7.5 0 3.75 3.75 0 0 1 7.5 0ZM4.501 20.118a7.5 7.5 0 0 1 14.998 0A17.933 17.933 0 0 1 12 21.75c-2.676 0-5.216-.584-7.499-1.632Z"/>
                            </svg>
                        </span>
                        <p>Klantgegevens toevoegen</p>
                    </button>
                    <button @click="toggle('chooseShippingMethodPopup')"
                            x-bind:disabled="loading"
                            x-bind:class="loading ? 'bg-primary-900' : 'bg-primary-500 hover:bg-primary-700'"
                            x-cloak x-show="!shippingMethodId"
                            class="text-left rounded-lg transition-all duration-300 ease-in-out gap-8 flex flex-col justify-between p-4 font-medium text-xl">
                        <span>
                            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5"
                                 stroke="currentColor" class="size-6">
                              <path stroke-linecap="round" stroke-linejoin="round"
                                    d="M8.25 18.75a1.5 1.5 0 0 1-3 0m3 0a1.5 1.5 0 0 0-3 0m3 0h6m-9 0H3.375a1.125 1.125 0 0 1-1.125-1.125V14.25m17.25 4.5a1.5 1.5 0 0 1-3 0m3 0a1.5 1.5 0 0 0-3 0m3 0h1.125c.621 0 1.129-.504 1.09-1.124a17.902 17.902 0 0 0-3.213-9.193 2.056 2.056 0 0 0-1.58-.86H14.25M16.5 18.75h-2.25m0-11.177v-.958c0-.568-.422-1.048-.987-1.106a48.554 48.554 0 0 0-10.026 0 1.106 1.106 0 0 0-.987 1.106v7.635m12-6.677v6.677m0 4.5v-4.5m0 0h-12"/>
                            </svg>
                        </span>
                        <p>Verzendmethode toepassen</p>
                    </button>
                    <button @click="removeShippingMethod"
                            x-bind:disabled="loading"
                            x-cloak x-show="shippingMethodId"
                            x-bind:class="loading ? 'bg-red-900' : 'bg-red-500 hover:bg-red-700'"
                            class="text-left rounded-lg  transition-all duration-300 ease-in-out gap-8 flex flex-col justify-between p-4 font-medium text-xl">
                                            <span>
                   <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5"
                        stroke="currentColor" class="size-6">
  <path stroke-linecap="round" stroke-linejoin="round"
        d="M8.25 18.75a1.5 1.5 0 0 1-3 0m3 0a1.5 1.5 0 0 0-3 0m3 0h6m-9 0H3.375a1.125 1.125 0 0 1-1.125-1.125V14.25m17.25 4.5a1.5 1.5 0 0 1-3 0m3 0a1.5 1.5 0 0 0-3 0m3 0h1.125c.621 0 1.129-.504 1.09-1.124a17.902 17.902 0 0 0-3.213-9.193 2.056 2.056 0 0 0-1.58-.86H14.25M16.5 18.75h-2.25m0-11.177v-.958c0-.568-.422-1.048-.987-1.106a48.554 48.554 0 0 0-10.026 0 1.106 1.106 0 0 0-.987 1.106v7.635m12-6.677v6.677m0 4.5v-4.5m0 0h-12"/>
</svg>

                                            </span>
                        <p>Verzendmethode verwijderen</p>
                    </button>
                    <button @click="openCashRegister"
                            x-bind:disabled="loading"
                            x-cloak x-show="hasCashRegister"
                            x-bind:class="loading ? 'bg-primary-900' : 'bg-primary-500 hover:bg-primary-700'"
                            class="text-left rounded-lg transition-all duration-300 ease-in-out gap-8 flex flex-col justify-between p-4 font-medium text-xl">
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
                    <button @click="showOrdersPopup()"
                            x-bind:disabled="loading"
                            x-bind:class="loading ? 'bg-primary-900' : 'bg-primary-500 hover:bg-primary-700'"
                            class="focus-search-order text-left rounded-lg transition-all duration-300 ease-in-out gap-8 flex flex-col justify-between p-4 font-medium text-xl">
                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5"
                             stroke="currentColor" class="size-6">
                            <path stroke-linecap="round" stroke-linejoin="round"
                                  d="m21 21-5.197-5.197m0 0A7.5 7.5 0 1 0 5.196 5.196a7.5 7.5 0 0 0 10.607 10.607Z"/>
                        </svg>

                        <p>Zoek bestelling</p>
                    </button>
                    <button @click="refreshProducts()"
                            x-bind:disabled="loading"
                            x-bind:class="loading ? 'bg-primary-900' : 'bg-primary-500 hover:bg-primary-700'"
                            class="focus-search-order text-left rounded-lg transition-all duration-300 ease-in-out gap-8 flex flex-col justify-between p-4 font-medium text-xl">
                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5"
                             stroke="currentColor" class="size-6">
                            <path stroke-linecap="round" stroke-linejoin="round"
                                  d="M16.023 9.348h4.992v-.001M2.985 19.644v-4.992m0 0h4.992m-4.993 0 3.181 3.183a8.25 8.25 0 0 0 13.803-3.7M4.031 9.865a8.25 8.25 0 0 1 13.803-3.7l3.181 3.182m0-4.991v4.99"/>
                        </svg>

                        <p>Producten opnieuw ophalen</p>
                    </button>
                </div>
            </div>
            <div class="sm:col-span-3 sm:pl-8 flex flex-col gap-8 overflow-y-auto">
                <div x-show="products.length > 0"
                     class="flex flex-col grow p-4 rounded-lg border border-primary-500 overflow-y-auto gap-4">
                    <template x-for="product in products">
                        <div class="flex flex-wrap items-center gap-4">
                            <div class="relative">
                                <img :src="product.image" x-cloak x-show="product.image"
                                     class="object-cover rounded-lg w-20 h-20">
                                <img x-cloak x-show="!product.image && product.customProduct === true"
                                     src="https://placehold.co/400x400/{{ str(collect(collect(filament()->getPanels())->first()->getColors())->first())->replace('#', '') }}/fff?text=Aangepaste%20verkoop"
                                     class="object-cover rounded-lg w-20 h-20">
                                <img x-cloak x-show="!product.image && product.customProduct !== true"
                                     :src="'https://placehold.co/400x400/{{ str(collect(collect(filament()->getPanels())->first()->getColors())->first())->replace('#', '') }}/fff?text=' + product.name"
                                     class="object-cover rounded-lg w-20 h-20">
                                <span
                                    class="bg-primary-500 text-white font-bold rounded-full w-6 h-6 absolute -right-2 -top-2 flex items-center justify-center border-2 border-white"
                                    x-html="product.quantity">
                                        </span>
                            </div>
                            <div class="flex flex-col flex-wrap gap-1 flex-1">
                                <span class="word-wrap text-sm" x-html="product.name"></span>
                                <span class="font-bold text-md word-wrap" x-html="product.priceFormatted"></span>
                                <div class="flex flex-wrap gap-2">
                                    <button
                                        @click="changeQuantity(product.identifier, product.quantity + 1)"
                                        x-bind:disabled="loading"
                                        x-bind:class="loading ? 'bg-primary-900' : 'bg-primary-500 hover:bg-primary-700'"
                                        class="h-10 w-10 text-white transition-all duration-300 ease-in-out p-1 rounded-full flex items-center justify-center">
                                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24"
                                             stroke-width="1.5" stroke="currentColor" class="size-6">
                                            <path stroke-linecap="round" stroke-linejoin="round"
                                                  d="M12 4.5v15m7.5-7.5h-15"/>
                                        </svg>
                                    </button>
                                    <button
                                        @click="changeQuantity(product.identifier, product.quantity - 1)"
                                        x-bind:disabled="loading"
                                        x-bind:class="loading ? 'bg-primary-900' : 'bg-primary-500 hover:bg-primary-700'"
                                        class="h-10 w-10 text-white transition-all duration-300 ease-in-out p-1 rounded-full flex items-center justify-center">
                                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24"
                                             stroke-width="1.5" stroke="currentColor" class="size-6">
                                            <path stroke-linecap="round" stroke-linejoin="round" d="M5 12h14"/>
                                        </svg>
                                    </button>
                                    <button
                                        @click="openChangeProductPricePopup(product)"
                                        x-bind:disabled="loading"
                                        x-bind:class="loading ? 'bg-orange-900' : 'bg-orange-500 hover:bg-orange-700'"
                                        class="ml-auto h-10 w-10 text-white transition-all duration-300 ease-in-out p-1 rounded-full flex items-center justify-center">
                                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24"
                                             stroke-width="1.5" stroke="currentColor" class="size-6">
                                            <path stroke-linecap="round" stroke-linejoin="round"
                                                  d="m16.862 4.487 1.687-1.688a1.875 1.875 0 1 1 2.652 2.652L10.582 16.07a4.5 4.5 0 0 1-1.897 1.13L6 18l.8-2.685a4.5 4.5 0 0 1 1.13-1.897l8.932-8.931Zm0 0L19.5 7.125M18 14v4.75A2.25 2.25 0 0 1 15.75 21H5.25A2.25 2.25 0 0 1 3 18.75V8.25A2.25 2.25 0 0 1 5.25 6H10"/>
                                        </svg>
                                    </button>
                                    <button
                                        @click="changeQuantity(product.identifier, 0)"
                                        x-bind:disabled="loading"
                                        x-bind:class="loading ? 'bg-red-900' : 'bg-red-500 hover:bg-red-700'"
                                        class="h-10 w-10  text-white transition-all duration-300 ease-in-out p-1 rounded-full flex items-center justify-center">
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
                    <p x-show="products.length === 0">Geen producten geselecteerd...</p>
                </div>
                <div class="mt-auto gap-4 grid">
                    <div class="grid gap-2 p-4 rounded-lg border border-primary-500">
                        <div class="text-xl font-bold grid gap-2">
                            <div class="flex items-center justify-between">
                                <div class="flex flex-col">
                                    <span>Subtotaal</span>
                                    <span class="text-sm font-normal"
                                          x-html="totalQuantity() + ' artikelen'">0 artikelen</span>
                                </div>
                                <span class="font-bold" x-html="subTotal"></span>
                            </div>
                            <hr/>
                            <div x-show="activeDiscountCode" x-cloak>
                                <div class="text-sm font-bold flex justify-between items-center mb-2">
                                    <span>Korting</span>
                                    <span class="font-bold" x-html="discount"></span>
                                </div>
                                <hr>
                            </div>
                            <div x-show="shippingMethodId" x-cloak>
                                <div class="text-sm font-bold flex justify-between items-center mb-2">
                                    <span>Verzendkosten</span>
                                    <span class="font-bold" x-html="shippingMethodCosts"></span>
                                </div>
                                <hr>
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
                            <div x-show="vatPercentages.length == 0"
                                 class="text-sm font-bold flex justify-between items-center">
                                <span>BTW</span>
                                <span class="font-bold">0</span>
                            </div>
                        </div>
                    </div>
                    <button @click="toggle('checkoutPopup')"
                            x-bind:disabled="loading || products.length === 0"
                            x-bind:class="loading ? 'bg-primary-900' : 'bg-primary-500 hover:bg-primary-700'"
                            class="px-4 py-2 text-lg uppercase rounded-lg transition-all ease-in-out duration-300 text-white font-bold w-full">
                        Betaal <span x-html="total">€0,-</span>
                    </button>
                </div>
            </div>
        </div>
    </div>
    <div
        x-show="customProductPopup"
        x-cloak
        x-transition.opacity.scale.origin
        class="fixed inset-0 bg-black/50 flex items-center justify-center z-50 text-black">
        <div class="absolute h-full w-full" @click="toggle('customProductPopup');"></div>
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
        x-show="changeProductPricePopup"
        x-cloak
        x-transition.opacity.scale.origin
        class="fixed inset-0 bg-black/50 flex items-center justify-center z-50 text-black">
        <div class="absolute h-full w-full" @click="toggle('changeProductPricePopup')"></div>
        <div class="bg-white rounded-lg p-8 grid gap-4 relative">
            <div class="absolute top-2 right-2 text-black cursor-pointer"
                 @click="toggle('changeProductPricePopup')">
                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5"
                     stroke="currentColor" class="size-10">
                    <path stroke-linecap="round" stroke-linejoin="round"
                          d="m9.75 9.75 4.5 4.5m0-4.5-4.5 4.5M21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z"/>
                </svg>
            </div>
            <p class="text-3xl font-bold">Product prijs aanpassen</p>
            <form wire:submit.prevent="submitChangeProductForm">
                <div class="grid gap-4">
                    {{ $this->changeProductForm }}
                    <div>
                        <button type="submit"
                                class="px-4 py-2 text-lg uppercase rounded-lg bg-primary-500 hover:bg-primary-700 transition-all ease-in-out duration-300 text-white font-bold w-full">
                            Aanpassen
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
        class="fixed inset-0 bg-black/50 flex items-center justify-center z-50 text-black">
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
    <div
        x-show="chooseShippingMethodPopup"
        x-cloak
        x-transition.opacity.scale.origin
        class="fixed inset-0 bg-black/50 flex items-center justify-center z-50 text-black">
        <div class="absolute h-full w-full" @click="toggle('chooseShippingMethodPopup')"></div>
        <div class="bg-white rounded-lg p-8 grid gap-4 relative">
            <div class="bg-white rounded-lg p-8 grid gap-4">
                <div class="absolute top-2 right-2 text-black cursor-pointer"
                     @click="toggle('chooseShippingMethodPopup')">
                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5"
                         stroke="currentColor" class="size-10">
                        <path stroke-linecap="round" stroke-linejoin="round"
                              d="m9.75 9.75 4.5 4.5m0-4.5-4.5 4.5M21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z"/>
                    </svg>
                </div>
                <p class="text-3xl font-bold">Selecteer een verzendmethode</p>
                <div class="grid gap-8 grid-cols-1 md:grid-cols-2" x-show="shippingMethods.length">
                    <template x-for="shippingMethod in shippingMethods">
                        <button @click="selectShippingMethod(shippingMethod.id)"
                                x-bind:disabled="loading"
                                x-bind:class="loading ? 'bg-primary-900' : 'bg-primary-500 hover:bg-primary-700'"
                                class="p-4 text-2xl uppercase rounded-lg transition-all ease-in-out duration-300 text-white font-bold w-full flex items-center flex-wrap justify-between">
                            <span x-html="shippingMethod.fullName"></span>
                        </button>
                    </template>
                </div>
                <div class="p-4" x-show="!shippingMethods.length">
                    <p class="text-center text-black">Geen verzendmethodes gevonden</p>
                </div>
            </div>
        </div>
    </div>
    <div
        x-show="checkoutPopup"
        x-cloak
        x-transition.opacity.scale.origin
        class="fixed inset-0 bg-black/50 flex items-center justify-center z-50 text-black">
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
                                x-bind:class="loading ? 'bg-primary-900' : 'bg-primary-500 hover:bg-primary-700'"
                                x-bind:disabled="loading"
                                class="p-4 text-2xl uppercase rounded-lg transition-all ease-in-out duration-300 text-white font-bold w-full flex items-center flex-wrap justify-between">
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
        class="fixed inset-0 bg-black/50 flex items-center justify-center z-50 text-black">
        <div class="absolute h-full w-full" @click="closePayment"></div>
        <div class="bg-white rounded-lg p-8 grid gap-4 relative sm:min-w-[800px]">
            <div class="bg-white rounded-lg p-8 grid gap-4">
                <div class="absolute top-2 right-2 text-black cursor-pointer"
                     @click="closePayment">
                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5"
                         stroke="currentColor" class="size-10">
                        <path stroke-linecap="round" stroke-linejoin="round"
                              d="m9.75 9.75 4.5 4.5m0-4.5-4.5 4.5M21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z"/>
                    </svg>
                </div>
                <template x-if="chosenPaymentMethod">
                    <div>
                        <p class="text-3xl font-bold" x-html="'Totaal: ' + total"></p>
                        <p class="text-xl text-gray-400" x-html="'Betaalmethode: ' + chosenPaymentMethod.name"></p>
                    </div>
                </template>
                <div class="flex flex-col gap-4"
                     x-show="!isPinTerminalPayment && chosenPaymentMethod && chosenPaymentMethod.isCashPayment">
                    <div class="flex flex-col gap-2">
                        <p class="text-xl font-bold">Ontvangen bedrag</p>
                        <div class="grid md:grid-cols-2 gap-4">
                            <template x-for="suggestedCashPaymentAmount in suggestedCashPaymentAmounts">
                                <button @click="setCashPaymentAmount(suggestedCashPaymentAmount.amount)"
                                        x-bind:class="loading ? 'bg-primary-900' : 'bg-primary-500 hover:bg-primary-700'"
                                        x-bind:disabled="loading"
                                        class="p-4 text-2xl uppercase rounded-lg transition-all ease-in-out duration-300 text-white font-bold w-full"
                                        x-html="suggestedCashPaymentAmount.formattedAmount">

                                </button>
                            </template>
                        </div>
                    </div>
                    <form @submit.prevent="markAsPaid" x-show="!isPinTerminalPayment">
                        <div>
                            <label for="cashPaymentAmount"
                                   class="text-xl font-bold">Anders</label>
                            <div class="mt-2">
                                <div
                                    class="flex items-center rounded-md bg-white px-3 outline outline-1 -outline-offset-1 outline-gray-300 focus-within:outline focus-within:outline-2 focus-within:-outline-offset-2 focus-within:outline-primary-600">
                                    <div class="shrink-0 select-none text-base text-gray-500 sm:text-lg pr-3">€</div>
                                    <input x-model="cashPaymentAmount"
                                           type="number"
                                           min="0"
                                           max="100000"
                                           required
                                           placeholder="Anders..."
                                           x-bind:class="loading ? 'bg-gray-300' : 'bg-white'"
                                           x-bind:disabled="loading"
                                           class="block min-w-0 grow py-3 pl-1 pr-3 text-base text-gray-900 placeholder:text-gray-400 focus:outline focus:outline-0 sm:text-lg">
                                    <div id="price-currency"
                                         class="shrink-0 select-none text-base text-gray-500 sm:text-lg pl-3">EUR
                                    </div>
                                </div>
                            </div>
                            {{--                            <button class="mt-4 p-4 text-xl uppercase rounded-lg bg-primary-500 hover:bg-primary-700 transition-all ease-in-out duration-300 text-white font-bold w-full">--}}
                            {{--                                Betaling verwerken--}}
                            {{--                            </button>--}}
                        </div>
                    </form>
                </div>

                <template x-if="isPinTerminalPayment && pinTerminalStatus == 'pending'">
                    <div class="grid gap-2 flex items-center justify-between">
                        <p class="text-3xl">{{ Translation::get('pin-transaction-started', 'point-of-sale', 'De klant mag nu pinnen.') }}</p>
                        <p class="text-xl text-gray-400" x-show="order && order.paidAmount > 0">
                            Al betaald: <span x-html="order.paidAmountFormatted"></span>
                        </p>
                    </div>
                </template>
                <div x-show="isPinTerminalPayment && pinTerminalStatus == 'waiting_for_clearance'"
                     class="grid gap-2 flex items-center justify-between">
                    <p class="text-3xl">{{ Translation::get('pin-terminal-in-use', 'point-of-sale', 'De pin terminal is in gebruik, wacht tot deze vrijgegeven is.') }}</p>
                    <button @click="startPinTerminalPayment"
                            x-bind:class="loading ? 'bg-primary-900' : 'bg-primary-500 hover:bg-primary-700'"
                            x-bind:disabled="loading"
                            class="w-full px-4 py-4 text-lg uppercase rounded-lg transition-all ease-in-out duration-300 text-white font-bold w-full flex items-center justify-center gap-1">
                        <span>Start betaling opnieuw</span>
                    </button>
                </div>
                <div x-show="isPinTerminalPayment && pinTerminalStatus == 'timed_out'"
                     class="grid gap-2 flex items-center justify-between">
                    <p class="text-3xl">{{ Translation::get('pin-terminal-payment-timed-out', 'point-of-sale', 'De betaling is niet optijd voltooid, probeer het opnieuw.') }}</p>
                    <button @click="startPinTerminalPayment"
                            x-bind:class="loading ? 'bg-primary-900' : 'bg-primary-500 hover:bg-primary-700'"
                            x-bind:disabled="loading"
                            class="w-full px-4 py-4 text-lg uppercase rounded-lg transition-all ease-in-out duration-300 text-white font-bold w-full flex items-center justify-center gap-1">
                        <span>Start betaling opnieuw</span>
                    </button>
                </div>
                <div x-show="isPinTerminalPayment && pinTerminalStatus == 'cancelled_by_customer'"
                     class="flex-col gap-2 flex items-between">
                    <p class="text-3xl">{{ Translation::get('pin-terminal-payment-cancelled-by-customer', 'point-of-sale', 'De betaling is geannuleerd door de klant.') }}</p>
                    <button @click="startPinTerminalPayment"
                            x-bind:class="loading ? 'bg-primary-900' : 'bg-primary-500 hover:bg-primary-700'"
                            x-bind:disabled="loading"
                            class="w-full px-4 py-4 text-lg uppercase rounded-lg transition-all ease-in-out duration-300 text-white font-bold w-full flex items-center justify-center gap-1">
                        <span>Start betaling opnieuw</span>
                    </button>
                </div>
                <div
                    x-show="isPinTerminalPayment && pinTerminalError && !['pending', 'waiting_for_clearance', 'timed_out', 'cancelled_by_customer'].includes(pinTerminalStatus)"
                    class="grid gap-2">
                    <p class="text-3xl" x-html="pinTerminalError"></p>
                    <button @click="startPinTerminalPayment"
                            x-bind:class="loading ? 'bg-primary-900' : 'bg-primary-500 hover:bg-primary-700'"
                            x-bind:disabled="loading"
                            class="w-full px-4 py-4 text-lg uppercase rounded-lg transition-all ease-in-out duration-300 text-white font-bold w-full flex items-center justify-center gap-1">
                        <span>Probeer betaling opnieuw</span>
                    </button>
                </div>
                <div class="grid md:grid-cols-2 gap-4">
                    <template x-if="isPinTerminalPayment && pinTerminalStatus == 'pending'">
                        <button disabled
                                class="md:col-span-2 px-4 py-4 text-lg uppercase rounded-lg bg-primary-900 transition-all ease-in-out duration-300 text-white font-bold w-full flex items-center justify-center gap-1">
                            <svg class="animate-spin -ml-1 mr-3 h-5 w-5 text-white"
                                 xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor"
                                        stroke-width="4"></circle>
                                <path class="opacity-75" fill="currentColor"
                                      d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                            </svg>
                            <span>Wachten op betaling...</span>
                        </button>
                    </template>
                    @if(!Customsetting::get('pos_auto_print_receipt', null, true))
                        <button @click="printReceipt()" x-show="postPay"
                                x-bind:class="loading ? 'bg-primary-900' : 'bg-primary-500 hover:bg-primary-700'"
                                x-bind:disabled="loading"
                                class="px-4 py-4 text-lg uppercase rounded-lg transition-all ease-in-out duration-300 text-white font-bold w-full text-center">
                            Bon printen
                        </button>
                    @endif
                    <button @click="resetPOS()" x-show="postPay"
                            x-bind:class="loading ? 'bg-primary-900' : 'bg-primary-500 hover:bg-primary-700'"
                            x-bind:disabled="loading"
                            class="px-4 py-4 text-lg uppercase rounded-lg transition-all ease-in-out duration-300 text-white font-bold w-full text-center">
                        Terug naar POS
                    </button>
                    <a x-bind:href="orderUrl" x-show="postPay"
                       target="_blank"
                       x-bind:class="loading ? 'bg-primary-900' : 'bg-primary-500 hover:bg-primary-700'"
                       x-bind:disabled="loading"
                       class="px-4 py-4 text-lg uppercase rounded-lg transition-all ease-in-out duration-300 text-white font-bold w-full text-center">
                        Bestelling bekijken
                    </a>
                    <button @click="closePayment" x-show="!isPinTerminalPayment && !postPay"
                            x-bind:class="loading ? 'bg-red-900' : 'bg-red-500 hover:bg-red-700'"
                            x-bind:disabled="loading"
                            class="px-4 py-4 text-lg uppercase rounded-lg transition-all ease-in-out duration-300 text-white font-bold w-full">
                        Annuleren
                    </button>
                    <button disabled x-show="!cashPaymentAmount && !isPinTerminalPayment && !postPay"
                            x-bind:class="loading ? 'bg-primary-900' : 'bg-primary-700'"
                            x-bind:disabled="loading"
                            class="px-4 py-4 text-lg uppercase rounded-lg bg-primary-700 transition-all ease-in-out duration-300 text-white font-bold w-full">
                        Vul een bedrag in
                    </button>
                    <button @click="createPaymentWithExtraPayment"
                            x-bind:class="loading ? 'bg-primary-900' : 'bg-primary-500 hover:bg-primary-700'"
                            x-bind:disabled="loading"
                            x-show="!isPinTerminalPayment && cashPaymentAmount && Math.floor(cashPaymentAmount) < Math.floor(totalUnformatted) && !postPay"
                            class="px-4 py-4 text-lg uppercase rounded-lg transition-all ease-in-out duration-300 text-white font-bold w-full">
                        Restbedrag bijpinnen
                    </button>
                    <button @click="markAsPaid"
                            x-bind:class="loading ? 'bg-primary-900' : 'bg-primary-500 hover:bg-primary-700'"
                            x-bind:disabled="loading"
                            x-show="!isPinTerminalPayment && Math.floor(cashPaymentAmount) >= Math.floor(totalUnformatted) && !postPay"
                            class="px-4 py-4 text-lg uppercase rounded-lg transition-all ease-in-out duration-300 text-white font-bold w-full">
                        Markeer als betaald
                    </button>
                </div>
            </div>
        </div>
    </div>
    <div
        x-show="orderConfirmationPopup"
        x-cloak
        x-transition.opacity.scale.origin
        class="fixed inset-0 bg-black/50 flex items-center justify-center z-50 text-black">
        <div class="absolute h-full w-full" @click="resetPOS()"></div>
        <template x-if="order && firstPaymentMethod">
            <div class="bg-white rounded-lg p-8 grid gap-4 relative sm:min-w-[800px]">
                <div class="bg-white rounded-lg p-8 grid gap-4">
                    <div class="absolute top-2 right-2 text-black cursor-pointer"
                         @click="resetPOS()">
                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5"
                             stroke="currentColor" class="size-10">
                            <path stroke-linecap="round" stroke-linejoin="round"
                                  d="m9.75 9.75 4.5 4.5m0-4.5-4.5 4.5M21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z"/>
                        </svg>
                    </div>
                    <div>
                        <p class="text-3xl font-bold">
                            Bestelling <span x-html="order.invoice_id"></span> afgerond - <span
                                x-html="order.totalFormatted"></span>
                        </p>
                        <p class="text-xl text-gray-400">
                            Betaalmethode: <span x-html="firstPaymentMethod.name"></span>
                        </p>
                    </div>
                    <div class="flex flex-col gap-4" x-show="firstPaymentMethod.is_cash_payment">
                        <p class="text-xl font-bold">Betaling overzicht</p>
                        <template x-for="(orderPayment, index) in orderPayments">
                            <div x-show="index == 0 || (index != 0 && !order.shouldChangeMoney)"
                                 class="flex flex-wrap items-center justify-between border border-primary-500 rounded-lg p-4 gap-4">
                                <div class="flex flex-col">
                                    <p class="font-bold text-lg">Betaling <span x-html="index + 1"></span></p>
                                    <p class="text-gray-400" x-html="orderPayment.paymentMethodName"></p>
                                </div>
                                <div class="flex flex-col">
                                    <p class="font-bold text-xl" x-html="orderPayment.amountFormatted"></p>
                                    <p class="text-warning-500 font-bold text-xl"
                                       x-show="index == 0 && order.shouldChangeMoney">
                                        Wisselgeld verschuldigd: <span x-html="order.changeMoney"></span>
                                    </p>
                                </div>
                            </div>
                        </template>
                    </div>
                    <div class="grid gap-4 mt-16">
                        @if(!Customsetting::get('pos_auto_print_receipt', null, true))
                            <button @click="printReceipt()"
                                    x-bind:class="loading ? 'bg-primary-900' : 'bg-primary-500 hover:bg-primary-700'"
                                    x-bind:disabled="loading"
                                    class="px-4 py-4 text-lg uppercase rounded-lg transition-all ease-in-out duration-300 text-white font-bold w-full">
                                Bon printen
                            </button>
                        @endif
                        <button @click="resetPOS()"
                                x-bind:class="loading ? 'bg-primary-900' : 'bg-primary-500 hover:bg-primary-700'"
                                x-bind:disabled="loading"
                                class="px-4 py-4 text-lg uppercase rounded-lg transition-all ease-in-out duration-300 text-white font-bold w-full">
                            Terug naar POS
                        </button>
                    </div>
                </div>
            </div>
        </template>
    </div>
    <div
        x-show="customerDataPopup"
        x-cloak
        x-transition.opacity.scale.origin
        class="fixed inset-0 bg-black/50 flex items-center justify-center z-50 text-black">
        <div class="absolute h-full w-full" @click="toggle('customerDataPopup')"></div>
        <div class="bg-white rounded-lg p-8 grid gap-4 relative sm:min-w-[800px] h-[95%] overflow-y-auto">
            <div class="bg-white rounded-lg p-8 grid gap-4">
                <div class="absolute top-2 right-2 text-black cursor-pointer"
                     @click="toggle('customerDataPopup')">
                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5"
                         stroke="currentColor" class="size-10">
                        <path stroke-linecap="round" stroke-linejoin="round"
                              d="m9.75 9.75 4.5 4.5m0-4.5-4.5 4.5M21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z"/>
                    </svg>
                </div>
                <div>
                    <p class="text-3xl font-bold">
                        Voeg klantgegevens toe aan de bestelling
                    </p>
                </div>

                <form wire:submit.prevent="submitCustomerDataForm">
                    <div class="grid gap-4">
                        {{ $this->customerDataForm }}
                        <div>
                            <button type="submit"
                                    class="px-4 py-2 text-lg uppercase rounded-lg bg-primary-500 hover:bg-primary-700 transition-all ease-in-out duration-300 text-white font-bold w-full">
                                Klantgegevens opslaan
                            </button>
                        </div>
                    </div>
                </form>
            </div>
        </div>
    </div>
    <div class="fixed inset-0 flex items-center justify-center z-50 text-black" x-cloak x-show="ordersPopup"
         x-transition.opacity.scale.origin>
        <div class="absolute h-full w-full" @click="showOrdersPopup"></div>
        <div class="bg-primary-500 h-[95%] w-[95%] rounded-lg grid gap-0.5 relative grid-cols-8">
            <div class="absolute top-5 right-5 text-black cursor-pointer"
                 @click="showOrdersPopup">
                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5"
                     stroke="currentColor" class="size-10">
                    <path stroke-linecap="round" stroke-linejoin="round"
                          d="m9.75 9.75 4.5 4.5m0-4.5-4.5 4.5M21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z"/>
                </svg>
            </div>
            <div class="col-span-2 bg-gray-100 rounded-tl-lg flex flex-col p-4 overflow-y-auto">
                <div class="grid gap-4 overflow-y-auto">
                    <form @submit.prevent="retrieveOrders">
                        <div class="w-full relative">
                    <span class="text-black absolute left-2.5 top-2">
                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5"
                             stroke="currentColor" class="size-6">
                          <path stroke-linecap="round" stroke-linejoin="round"
                                d="m21 21-5.197-5.197m0 0A7.5 7.5 0 1 0 5.196 5.196a7.5 7.5 0 0 0 10.607 10.607Z"/>
                        </svg>
                    </span>
                            <input autofocus x-model.debounce.500ms="searchOrderQuery"
                                   id="search-order-query"
                                   :inputmode="!searchQueryInputmode ? 'text' : 'none'"
                                   placeholder="Zoek bestelling..."
                                   class="text-black w-full rounded-lg pl-10 pr-10 text-xl py-1 border-2 border-primary-600">
                            <p class="absolute right-2.5 top-2 text-black cursor-pointer"
                               @click="updateSearchQueryInputmode">
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
                        </div>
                    </form>
                    <div>
                        <p class="text-2xl font-bold">Bestellingen</p>
                    </div>
                    <div class="grid gap-4 overflow-y-auto">
                        <template x-for="orderDate in orders">
                            <div class="grid gap-2">
                                <p class="text-gray-600 uppercase text-xs" x-html="orderDate.date"></p>
                                <template x-for="order in orderDate.orders">
                                    <div @click="selectOrder(order)"
                                         class="rounded-lg flex gap-4 px-4 py-2 cursor-pointer group"
                                         :class="order.id === selectedOrder.id ? 'text-white bg-primary-500' : 'bg-white hover:bg-primary-500 text-black hover:text-white'">
                                        <div class="flex items-center justify-center">
                                            <svg x-show="order.orderOrigin == 'own'" xmlns="http://www.w3.org/2000/svg"
                                                 fill="none" viewBox="0 0 24 24" stroke-width="1.5"
                                                 stroke="currentColor" class="size-6">
                                                <path stroke-linecap="round" stroke-linejoin="round"
                                                      d="M12 21a9.004 9.004 0 0 0 8.716-6.747M12 21a9.004 9.004 0 0 1-8.716-6.747M12 21c2.485 0 4.5-4.03 4.5-9S14.485 3 12 3m0 18c-2.485 0-4.5-4.03-4.5-9S9.515 3 12 3m0 0a8.997 8.997 0 0 1 7.843 4.582M12 3a8.997 8.997 0 0 0-7.843 4.582m15.686 0A11.953 11.953 0 0 1 12 10.5c-2.998 0-5.74-1.1-7.843-2.918m15.686 0A8.959 8.959 0 0 1 21 12c0 .778-.099 1.533-.284 2.253m0 0A17.919 17.919 0 0 1 12 16.5c-3.162 0-6.133-.815-8.716-2.247m0 0A9.015 9.015 0 0 1 3 12c0-1.605.42-3.113 1.157-4.418"/>
                                            </svg>

                                            <svg x-show="order.orderOrigin == 'pos'" xmlns="http://www.w3.org/2000/svg"
                                                 fill="none" viewBox="0 0 24 24" stroke-width="1.5"
                                                 stroke="currentColor" class="size-6">
                                                <path stroke-linecap="round" stroke-linejoin="round"
                                                      d="M13.5 21v-7.5a.75.75 0 0 1 .75-.75h3a.75.75 0 0 1 .75.75V21m-4.5 0H2.36m11.14 0H18m0 0h3.64m-1.39 0V9.349M3.75 21V9.349m0 0a3.001 3.001 0 0 0 3.75-.615A2.993 2.993 0 0 0 9.75 9.75c.896 0 1.7-.393 2.25-1.016a2.993 2.993 0 0 0 2.25 1.016c.896 0 1.7-.393 2.25-1.015a3.001 3.001 0 0 0 3.75.614m-16.5 0a3.004 3.004 0 0 1-.621-4.72l1.189-1.19A1.5 1.5 0 0 1 5.378 3h13.243a1.5 1.5 0 0 1 1.06.44l1.19 1.189a3 3 0 0 1-.621 4.72M6.75 18h3.75a.75.75 0 0 0 .75-.75V13.5a.75.75 0 0 0-.75-.75H6.75a.75.75 0 0 0-.75.75v3.75c0 .414.336.75.75.75Z"/>
                                            </svg>
                                        </div>
                                        <div>
                                            <p class="text-md font-medium" x-html="order.invoiceId"></p>
                                            <p class="text-sm group-hover:text-white"
                                               :class="order.id === selectedOrder.id ? 'text-white' : 'text-gray-400'"
                                               x-html="order.time"></p>
                                        </div>
                                        <div class="ml-auto flex items-center gap-4">
                                            <p class="text-md" x-html="order.totalFormatted"></p>
                                            <p class="p-1 border-8 border-green-200 bg-green-500 rounded-full"
                                               x-show="order.status == 'paid'"></p>
                                            <p class="p-1 border-8 border-green-200 bg-green-500 rounded-full"
                                               x-show="order.status == 'partially_paid'"></p>
                                            <p class="p-1 border-8 border-green-200 bg-green-500 rounded-full"
                                               x-show="order.status == 'waiting_for_confirmation'"></p>
                                            <p class="p-1 border-8 border-blue-200 bg-blue-500 rounded-full"
                                               x-show="order.status == 'pending'"></p>
                                            <p class="p-1 border-8 border-red-200 bg-red-500 rounded-full"
                                               x-show="order.status == 'cancelled'"></p>
                                            <p class="p-1 border-8 border-orange-200 bg-orange-500 rounded-full"
                                               x-show="order.status == 'return'"></p>
                                        </div>
                                    </div>
                                </template>
                            </div>
                        </template>
                    </div>
                </div>
            </div>
            <template x-if="selectedOrder">
                <div class="col-span-6 p-4 overflow-y-auto">
                    <div
                        x-show="cancelOrderPopup"
                        x-cloak
                        x-transition.opacity.scale.origin
                        class="fixed inset-0 bg-black/50 flex items-center justify-center z-50 text-black p-8 overflow-y-auto">
                        <div class="absolute h-full w-full" @click="toggle('cancelOrderPopup')"></div>
                        <div class="bg-gray-100 rounded-lg p-8 grid gap-4 relative overflow-y-auto max-h-full">
                            <div class="absolute top-2 right-2 text-black cursor-pointer"
                                 @click="toggle('cancelOrderPopup')">
                                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24"
                                     stroke-width="1.5"
                                     stroke="currentColor" class="size-10">
                                    <path stroke-linecap="round" stroke-linejoin="round"
                                          d="m9.75 9.75 4.5 4.5m0-4.5-4.5 4.5M21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z"/>
                                </svg>
                            </div>
                            <p class="text-3xl font-bold">Bestelling retourneren</p>
                            <form @submit.prevent="submitCancelOrderForm">
                                <div class="grid gap-4 max-w-2xl overflow-y-auto">
                                    <div class="grid gap-2">
                                        <p class="text-gray-500 uppercase text-sm">Producten</p>
                                        <div class="grid gap-4">
                                            <template x-for="product in selectedOrder.cancelData.orderProducts">
                                                <div class="flex gap-4 bg-white items-center rounded-lg p-4">
                                                    <div>
                                                        <img :src="product.image" x-cloak x-show="product.image"
                                                             class="object-cover rounded-lg w-20 h-20">
                                                    </div>
                                                    <div class="grid">
                                                        <p class="text-sm font-medium" x-html="product.name"></p>
                                                        <p class="text-sm text-gray-400">
                                                            <span x-html="product.quantity"></span>
                                                            x voor
                                                            <span x-html="product.priceFormatted"></span>
                                                        </p>
                                                    </div>
                                                    <div class="ml-auto grow text-right">
                                                        <div
                                                            class="flex items-center gap-2 rounded-lg bg-primary-800 p-2 ml-auto w-fit text-white">
                                                            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24"
                                                                 fill="currentColor"
                                                                 class="size-10 text-primary-500 hover:text-primary-700 cursor-pointer"
                                                                 @click="changeRefundQuantity(product, product.refundQuantity - 1, product.quantity)">
                                                                <path fill-rule="evenodd"
                                                                      d="M12 2.25c-5.385 0-9.75 4.365-9.75 9.75s4.365 9.75 9.75 9.75 9.75-4.365 9.75-9.75S17.385 2.25 12 2.25Zm3 10.5a.75.75 0 0 0 0-1.5H9a.75.75 0 0 0 0 1.5h6Z"
                                                                      clip-rule="evenodd"/>
                                                            </svg>

                                                            <p class="text-md font-medium"
                                                               x-html="product.refundQuantity"></p>
                                                            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24"
                                                                 fill="currentColor"
                                                                 class="size-10 text-primary-500 hover:text-primary-700 cursor-pointer"
                                                                 @click="changeRefundQuantity(product, product.refundQuantity + 1, product.quantity)">
                                                                <path fill-rule="evenodd"
                                                                      d="M12 2.25c-5.385 0-9.75 4.365-9.75 9.75s4.365 9.75 9.75 9.75 9.75-4.365 9.75-9.75S17.385 2.25 12 2.25ZM12.75 9a.75.75 0 0 0-1.5 0v2.25H9a.75.75 0 0 0 0 1.5h2.25V15a.75.75 0 0 0 1.5 0v-2.25H15a.75.75 0 0 0 0-1.5h-2.25V9Z"
                                                                      clip-rule="evenodd"/>
                                                            </svg>
                                                        </div>
                                                    </div>
                                                </div>
                                            </template>
                                            <div class="bg-white p-4 rounded-lg grid gap-4">
                                                <div
                                                    @click="selectedOrder.cancelData.extraOrderLine = !selectedOrder.cancelData.extraOrderLine"
                                                    class="cursor-pointer flex items-center gap-2">
                                                    <button type="button"
                                                            :class="selectedOrder.cancelData.extraOrderLine ? 'bg-primary-600' : 'bg-gray-200'"
                                                            class="relative inline-flex h-6 w-11 shrink-0 cursor-pointer rounded-full border-2 border-transparent bg-gray-200 transition-colors duration-200 ease-in-out focus:outline-none focus:ring-2 focus:ring-primary-600 focus:ring-offset-2"
                                                            role="switch" aria-checked="false">
                                                <span
                                                    :class="selectedOrder.cancelData.extraOrderLine ? 'translate-x-5' : 'translate-x-0'"
                                                    class="pointer-events-none relative inline-block size-5 translate-x-0 transform rounded-full bg-white shadow ring-0 transition duration-200 ease-in-out">
                                                <span
                                                    :class="selectedOrder.cancelData.extraOrderLine ? 'opacity-0 duration-100 ease-out' : 'opacity-100 duration-200 ease-in'"
                                                    class="absolute inset-0 flex size-full items-center justify-center opacity-100 transition-opacity duration-200 ease-in"
                                                    aria-hidden="true">
                                                  <svg class="size-3 text-gray-400" fill="none" viewBox="0 0 12 12">
                                                    <path d="M4 8l2-2m0 0l2-2M6 6L4 4m2 2l2 2" stroke="currentColor"
                                                          stroke-width="2" stroke-linecap="round"
                                                          stroke-linejoin="round"/>
                                                  </svg>
                                                </span>
                                                <span
                                                    :class="selectedOrder.cancelData.extraOrderLine ? 'opacity-100 duration-200 ease-in' : 'opacity-0 duration-100 ease-out'"
                                                    class="absolute inset-0 flex size-full items-center justify-center opacity-0 transition-opacity duration-100 ease-out"
                                                    aria-hidden="true">
                                                  <svg class="size-3 text-primary-600" fill="currentColor"
                                                       viewBox="0 0 12 12">
                                                    <path
                                                        d="M3.707 5.293a1 1 0 00-1.414 1.414l1.414-1.414zM5 8l-.707.707a1 1 0 001.414 0L5 8zm4.707-3.293a1 1 0 00-1.414-1.414l1.414 1.414zm-7.414 2l2 2 1.414-1.414-2-2-1.414 1.414zm3.414 2l4-4-1.414-1.414-4 4 1.414 1.414z"/>
                                                  </svg>
                                                </span>
                                              </span>
                                                    </button>
                                                    <p>Voeg een extra bestel regel toe</p>
                                                </div>
                                                <div x-show="selectedOrder.cancelData.extraOrderLine"
                                                     class="grid grid-cols-2 gap-4 bg-white items-center rounded-lg p-4">
                                                    <input x-model="selectedOrder.cancelData.extraOrderLineName"
                                                           placeholder="Extra bestel regel naam"
                                                           class="text-black w-full rounded-lg text-md">
                                                    <input x-model="selectedOrder.cancelData.extraOrderLinePrice"
                                                           type="number"
                                                           placeholder="Extra bestel regel prijs"
                                                           class="text-black w-full rounded-lg text-md">
                                                </div>
                                                <div
                                                    @click="selectedOrder.cancelData.sendCustomerEmail = !selectedOrder.cancelData.sendCustomerEmail"
                                                    class="cursor-pointer flex items-center gap-2">
                                                    <button type="button"
                                                            :class="selectedOrder.cancelData.sendCustomerEmail ? 'bg-primary-600' : 'bg-gray-200'"
                                                            class="relative inline-flex h-6 w-11 shrink-0 cursor-pointer rounded-full border-2 border-transparent bg-gray-200 transition-colors duration-200 ease-in-out focus:outline-none focus:ring-2 focus:ring-primary-600 focus:ring-offset-2"
                                                            role="switch" aria-checked="false">
                                                <span
                                                    :class="selectedOrder.cancelData.sendCustomerEmail ? 'translate-x-5' : 'translate-x-0'"
                                                    class="pointer-events-none relative inline-block size-5 translate-x-0 transform rounded-full bg-white shadow ring-0 transition duration-200 ease-in-out">
                                                <span
                                                    :class="selectedOrder.cancelData.sendCustomerEmail ? 'opacity-0 duration-100 ease-out' : 'opacity-100 duration-200 ease-in'"
                                                    class="absolute inset-0 flex size-full items-center justify-center opacity-100 transition-opacity duration-200 ease-in"
                                                    aria-hidden="true">
                                                  <svg class="size-3 text-gray-400" fill="none" viewBox="0 0 12 12">
                                                    <path d="M4 8l2-2m0 0l2-2M6 6L4 4m2 2l2 2" stroke="currentColor"
                                                          stroke-width="2" stroke-linecap="round"
                                                          stroke-linejoin="round"/>
                                                  </svg>
                                                </span>
                                                <span
                                                    :class="selectedOrder.cancelData.sendCustomerEmail ? 'opacity-100 duration-200 ease-in' : 'opacity-0 duration-100 ease-out'"
                                                    class="absolute inset-0 flex size-full items-center justify-center opacity-0 transition-opacity duration-100 ease-out"
                                                    aria-hidden="true">
                                                  <svg class="size-3 text-primary-600" fill="currentColor"
                                                       viewBox="0 0 12 12">
                                                    <path
                                                        d="M3.707 5.293a1 1 0 00-1.414 1.414l1.414-1.414zM5 8l-.707.707a1 1 0 001.414 0L5 8zm4.707-3.293a1 1 0 00-1.414-1.414l1.414 1.414zm-7.414 2l2 2 1.414-1.414-2-2-1.414 1.414zm3.414 2l4-4-1.414-1.414-4 4 1.414 1.414z"/>
                                                  </svg>
                                                </span>
                                              </span>
                                                    </button>
                                                    <p>Moet de klant een mail krijgen van deze
                                                        annulering/retournering?</p>
                                                </div>
                                                <div
                                                    @click="selectedOrder.cancelData.productsMustBeReturned = !selectedOrder.cancelData.productsMustBeReturned"
                                                    class="cursor-pointer flex items-center gap-2">
                                                    <button type="button"
                                                            :class="selectedOrder.cancelData.productsMustBeReturned ? 'bg-primary-600' : 'bg-gray-200'"
                                                            class="relative inline-flex h-6 w-11 shrink-0 cursor-pointer rounded-full border-2 border-transparent bg-gray-200 transition-colors duration-200 ease-in-out focus:outline-none focus:ring-2 focus:ring-primary-600 focus:ring-offset-2"
                                                            role="switch" aria-checked="false">
                                                <span
                                                    :class="selectedOrder.cancelData.productsMustBeReturned ? 'translate-x-5' : 'translate-x-0'"
                                                    class="pointer-events-none relative inline-block size-5 translate-x-0 transform rounded-full bg-white shadow ring-0 transition duration-200 ease-in-out">
                                                <span
                                                    :class="selectedOrder.cancelData.productsMustBeReturned ? 'opacity-0 duration-100 ease-out' : 'opacity-100 duration-200 ease-in'"
                                                    class="absolute inset-0 flex size-full items-center justify-center opacity-100 transition-opacity duration-200 ease-in"
                                                    aria-hidden="true">
                                                  <svg class="size-3 text-gray-400" fill="none" viewBox="0 0 12 12">
                                                    <path d="M4 8l2-2m0 0l2-2M6 6L4 4m2 2l2 2" stroke="currentColor"
                                                          stroke-width="2" stroke-linecap="round"
                                                          stroke-linejoin="round"/>
                                                  </svg>
                                                </span>
                                                <span
                                                    :class="selectedOrder.cancelData.productsMustBeReturned ? 'opacity-100 duration-200 ease-in' : 'opacity-0 duration-100 ease-out'"
                                                    class="absolute inset-0 flex size-full items-center justify-center opacity-0 transition-opacity duration-100 ease-out"
                                                    aria-hidden="true">
                                                  <svg class="size-3 text-primary-600" fill="currentColor"
                                                       viewBox="0 0 12 12">
                                                    <path
                                                        d="M3.707 5.293a1 1 0 00-1.414 1.414l1.414-1.414zM5 8l-.707.707a1 1 0 001.414 0L5 8zm4.707-3.293a1 1 0 00-1.414-1.414l1.414 1.414zm-7.414 2l2 2 1.414-1.414-2-2-1.414 1.414zm3.414 2l4-4-1.414-1.414-4 4 1.414 1.414z"/>
                                                  </svg>
                                                </span>
                                              </span>
                                                    </button>
                                                    <p>Moet de klant de producten nog retourneren?</p>
                                                </div>
                                                <div
                                                    @click="selectedOrder.cancelData.restock = !selectedOrder.cancelData.restock"
                                                    class="cursor-pointer flex items-center gap-2">
                                                    <button type="button"
                                                            :class="selectedOrder.cancelData.restock ? 'bg-primary-600' : 'bg-gray-200'"
                                                            class="relative inline-flex h-6 w-11 shrink-0 cursor-pointer rounded-full border-2 border-transparent bg-gray-200 transition-colors duration-200 ease-in-out focus:outline-none focus:ring-2 focus:ring-primary-600 focus:ring-offset-2"
                                                            role="switch" aria-checked="false">
                                                <span
                                                    :class="selectedOrder.cancelData.restock ? 'translate-x-5' : 'translate-x-0'"
                                                    class="pointer-events-none relative inline-block size-5 translate-x-0 transform rounded-full bg-white shadow ring-0 transition duration-200 ease-in-out">
                                                <span
                                                    :class="selectedOrder.cancelData.restock ? 'opacity-0 duration-100 ease-out' : 'opacity-100 duration-200 ease-in'"
                                                    class="absolute inset-0 flex size-full items-center justify-center opacity-100 transition-opacity duration-200 ease-in"
                                                    aria-hidden="true">
                                                  <svg class="size-3 text-gray-400" fill="none" viewBox="0 0 12 12">
                                                    <path d="M4 8l2-2m0 0l2-2M6 6L4 4m2 2l2 2" stroke="currentColor"
                                                          stroke-width="2" stroke-linecap="round"
                                                          stroke-linejoin="round"/>
                                                  </svg>
                                                </span>
                                                <span
                                                    :class="selectedOrder.cancelData.restock ? 'opacity-100 duration-200 ease-in' : 'opacity-0 duration-100 ease-out'"
                                                    class="absolute inset-0 flex size-full items-center justify-center opacity-0 transition-opacity duration-100 ease-out"
                                                    aria-hidden="true">
                                                  <svg class="size-3 text-primary-600" fill="currentColor"
                                                       viewBox="0 0 12 12">
                                                    <path
                                                        d="M3.707 5.293a1 1 0 00-1.414 1.414l1.414-1.414zM5 8l-.707.707a1 1 0 001.414 0L5 8zm4.707-3.293a1 1 0 00-1.414-1.414l1.414 1.414zm-7.414 2l2 2 1.414-1.414-2-2-1.414 1.414zm3.414 2l4-4-1.414-1.414-4 4 1.414 1.414z"/>
                                                  </svg>
                                                </span>
                                              </span>
                                                    </button>
                                                    <p>Moet de voorraad weer terug geboekt worden?</p>
                                                </div>
                                                <div x-show="selectedOrder.discountFormatted"
                                                     @click="selectedOrder.cancelData.refundDiscountCosts = !selectedOrder.cancelData.refundDiscountCosts"
                                                     class="cursor-pointer flex items-center gap-2">
                                                    <button type="button"
                                                            :class="selectedOrder.cancelData.refundDiscountCosts ? 'bg-primary-600' : 'bg-gray-200'"
                                                            class="relative inline-flex h-6 w-11 shrink-0 cursor-pointer rounded-full border-2 border-transparent bg-gray-200 transition-colors duration-200 ease-in-out focus:outline-none focus:ring-2 focus:ring-primary-600 focus:ring-offset-2"
                                                            role="switch" aria-checked="false">
                                                <span
                                                    :class="selectedOrder.cancelData.refundDiscountCosts ? 'translate-x-5' : 'translate-x-0'"
                                                    class="pointer-events-none relative inline-block size-5 translate-x-0 transform rounded-full bg-white shadow ring-0 transition duration-200 ease-in-out">
                                                <span
                                                    :class="selectedOrder.cancelData.refundDiscountCosts ? 'opacity-0 duration-100 ease-out' : 'opacity-100 duration-200 ease-in'"
                                                    class="absolute inset-0 flex size-full items-center justify-center opacity-100 transition-opacity duration-200 ease-in"
                                                    aria-hidden="true">
                                                  <svg class="size-3 text-gray-400" fill="none" viewBox="0 0 12 12">
                                                    <path d="M4 8l2-2m0 0l2-2M6 6L4 4m2 2l2 2" stroke="currentColor"
                                                          stroke-width="2" stroke-linecap="round"
                                                          stroke-linejoin="round"/>
                                                  </svg>
                                                </span>
                                                <span
                                                    :class="selectedOrder.cancelData.refundDiscountCosts ? 'opacity-100 duration-200 ease-in' : 'opacity-0 duration-100 ease-out'"
                                                    class="absolute inset-0 flex size-full items-center justify-center opacity-0 transition-opacity duration-100 ease-out"
                                                    aria-hidden="true">
                                                  <svg class="size-3 text-primary-600" fill="currentColor"
                                                       viewBox="0 0 12 12">
                                                    <path
                                                        d="M3.707 5.293a1 1 0 00-1.414 1.414l1.414-1.414zM5 8l-.707.707a1 1 0 001.414 0L5 8zm4.707-3.293a1 1 0 00-1.414-1.414l1.414 1.414zm-7.414 2l2 2 1.414-1.414-2-2-1.414 1.414zm3.414 2l4-4-1.414-1.414-4 4 1.414 1.414z"/>
                                                  </svg>
                                                </span>
                                              </span>
                                                    </button>
                                                    <p>Korting terugvorderen? (<span
                                                            x-html="selectedOrder.discountFormatted"></span>) (Dit
                                                        geldt alleen voor vaste korting, ex. €40,-, procentuele korting
                                                        is op product niveau en wordt altijd terug gevorderd)</p>
                                                </div>

                                                <div class="grid">
                                                    <p>Betaalmethode</p>
                                                    <select x-model="selectedOrder.cancelData.paymentMethodId"
                                                            class="text-black w-full rounded-lg text-md">
                                                        <template
                                                            x-for="(name, id) in selectedOrder.cancelData.paymentMethods">
                                                            <option x-value="id" x-html="name"></option>
                                                        </template>
                                                    </select>
                                                </div>

                                                <div class="grid">
                                                    <p>Fulfillment status</p>
                                                    <select x-model="selectedOrder.cancelData.fulfillmentStatus"
                                                            class="text-black w-full rounded-lg text-md">
                                                        <template
                                                            x-for="(name, id) in selectedOrder.cancelData.fulfillmentStatusOptions">
                                                            <option x-value="id" x-html="name"></option>
                                                        </template>
                                                    </select>
                                                </div>
                                            </div>

                                        </div>
                                    </div>
                                    <div>
                                        <button type="submit"
                                                class="px-4 py-2 text-lg uppercase rounded-lg bg-primary-500 hover:bg-primary-700 transition-all ease-in-out duration-300 text-white font-bold w-full">
                                            Retourneren
                                        </button>
                                    </div>
                                </div>
                            </form>
                        </div>
                    </div>
                    <div class="grid gap-4">
                        <div class="grid gap-4">
                            <div class="grid gap-4 bg-white rounded-lg p-4">
                                <p class="text-2xl font-bold">Bestelling #<span x-html="selectedOrder.invoiceId"></span>
                                </p>
                                <div class="flex items-center flex-wrap gap-2 text-sm">
                                    <p class="text-gray-400 flex gap-1 items-center">
                                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24"
                                             stroke-width="1.5" stroke="currentColor" class="size-6">
                                            <path stroke-linecap="round" stroke-linejoin="round"
                                                  d="M12 6v6h4.5m4.5 0a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z"/>
                                        </svg>

                                        <span x-html="selectedOrder.createdAt"></span>
                                    </p>
                                    <p>|</p>
                                    <p class="text-gray-400 flex gap-1 items-center">
                                        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor"
                                             class="size-6">
                                            <path fill-rule="evenodd"
                                                  d="M1.5 9.832v1.793c0 1.036.84 1.875 1.875 1.875h17.25c1.035 0 1.875-.84 1.875-1.875V9.832a3 3 0 0 0-.722-1.952l-3.285-3.832A3 3 0 0 0 16.215 3h-8.43a3 3 0 0 0-2.278 1.048L2.222 7.88A3 3 0 0 0 1.5 9.832ZM7.785 4.5a1.5 1.5 0 0 0-1.139.524L3.881 8.25h3.165a3 3 0 0 1 2.496 1.336l.164.246a1.5 1.5 0 0 0 1.248.668h2.092a1.5 1.5 0 0 0 1.248-.668l.164-.246a3 3 0 0 1 2.496-1.336h3.165l-2.765-3.226a1.5 1.5 0 0 0-1.139-.524h-8.43Z"
                                                  clip-rule="evenodd"/>
                                            <path
                                                d="M2.813 15c-.725 0-1.313.588-1.313 1.313V18a3 3 0 0 0 3 3h15a3 3 0 0 0 3-3v-1.688c0-.724-.588-1.312-1.313-1.312h-4.233a3 3 0 0 0-2.496 1.336l-.164.246a1.5 1.5 0 0 1-1.248.668h-2.092a1.5 1.5 0 0 1-1.248-.668l-.164-.246A3 3 0 0 0 7.046 15H2.812Z"/>
                                        </svg>

                                        <span x-html="selectedOrder.fulfillmenStatus"></span>
                                    </p>
                                    <p>|</p>
                                    <p class="text-green-800 rounded-lg bg-green-300 px-1 py-0.5 flex gap-1 items-center"
                                       x-show="selectedOrder.status == 'paid'">
                                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24"
                                             stroke-width="1.5" stroke="currentColor" class="size-6">
                                            <path stroke-linecap="round" stroke-linejoin="round"
                                                  d="M2.25 18.75a60.07 60.07 0 0 1 15.797 2.101c.727.198 1.453-.342 1.453-1.096V18.75M3.75 4.5v.75A.75.75 0 0 1 3 6h-.75m0 0v-.375c0-.621.504-1.125 1.125-1.125H20.25M2.25 6v9m18-10.5v.75c0 .414.336.75.75.75h.75m-1.5-1.5h.375c.621 0 1.125.504 1.125 1.125v9.75c0 .621-.504 1.125-1.125 1.125h-.375m1.5-1.5H21a.75.75 0 0 0-.75.75v.75m0 0H3.75m0 0h-.375a1.125 1.125 0 0 1-1.125-1.125V15m1.5 1.5v-.75A.75.75 0 0 0 3 15h-.75M15 10.5a3 3 0 1 1-6 0 3 3 0 0 1 6 0Zm3 0h.008v.008H18V10.5Zm-12 0h.008v.008H6V10.5Z"/>
                                        </svg>

                                        <span>Betaald</span>
                                    </p>
                                    <p class="text-red-800 rounded-lg bg-red-300 px-1 py-0.5 flex gap-1 items-center"
                                       x-show="selectedOrder.status == 'cancelled'">
                                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24"
                                             stroke-width="1.5" stroke="currentColor" class="size-6">
                                            <path stroke-linecap="round" stroke-linejoin="round"
                                                  d="M2.25 18.75a60.07 60.07 0 0 1 15.797 2.101c.727.198 1.453-.342 1.453-1.096V18.75M3.75 4.5v.75A.75.75 0 0 1 3 6h-.75m0 0v-.375c0-.621.504-1.125 1.125-1.125H20.25M2.25 6v9m18-10.5v.75c0 .414.336.75.75.75h.75m-1.5-1.5h.375c.621 0 1.125.504 1.125 1.125v9.75c0 .621-.504 1.125-1.125 1.125h-.375m1.5-1.5H21a.75.75 0 0 0-.75.75v.75m0 0H3.75m0 0h-.375a1.125 1.125 0 0 1-1.125-1.125V15m1.5 1.5v-.75A.75.75 0 0 0 3 15h-.75M15 10.5a3 3 0 1 1-6 0 3 3 0 0 1 6 0Zm3 0h.008v.008H18V10.5Zm-12 0h.008v.008H6V10.5Z"/>
                                        </svg>
                                        <span>Geannuleerd</span>
                                    </p>
                                    <p class="text-blue-800 rounded-lg bg-blue-300 px-1 py-0.5 flex gap-1 items-center"
                                       x-show="selectedOrder.status == 'pending'">
                                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24"
                                             stroke-width="1.5" stroke="currentColor" class="size-6">
                                            <path stroke-linecap="round" stroke-linejoin="round"
                                                  d="M2.25 18.75a60.07 60.07 0 0 1 15.797 2.101c.727.198 1.453-.342 1.453-1.096V18.75M3.75 4.5v.75A.75.75 0 0 1 3 6h-.75m0 0v-.375c0-.621.504-1.125 1.125-1.125H20.25M2.25 6v9m18-10.5v.75c0 .414.336.75.75.75h.75m-1.5-1.5h.375c.621 0 1.125.504 1.125 1.125v9.75c0 .621-.504 1.125-1.125 1.125h-.375m1.5-1.5H21a.75.75 0 0 0-.75.75v.75m0 0H3.75m0 0h-.375a1.125 1.125 0 0 1-1.125-1.125V15m1.5 1.5v-.75A.75.75 0 0 0 3 15h-.75M15 10.5a3 3 0 1 1-6 0 3 3 0 0 1 6 0Zm3 0h.008v.008H18V10.5Zm-12 0h.008v.008H6V10.5Z"/>
                                        </svg>

                                        <span>In afwachting van betaling</span>
                                    </p>
                                    <p class="text-orange-800 rounded-lg bg-orange-300 px-1 py-0.5 flex gap-1 items-center"
                                       x-show="selectedOrder.status == 'return'">
                                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24"
                                             stroke-width="1.5" stroke="currentColor" class="size-6">
                                            <path stroke-linecap="round" stroke-linejoin="round"
                                                  d="M2.25 18.75a60.07 60.07 0 0 1 15.797 2.101c.727.198 1.453-.342 1.453-1.096V18.75M3.75 4.5v.75A.75.75 0 0 1 3 6h-.75m0 0v-.375c0-.621.504-1.125 1.125-1.125H20.25M2.25 6v9m18-10.5v.75c0 .414.336.75.75.75h.75m-1.5-1.5h.375c.621 0 1.125.504 1.125 1.125v9.75c0 .621-.504 1.125-1.125 1.125h-.375m1.5-1.5H21a.75.75 0 0 0-.75.75v.75m0 0H3.75m0 0h-.375a1.125 1.125 0 0 1-1.125-1.125V15m1.5 1.5v-.75A.75.75 0 0 0 3 15h-.75M15 10.5a3 3 0 1 1-6 0 3 3 0 0 1 6 0Zm3 0h.008v.008H18V10.5Zm-12 0h.008v.008H6V10.5Z"/>
                                        </svg>

                                        <span>Retour</span>
                                    </p>
                                    <p>|</p>
                                    <p class="text-gray-400 flex gap-1 items-center">
                                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24"
                                             stroke-width="1.5" stroke="currentColor" class="size-6">
                                            <path stroke-linecap="round" stroke-linejoin="round"
                                                  d="M8.25 18.75a1.5 1.5 0 0 1-3 0m3 0a1.5 1.5 0 0 0-3 0m3 0h6m-9 0H3.375a1.125 1.125 0 0 1-1.125-1.125V14.25m17.25 4.5a1.5 1.5 0 0 1-3 0m3 0a1.5 1.5 0 0 0-3 0m3 0h1.125c.621 0 1.129-.504 1.09-1.124a17.902 17.902 0 0 0-3.213-9.193 2.056 2.056 0 0 0-1.58-.86H14.25M16.5 18.75h-2.25m0-11.177v-.958c0-.568-.422-1.048-.987-1.106a48.554 48.554 0 0 0-10.026 0 1.106 1.106 0 0 0-.987 1.106v7.635m12-6.677v6.677m0 4.5v-4.5m0 0h-12"/>
                                        </svg>

                                        <span x-html="selectedOrder.shippingMethod"></span>
                                    </p>
                                </div>
                                <div class="flex flex-wrap gap-4">
                                    <button @click="printOrder(selectedOrder)"
                                            class="h-12 w-fit px-2 py-1 gap-2 bg-primary-500 text-white hover:bg-primary-700 transition-all duration-300 ease-in-out rounded-full flex items-center justify-center">
                                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24"
                                             stroke-width="1.5" stroke="currentColor" class="size-6">
                                            <path stroke-linecap="round" stroke-linejoin="round"
                                                  d="m9 14.25 6-6m4.5-3.493V21.75l-3.75-1.5-3.75 1.5-3.75-1.5-3.75 1.5V4.757c0-1.108.806-2.057 1.907-2.185a48.507 48.507 0 0 1 11.186 0c1.1.128 1.907 1.077 1.907 2.185ZM9.75 9h.008v.008H9.75V9Zm.375 0a.375.375 0 1 1-.75 0 .375.375 0 0 1 .75 0Zm4.125 4.5h.008v.008h-.008V13.5Zm.375 0a.375.375 0 1 1-.75 0 .375.375 0 0 1 .75 0Z"/>
                                        </svg>

                                        <span>Kassabon printen</span>
                                    </button>
                                    <button @click="sendInvoice(selectedOrder)"
                                            x-show="selectedOrder.email"
                                            class="h-12 w-fit px-2 py-1 gap-2 bg-primary-500 text-white hover:bg-primary-700 transition-all duration-300 ease-in-out rounded-full flex items-center justify-center">
                                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24"
                                             stroke-width="1.5" stroke="currentColor" class="size-6">
                                            <path stroke-linecap="round" stroke-linejoin="round"
                                                  d="M21.75 6.75v10.5a2.25 2.25 0 0 1-2.25 2.25h-15a2.25 2.25 0 0 1-2.25-2.25V6.75m19.5 0A2.25 2.25 0 0 0 19.5 4.5h-15a2.25 2.25 0 0 0-2.25 2.25m19.5 0v.243a2.25 2.25 0 0 1-1.07 1.916l-7.5 4.615a2.25 2.25 0 0 1-2.36 0L3.32 8.91a2.25 2.25 0 0 1-1.07-1.916V6.75"/>
                                        </svg>


                                        <span>Factuur versturen</span>
                                    </button>
                                    <button @click="toggle('cancelOrderPopup')"
                                            class="h-12 w-fit px-2 py-1 gap-2 bg-primary-500 text-white hover:bg-primary-700 transition-all duration-300 ease-in-out rounded-full flex items-center justify-center">
                                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24"
                                             stroke-width="1.5" stroke="currentColor" class="size-6">
                                            <path stroke-linecap="round" stroke-linejoin="round"
                                                  d="M9 15 3 9m0 0 6-6M3 9h12a6 6 0 0 1 0 12h-3"/>
                                        </svg>

                                        <span>Retourneren</span>
                                    </button>
                                </div>
                            </div>
                            <div class="grid gap-2">
                                <p class="text-white uppercase text-sm">Betaaloverzicht</p>
                                <div class="grid gap-2 bg-white rounded-lg p-4">
                                    <div class="flex gap-4">
                                        <div>
                                            <p>Aantal producten</p>
                                        </div>
                                        <div class="ml-auto grow text-right">
                                            <p class="text-md font-medium" x-html="selectedOrder.totalProducts"></p>
                                        </div>
                                    </div>
                                    <hr/>
                                    <div class="flex gap-4 bg-white rounded-lg"
                                         x-show="selectedOrder.discountFormatted">
                                        <div>
                                            <p>Korting</p>
                                        </div>
                                        <div class="ml-auto grow text-right">
                                            <p class="text-md font-medium" x-html="selectedOrder.discountFormatted"></p>
                                        </div>
                                    </div>
                                    <hr x-show="selectedOrder.discountFormatted"/>
                                    <template x-for="(value, percentage) in selectedOrder.vatPercentages">
                                        <div class="flex gap-4 bg-white rounded-lg">
                                            <div>
                                                <p>BTW <span x-html="percentage"></span></p>
                                            </div>
                                            <div class="ml-auto grow text-right">
                                                <p class="text-md font-medium" x-html="value"></p>
                                            </div>
                                        </div>
                                    </template>
                                    <hr/>
                                    <div class="flex gap-4 bg-white rounded-lg"
                                         x-show="selectedOrder.vatPercentages.length > 1">
                                        <div>
                                            <p>BTW</p>
                                        </div>
                                        <div class="ml-auto grow text-right">
                                            <p class="text-md font-medium" x-html="selectedOrder.taxFormatted"></p>
                                        </div>
                                    </div>
                                    <hr x-show="selectedOrder.vatPercentages.length > 1"/>
                                    <div class="flex gap-4 bg-white rounded-lg font-bold">
                                        <div>
                                            <p class="">Totaal</p>
                                        </div>
                                        <div class="ml-auto grow text-right">
                                            <p class="text-md font-medium" x-html="selectedOrder.totalFormatted"></p>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <div class="grid gap-2">
                                <p class="text-white uppercase text-sm">Producten</p>
                                <div class="grid grid-cols-2 gap-4">
                                    <template x-for="product in selectedOrder.orderProducts">
                                        <div class="flex gap-4 bg-white rounded-lg p-4">
                                            <div>
                                                <img :src="product.image" x-cloak x-show="product.image"
                                                     class="object-cover rounded-lg w-20 h-20">
                                            </div>
                                            <div class="grid gap-2">
                                                <p class="text-sm font-medium" x-html="product.name"></p>
                                                <p class="text-sm text-gray-400"><span x-html="product.quantity"></span>x
                                                </p>
                                            </div>
                                            <div class="ml-auto grow text-right">
                                                <p class="text-md font-medium" x-html="product.priceFormatted"></p>
                                            </div>
                                        </div>
                                    </template>
                                </div>
                            </div>
                            <div class="grid gap-2">
                                <p class="text-white uppercase text-sm">Betalingen</p>
                                <div class="grid grid-cols-2 gap-4">
                                    <template x-for="payment in selectedOrder.orderPayments">
                                        <div class="flex justify-between items-center gap-4 bg-white rounded-lg p-4">
                                            <p class="font-medium" x-html="payment.paymentMethod"></p>
                                            <p class="text-green-800 rounded-lg bg-green-300 px-1 py-0.5 flex gap-1"
                                               x-show="payment.status == 'paid'">
                                                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24"
                                                     stroke-width="1.5" stroke="currentColor" class="size-6">
                                                    <path stroke-linecap="round" stroke-linejoin="round"
                                                          d="M2.25 18.75a60.07 60.07 0 0 1 15.797 2.101c.727.198 1.453-.342 1.453-1.096V18.75M3.75 4.5v.75A.75.75 0 0 1 3 6h-.75m0 0v-.375c0-.621.504-1.125 1.125-1.125H20.25M2.25 6v9m18-10.5v.75c0 .414.336.75.75.75h.75m-1.5-1.5h.375c.621 0 1.125.504 1.125 1.125v9.75c0 .621-.504 1.125-1.125 1.125h-.375m1.5-1.5H21a.75.75 0 0 0-.75.75v.75m0 0H3.75m0 0h-.375a1.125 1.125 0 0 1-1.125-1.125V15m1.5 1.5v-.75A.75.75 0 0 0 3 15h-.75M15 10.5a3 3 0 1 1-6 0 3 3 0 0 1 6 0Zm3 0h.008v.008H18V10.5Zm-12 0h.008v.008H6V10.5Z"/>
                                                </svg>

                                                <span>Betaald</span>
                                            </p>
                                            <p class="text-yellow-800 rounded-lg bg-yellow-300 px-1 py-0.5 flex gap-1"
                                               x-show="payment.status == 'cancelled'">
                                                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24"
                                                     stroke-width="1.5" stroke="currentColor" class="size-6">
                                                    <path stroke-linecap="round" stroke-linejoin="round"
                                                          d="M2.25 18.75a60.07 60.07 0 0 1 15.797 2.101c.727.198 1.453-.342 1.453-1.096V18.75M3.75 4.5v.75A.75.75 0 0 1 3 6h-.75m0 0v-.375c0-.621.504-1.125 1.125-1.125H20.25M2.25 6v9m18-10.5v.75c0 .414.336.75.75.75h.75m-1.5-1.5h.375c.621 0 1.125.504 1.125 1.125v9.75c0 .621-.504 1.125-1.125 1.125h-.375m1.5-1.5H21a.75.75 0 0 0-.75.75v.75m0 0H3.75m0 0h-.375a1.125 1.125 0 0 1-1.125-1.125V15m1.5 1.5v-.75A.75.75 0 0 0 3 15h-.75M15 10.5a3 3 0 1 1-6 0 3 3 0 0 1 6 0Zm3 0h.008v.008H18V10.5Zm-12 0h.008v.008H6V10.5Z"/>
                                                </svg>
                                                <span>Geannuleerd</span>
                                            </p>
                                            <p class="text-blue-800 rounded-lg bg-blue-300 px-1 py-0.5 flex gap-1"
                                               x-show="payment.status == 'pending'">
                                                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24"
                                                     stroke-width="1.5" stroke="currentColor" class="size-6">
                                                    <path stroke-linecap="round" stroke-linejoin="round"
                                                          d="M2.25 18.75a60.07 60.07 0 0 1 15.797 2.101c.727.198 1.453-.342 1.453-1.096V18.75M3.75 4.5v.75A.75.75 0 0 1 3 6h-.75m0 0v-.375c0-.621.504-1.125 1.125-1.125H20.25M2.25 6v9m18-10.5v.75c0 .414.336.75.75.75h.75m-1.5-1.5h.375c.621 0 1.125.504 1.125 1.125v9.75c0 .621-.504 1.125-1.125 1.125h-.375m1.5-1.5H21a.75.75 0 0 0-.75.75v.75m0 0H3.75m0 0h-.375a1.125 1.125 0 0 1-1.125-1.125V15m1.5 1.5v-.75A.75.75 0 0 0 3 15h-.75M15 10.5a3 3 0 1 1-6 0 3 3 0 0 1 6 0Zm3 0h.008v.008H18V10.5Zm-12 0h.008v.008H6V10.5Z"/>
                                                </svg>

                                                <span>In afwachting van betaling</span>
                                            </p>
                                            <p class="text-md font-medium" x-html="payment.amountFormatted"></p>
                                        </div>
                                    </template>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </template>
        </div>
    </div>
</div>
@script
<script>
    Alpine.data('POSData', () => ({
        cartInstance: 'point-of-sale',
        orderOrigin: 'pos',
        posIdentifier: '',
        userId: {{ auth()->user()->id }},
        searchQueryInputmode: $wire.entangle('searchQueryInputmode'),
        searchProductQuery: '',
        lastOrder: null,
        orders: [],
        selectedOrder: null,
        searchOrderQuery: '',
        products: [],
        allProducts: [],
        loadingSearchedProducts: false,
        discountCode: null,
        discount: null,
        vat: null,
        vatPercentages: [],
        subTotal: null,
        total: null,
        totalUnformatted: null,
        activeDiscountCode: null,
        searchedProducts: [],
        paymentMethods: [],
        order: null,
        suggestedCashPaymentAmounts: [],
        chosenPaymentMethod: null,
        isPinTerminalPayment: false,
        pinTerminalStatus: false,
        pinTerminalError: false,
        pinTerminalErrorMessage: false,
        cashPaymentAmount: null,
        orderPayments: [],
        firstPaymentMethod: null,
        pinTerminalIntervalId: null,
        shippingMethods: [],
        shippingMethod: null,
        shippingMethodId: null,
        shippingMethodCosts: null,
        shippingMethodCostsUnformatted: null,
        postPay: null,
        orderUrl: null,
        productToChange: $wire.entangle('productToChange'),
        totalQuantity() {
            return this.products.reduce((sum, product) => sum + product.quantity, 0);
        },

        customProductPopup: false,
        createDiscountPopup: false,
        customerDataPopup: false,
        checkoutPopup: false,
        paymentPopup: false,
        ordersPopup: false,
        cancelOrderPopup: false,
        orderConfirmationPopup: false,
        chooseShippingMethodPopup: false,
        changeProductPricePopup: false,
        isFullscreen: false,
        pinTerminalStatusHandled: false,
        loading: false,

        customerUserId: $wire.entangle('customerUserId'),
        firstName: $wire.entangle('firstName'),
        lastName: $wire.entangle('lastName'),
        phoneNumber: $wire.entangle('phoneNumber'),
        email: $wire.entangle('email'),
        street: $wire.entangle('street'),
        houseNr: $wire.entangle('houseNr'),
        zipCode: $wire.entangle('zipCode'),
        city: $wire.entangle('city'),
        country: $wire.entangle('country'),
        company: $wire.entangle('company'),
        btwId: $wire.entangle('btwId'),
        invoiceStreet: $wire.entangle('invoiceStreet'),
        invoiceHouseNr: $wire.entangle('invoiceHouseNr'),
        invoiceZipCode: $wire.entangle('invoiceZipCode'),
        invoiceCity: $wire.entangle('invoiceCity'),
        invoiceCountry: $wire.entangle('invoiceCountry'),
        note: $wire.entangle('note'),
        customFields: $wire.entangle('customFields'),

        hasCashRegister: {{ Customsetting::get('cash_register_available', null, false) ? 'true' : 'false' }},

        toggle(variable) {
            this.loading = true;
            if (variable in this) {
                if (this[variable]) {
                    this.focus();
                }
                this[variable] = !this[variable];
            }
            this.loading = false;
        },

        disable(variable) {
            if (variable in this) {
                this[variable] = false;
            }
            this.focus();
        },

        enable(variable) {
            if (variable in this) {
                this[variable] = true;
            }
        },

        async openCashRegister() {
            this.loading = true;

            try {
                let response = await fetch('{{ route('api.point-of-sale.open-cash-register') }}', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'Accept': 'application/json',
                    }
                });


                let data = await response.json();

                if (!response.ok) {
                    this.loading = false;
                    this.focus();
                    return $wire.dispatch('notify', {
                        type: 'danger',
                        message: data.message,
                    })
                }

                $wire.dispatch('notify', {
                    type: 'success',
                    message: 'De kassa is geopend'
                })


                this.focus();
                this.loading = false;
            } catch (error) {

                this.loading = false;
                this.focus();
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
                this.shippingMethods = data.shippingMethods;
                this.shippingMethodId = data.shippingMethodId;
                this.shippingMethodCosts = data.shippingMethodCosts;
                this.customerUserId = data.customerUserId;
                this.firstName = data.firstName;
                this.lastName = data.lastName;
                this.phoneNumber = data.phoneNumber;
                this.email = data.email;
                this.street = data.street;
                this.houseNr = data.houseNr;
                this.zipCode = data.zipCode;
                this.city = data.city;
                this.company = data.company;
                this.country = data.country;
                this.btwId = data.btwId;
                this.invoiceStreet = data.invoiceStreet;
                this.invoiceHouseNr = data.invoiceHouseNr;
                this.invoiceZipCode = data.invoiceZipCode;
                this.invoiceCity = data.invoiceCity;
                this.invoiceCountry = data.invoiceCountry;
                this.note = data.note;
                this.discountCode = data.discountCode;
                this.customFields = data.customFields;
                this.retrieveCart();
                this.focus();
            } catch (error) {
                return $wire.dispatch('notify', {
                    type: 'danger',
                    message: 'De winkelwagen kon niet worden gestart'
                })
            }
        },

        async getAllProducts(clearCache = false) {
            try {
                let response = await fetch('{{ route('api.point-of-sale.get-all-products') }}', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'Accept': 'application/json',
                    },
                    body: JSON.stringify({
                        userId: this.userId,
                        clearCache: clearCache,
                    })
                });

                let data = await response.json();

                if (!response.ok) {
                    return $wire.dispatch('notify', {
                        type: 'danger',
                        message: data.message,
                    })
                }

                this.allProducts = data.products;
            } catch (error) {
                return $wire.dispatch('notify', {
                    type: 'danger',
                    message: 'De producten kon niet worden opgehaald'
                })
            }
        },

        async retrieveCart() {
            this.loading = true;
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
                    $wire.dispatch('notify', {
                        type: 'danger',
                        message: data.message,
                    })
                } else {
                    this.products = data.products;
                    this.discountCode = data.discountCode;
                    this.activeDiscountCode = data.activeDiscountCode;
                    this.discount = data.discount;
                    this.vat = data.vat;
                    this.vatPercentages = data.vatPercentages;
                    this.subTotal = data.subTotal;
                    this.total = data.total;
                    this.totalUnformatted = data.totalUnformatted;
                    this.shippingMethods = data.shippingMethods;
                    this.shippingMethodId = data.shippingMethodId;
                    this.shippingMethodCosts = data.shippingCosts;
                    this.shippingMethodCostsUnformatted = data.shippingCostsUnformatted;
                    this.paymentMethods = data.paymentMethods;
                }

            } catch (error) {
                $wire.dispatch('notify', {
                    type: 'danger',
                    message: 'De winkelwagen kon niet worden opgehaald'
                })
            }

            this.loading = false;
            return true;
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

        async printReceipt() {
            this.loading = true;

            if(!this.order){
                this.loading = false;
                return $wire.dispatch('notify', {
                    type: 'danger',
                    message: 'Er is geen bestelling om te printen',
                })
            }

            try {
                let response = await fetch('{{ route('api.point-of-sale.print-receipt') }}', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'Accept': 'application/json',
                    },
                    body: JSON.stringify({
                        orderId: this.order.id,
                        isCopy: false,
                    })
                });

                let data = await response.json();

                this.focus();

                if (!response.ok) {
                    this.loading = false;
                    return $wire.dispatch('notify', {
                        type: 'danger',
                        message: data.message,
                    })
                }

                this.loading = false;
                return $wire.dispatch('notify', {
                    type: 'success',
                    message: 'Bon geprint'
                })
            } catch (error) {
                this.loading = false;
                return $wire.dispatch('notify', {
                    type: 'danger',
                    message: 'De bon kon niet worden geprint'
                })
            }
        },

        async printOrder(order) {
            this.loading = true;
            try {
                let response = await fetch('{{ route('api.point-of-sale.print-receipt') }}', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'Accept': 'application/json',
                    },
                    body: JSON.stringify({
                        orderId: order.id,
                        isCopy: true,
                    })
                });

                let data = await response.json();

                this.focus();

                if (!response.ok) {
                    this.loading = false;
                    return $wire.dispatch('notify', {
                        type: 'danger',
                        message: data.message,
                    })
                }

                this.loading = false;
                return $wire.dispatch('notify', {
                    type: 'success',
                    message: 'Bon geprint'
                })
            } catch (error) {
                this.loading = false;
                return $wire.dispatch('notify', {
                    type: 'danger',
                    message: 'De bon kon niet worden geprint'
                })
            }
        },

        async sendInvoice(order) {
            this.loading = true;
            try {
                let response = await fetch('{{ route('api.point-of-sale.send-invoice') }}', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'Accept': 'application/json',
                    },
                    body: JSON.stringify({
                        orderId: order.id,
                        isCopy: true,
                    })
                });

                let data = await response.json();

                this.focus();

                if (!response.ok) {
                    this.loading = false;
                    return $wire.dispatch('notify', {
                        type: 'danger',
                        message: data.message,
                    })
                }

                this.loading = false;
                return $wire.dispatch('notify', {
                    type: 'success',
                    message: 'Factuur verstuurd'
                })
            } catch (error) {
                this.loading = false;
                return $wire.dispatch('notify', {
                    type: 'danger',
                    message: 'De factuur kon niet worden verstuurd'
                })
            }
        },

        async updateSearchedProducts() {
            this.loading = true;
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
                    this.loading = false;
                    return $wire.dispatch('notify', {
                        type: 'danger',
                        message: data.message,
                    })
                }
                this.loading = false;
            } catch (error) {
                this.loading = false;
                return $wire.dispatch('notify', {
                    type: 'danger',
                    message: 'De gezochte producten konden niet worden opgehaald'
                })
            }
        },

        async addProduct(productId) {
            this.loading = true;
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
                this.loadingSearchedProducts = false;

                if (!response.ok) {
                    this.loading = false;
                    return $wire.dispatch('notify', {
                        type: 'danger',
                        message: data.message,
                    })
                }

                this.products = data.products;
                this.retrieveCart();
                this.loading = false;
            } catch (error) {
                this.loading = false;
                return $wire.dispatch('notify', {
                    type: 'danger',
                    message: 'De gezochte producten konden niet worden opgehaald'
                })
            }
        },

        async selectProduct() {
            this.loading = true;
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
                    this.loading = false;
                    return $wire.dispatch('notify', {
                        type: 'danger',
                        message: data.message,
                    })
                }

                this.products = data.products;
                this.retrieveCart();
                this.loading = false;
            } catch (error) {
                this.loading = false;
                return $wire.dispatch('notify', {
                    type: 'danger',
                    message: 'Het gezochte product konden niet worden opgehaald'
                })
            }
        },

        async changeQuantity(productIdentifier, quantity) {
            this.loading = true;
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
                    this.loading = false;
                    return $wire.dispatch('notify', {
                        type: 'danger',
                        message: data.message,
                    })
                }

                this.products = data.products;
                this.retrieveCart();
                this.loading = false;
            } catch (error) {
                this.loading = false;
                return $wire.dispatch('notify', {
                    type: 'danger',
                    message: 'De gezochte producten konden niet worden opgehaald'
                })
            }
        },

        async clearProducts() {
            this.loading = true;
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
                    this.loading = false;
                    return $wire.dispatch('notify', {
                        type: 'danger',
                        message: data.message,
                    })
                }

                this.products = data.products;
                this.retrieveCart();
                this.loading = false;
            } catch (error) {
                this.loading = false;
                return $wire.dispatch('notify', {
                    type: 'danger',
                    message: 'De winkelmand kon niet worden geleegd'
                })
            }
        },

        async removeDiscount() {
            this.loading = true;
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
                    this.loading = false;
                    return $wire.dispatch('notify', {
                        type: 'danger',
                        message: data.message,
                    })
                }

                this.discountCode = null;
                this.activeDiscountCode = null;
                this.retrieveCart();
                this.loading = false;

            } catch (error) {
                this.loading = false;
                return $wire.dispatch('notify', {
                    type: 'danger',
                    message: 'De korting kon niet worden verwijderd'
                })
            }
        },

        async openChangeProductPricePopup(product) {
            this.loading = true;
            this.productToChange = product;
            this.toggle('changeProductPricePopup');
            this.loading = false;
        },

        async selectShippingMethod(shippingMethodId) {
            this.loading = true;
            try {
                let response = await fetch('{{ route('api.point-of-sale.select-shipping-method') }}', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'Accept': 'application/json',
                    },
                    body: JSON.stringify({
                        posIdentifier: this.posIdentifier,
                        cartInstance: this.cartInstance,
                        orderOrigin: this.orderOrigin,
                        shippingMethodId: shippingMethodId,
                        userId: this.userId,
                    })
                });

                let data = await response.json();

                if (!response.ok) {
                    this.loading = false;
                    return $wire.dispatch('notify', {
                        type: 'danger',
                        message: data.message,
                    })
                }

                this.shippingMethodId = data.shippingMethodId;
                this.shippingMethodCosts = data.shippingMethodCosts;

                this.toggle('chooseShippingMethodPopup');
                await this.retrieveCart();
                this.focus();
                this.loading = false;

            } catch (error) {
                this.loading = false;
                return $wire.dispatch('notify', {
                    type: 'danger',
                    message: 'De verzendmethode kon niet worden geselecteerd'
                })
            }
        },

        async removeShippingMethod() {
            this.loading = true;
            try {
                let response = await fetch('{{ route('api.point-of-sale.remove-shipping-method') }}', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'Accept': 'application/json',
                    },
                    body: JSON.stringify({
                        posIdentifier: this.posIdentifier,
                        cartInstance: this.cartInstance,
                        orderOrigin: this.orderOrigin,
                        userId: this.userId,
                    })
                });

                let data = await response.json();
                this.focus();

                if (!response.ok) {
                    this.loading = false;
                    return $wire.dispatch('notify', {
                        type: 'danger',
                        message: data.message,
                    })
                }

                this.shippingMethodId = null;
                this.retrieveCart();
                this.focus();
                this.loading = false;

            } catch (error) {
                this.loading = false;
                return $wire.dispatch('notify', {
                    type: 'danger',
                    message: 'De verzendmethode kon niet worden verwijderd'
                })
            }
        },

        async selectPaymentMethod(paymentMethodId) {
            this.loading = true;
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
                        userId: this.userId,
                    })
                });

                let data = await response.json();
                this.focus();

                if (!response.ok) {
                    this.loading = false;
                    return $wire.dispatch('notify', {
                        type: 'danger',
                        message: data.message,
                    })
                }

                this.isPinTerminalPayment = data.isPinTerminalPayment;
                this.chosenPaymentMethod = data.paymentMethod;
                this.suggestedCashPaymentAmounts = data.suggestedCashPaymentAmounts;
                this.order = data.order;
                this.postPay = data.postPay;
                this.orderUrl = data.orderUrl;

                this.disable('checkoutPopup');
                this.enable('paymentPopup');

                if (this.isPinTerminalPayment) {
                    this.startPinTerminalPayment();
                }
                this.loading = false;

            } catch (error) {
                this.loading = false;
                return $wire.dispatch('notify', {
                    type: 'danger',
                    message: 'De betaalmethode kon niet worden geselecteerd'
                })
            }
        },

        async saveCustomerData() {
            this.loading = true;
            try {
                let response = await fetch('{{ route('api.point-of-sale.update-customer-data') }}', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'Accept': 'application/json',
                    },
                    body: JSON.stringify({
                        posIdentifier: this.posIdentifier,
                        cartInstance: this.cartInstance,
                        orderOrigin: this.orderOrigin,
                        userId: this.userId,
                    })
                });

                let data = await response.json();
                this.focus();

                if (!response.ok) {
                    this.loading = false;
                    return $wire.dispatch('notify', {
                        type: 'danger',
                        message: data.message,
                    })
                }

                this.toggle('customerDataPopup');
                this.retrieveCart();
                this.loading = false;

            } catch (error) {
                this.loading = false;
                return $wire.dispatch('notify', {
                    type: 'danger',
                    message: 'De klant gegevens kon niet worden opgeslagen'
                })
            }
        },

        async startPinTerminalPayment(hasMultiplePayments = false) {
            this.isPinTerminalPayment = true;
            this.pinTerminalStatusHandled = false;
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
                        paymentMethod: this.chosenPaymentMethod,
                        userId: this.userId,
                        hasMultiplePayments: hasMultiplePayments,
                    })
                });

                let data = await response.json();
                this.focus();

                if (!response.ok) {
                    this.pinTerminalStatus = data.pinTerminalStatus;
                    this.pinTerminalError = data.pinTerminalError;
                    this.pinTerminalErrorMessage = data.pinTerminalErrorMessage;

                    return $wire.dispatch('notify', {
                        type: 'danger',
                        message: data.message,
                    })
                }

                this.pinTerminalStatus = data.pinTerminalStatus;
                this.pinTerminalError = data.pinTerminalError;
                this.pinTerminalErrorMessage = data.pinTerminalErrorMessage;

                if (this.pinTerminalStatus == 'pending') {
                    this.checkPinTerminalPayment();
                }

            } catch (error) {
                return $wire.dispatch('notify', {
                    type: 'danger',
                    message: 'De pin betaling kon niet worden gestart'
                })
            }
        },

        async createPaymentWithExtraPayment() {
            this.markAsPaid(true);
        },

        async closePayment() {
            this.loading = true;
            try {
                let response = await fetch('{{ route('api.point-of-sale.close-payment') }}', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'Accept': 'application/json',
                    },
                    body: JSON.stringify({
                        posIdentifier: this.posIdentifier,
                        order: this.order,
                    })
                });

                let data = await response.json();
                this.focus();

                if (!response.ok) {
                    this.loading = false;
                    return $wire.dispatch('notify', {
                        type: 'danger',
                        message: data.message,
                    });
                }

                $wire.dispatch('notify', {
                    type: 'success',
                    message: data.message,
                });

                this.isPinTerminalPayment = false;
                this.order = null;
                this.toggle('paymentPopup');
                this.loading = false;

            } catch (error) {
                this.loading = false;
                return $wire.dispatch('notify', {
                    type: 'danger',
                    message: 'De betaling kon niet worden gesloten'
                })
            }
        },

        async setCashPaymentAmount(amount) {
            this.loading = true;
            this.cashPaymentAmount = amount;
            this.markAsPaid();
            this.loading = false;
        },

        async markAsPaid(hasMultiplePayments = false) {
            this.loading = true;
            try {
                let response = await fetch('{{ route('api.point-of-sale.mark-as-paid') }}', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'Accept': 'application/json',
                    },
                    body: JSON.stringify({
                        posIdentifier: this.posIdentifier,
                        order: this.order,
                        paymentMethod: this.chosenPaymentMethod,
                        userId: this.userId,
                        cashPaymentAmount: this.cashPaymentAmount,
                        hasMultiplePayments: hasMultiplePayments,
                    })
                });

                let data = await response.json();
                this.focus();

                if (!response.ok) {
                    this.loading = false;
                    return $wire.dispatch('notify', {
                        type: 'danger',
                        message: data.message,
                    })
                }

                if (data.startPinTerminalPayment) {
                    this.startPinTerminalPayment(hasMultiplePayments);
                } else {
                    this.toggle('paymentPopup')
                    this.products = [];
                    this.discountCode = '';
                    this.cashPaymentAmount = null;
                    this.order = data.order;
                    this.orderPayments = data.orderPayments;
                    this.firstPaymentMethod = data.firstPaymentMethod;
                    this.toggle('orderConfirmationPopup')
                }

                this.loading = false;
            } catch (error) {
                this.loading = false;
                return $wire.dispatch('notify', {
                    type: 'danger',
                    message: 'De bestelling kon niet worden gemarkeerd als betaald'
                })
            }
        },

        checkPinTerminalPayment() {
            this.pinTerminalIntervalId = setInterval(() => {
                if (this.isPinTerminalPayment && this.pinTerminalStatus == 'pending') {
                    console.log('Checking pin terminal payment status...');
                    this.pollPinTerminalPayment();
                } else {
                    console.log('Stopping pin terminal payment status check.');
                    clearInterval(this.pinTerminalIntervalId); // Stop polling if condition changes
                }
            }, 1000);
        },

        async pollPinTerminalPayment() {

            try {
                console.log('Polling pin terminal payment status...');
                let response = await fetch('{{ route('api.point-of-sale.check-pin-terminal-payment') }}', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'Accept': 'application/json',
                    },
                    body: JSON.stringify({
                        posIdentifier: this.posIdentifier,
                        order: this.order,
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

                this.pinTerminalStatus = data.pinTerminalStatus;
                this.pinTerminalError = data.pinTerminalError;
                this.pinTerminalErrorMessage = data.pinTerminalErrorMessage;

                if (this.pinTerminalStatus == 'paid' && !this.pinTerminalStatusHandled) {
                    console.log('Pin terminal payment completed successfully.');
                    this.disable('paymentPopup')
                    this.products = [];
                    this.discountCode = '';
                    this.cashPaymentAmount = null;
                    this.order = data.order;
                    this.orderPayments = data.orderPayments;
                    this.firstPaymentMethod = data.firstPaymentMethod;
                    this.enable('pinTerminalStatusHandled');
                    this.enable('orderConfirmationPopup')
                }

            } catch (error) {
                console.log(error);
                return $wire.dispatch('notify', {
                    type: 'danger',
                    message: 'De pin betaling kon niet worden gecontroleerd'
                })
            }
        },

        async updateSearchQueryInputmode() {
            this.searchQueryInputmode = !this.searchQueryInputmode;
            try {
                let response = await fetch('{{ route('api.point-of-sale.update-search-query-input-mode') }}', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'Accept': 'application/json',
                    },
                    body: JSON.stringify({
                        searchQueryInputmode: this.searchQueryInputmode,
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

            } catch (error) {
                return $wire.dispatch('notify', {
                    type: 'danger',
                    message: 'De input query status kon niet worden geupdate'
                })
            }
        },

        async resetPOS() {
            this.lastOrder = this.order;
            this.order = null;
            this.paymentPopup = false;
            this.orderUrl = null;
            this.postPay = false;
            this.orderConfirmationPopup = false;
            this.customerUserId = null;
            this.firstName = null;
            this.lastName = null;
            this.email = null;
            this.phoneNumber = null;
            this.street = null;
            this.houseNr = null;
            this.zipCode = null;
            this.city = null;
            this.country = null;
            this.company = null;
            this.btwId = null;
            this.invoiceStreet = null;
            this.invoiceHouseNr = null;
            this.invoiceZipCode = null;
            this.invoiceCity = null;
            this.invoiceCountry = null;
            this.note = null;
            this.initialize();
        },

        async getSearchedProducts() {
            if (this.searchProductQuery.length < 3) {
                this.searchedProducts = [];
            }
            this.searchedProducts = this.allProducts
                .filter(product => product.search.toLowerCase().includes(this.searchProductQuery.toLowerCase()))
                .slice(0, 100);

            try {
                let response = await fetch('{{ route('api.point-of-sale.update-product-info') }}', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'Accept': 'application/json',
                    },
                    body: JSON.stringify({
                        products: this.searchedProducts,
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

                this.searchedProducts = data.products;

            } catch (error) {
                return $wire.dispatch('notify', {
                    type: 'danger',
                    message: 'Kan de voorraad niet bijwerken'
                })
            }
        },

        async retrieveOrders() {
            try {
                let response = await fetch('{{ route('api.point-of-sale.retrieve-orders') }}', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'Accept': 'application/json',
                    },
                    body: JSON.stringify({
                        userId: this.userId,
                        searchOrderQuery: this.searchOrderQuery,
                    })
                });

                let data = await response.json();

                if (!response.ok) {
                    this.searchOrderQuery = '';
                    return $wire.dispatch('notify', {
                        type: 'danger',
                        message: data.message,
                    })
                }

                if (data.order) {
                    this.orders = [
                        {
                            'date': 'gevonden resultaat',
                            'orders': [
                                data.order
                            ]
                        }
                    ];
                    this.selectedOrder = data.order;
                } else {
                    this.orders = data.orders;
                    if (!this.selectedOrder && data.firstOrder) {
                        this.selectedOrder = data.firstOrder;
                    }
                }

            } catch (error) {
                return $wire.dispatch('notify', {
                    type: 'danger',
                    message: 'Kan de bestellingen niet ophalen'
                })
            }
        },

        async submitCancelOrderForm() {
            try {
                let response = await fetch('{{ route('api.point-of-sale.cancel-order') }}', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'Accept': 'application/json',
                    },
                    body: JSON.stringify({
                        userId: this.userId,
                        order: this.selectedOrder,
                    })
                });

                let data = await response.json();

                if (!response.ok) {
                    return $wire.dispatch('notify', {
                        type: 'danger',
                        message: data.message,
                    })
                }

                this.cancelOrderPopup = false;

            } catch (error) {
                return $wire.dispatch('notify', {
                    type: 'danger',
                    message: 'Kan de bestellingen niet ophalen'
                })
            }
        },

        async refreshProducts() {
            this.loading = true;
            this.getAllProducts(true);
            this.loading = false;

            return $wire.dispatch('notify', {
                type: 'success',
                message: 'De producten zijn opgehaald',
            })
        },

        toggleFullscreen() {
            if (!document.fullscreenElement) {
                if (document.documentElement.requestFullscreen) {
                    document.documentElement.requestFullscreen();
                } else if (document.documentElement.mozRequestFullScreen) { // Firefox
                    document.documentElement.mozRequestFullScreen();
                } else if (document.documentElement.webkitRequestFullscreen) { // Chrome, Safari and Opera
                    document.documentElement.webkitRequestFullscreen();
                } else if (document.documentElement.msRequestFullscreen) { // IE/Edge
                    document.documentElement.msRequestFullscreen();
                }
                this.isFullscreen = true;
            } else {
                if (document.exitFullscreen) {
                    document.exitFullscreen();
                } else if (document.mozCancelFullScreen) { // Firefox
                    document.mozCancelFullScreen();
                } else if (document.webkitExitFullscreen) { // Chrome, Safari and Opera
                    document.webkitExitFullscreen();
                } else if (document.msExitFullscreen) { // IE/Edge
                    document.msExitFullscreen();
                }
                this.isFullscreen = false;
            }
        },

        showOrdersPopup() {
            this.loading = true;
            if (this.ordersPopup) {
                this.ordersPopup = false;
                this.focus();
            } else {
                this.ordersPopup = true;
                this.cancelOrderPopup = false;
                this.retrieveOrders();
                this.focusSearchOrder();
            }
            this.loading = false;
        },

        selectOrder(order) {
            this.selectedOrder = order;
        },

        changeRefundQuantity(quantityModel, quantity, maxQuantity) {
            quantityModel.refundQuantity = quantity;
            if (quantityModel.refundQuantity > maxQuantity) {
                quantityModel.refundQuantity = maxQuantity;
            }
            if (quantityModel.refundQuantity < 0) {
                quantityModel.refundQuantity = 0;
            }
        },

        focus() {
            document.getElementById("search-product-query").focus();
        },

        focusSearchOrder() {
            document.getElementById("search-order-query").focus();
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
                this.discountCode = variable[0].discountCode;
                this.createDiscountPopup = false;
                this.focus();
                this.retrieveCart();
            })

            $wire.on('saveCustomerData', (variable) => {
                this.focus();
                this.saveCustomerData();
            })

            $wire.on('productChanged', (variable) => {
                this.focus();
                this.toggle('changeProductPricePopup');
                this.retrieveCart();
            })

            this.initialize();
            this.getAllProducts();

            let searchTimeout = null;

            $watch('searchProductQuery', (value) => {
                clearTimeout(searchTimeout);
                this.loadingSearchedProducts = true;

                searchTimeout = setTimeout(() => {
                    if (value.length > 2) {
                        this.getSearchedProducts();
                    } else {
                        this.searchedProducts = [];
                    }

                    this.loadingSearchedProducts = false;
                }, 300);
            });

            $watch('searchOrderQuery', (value, oldValue) => {
                this.retrieveOrders();
            });
        }
    }));
</script>
@endscript
</div>
