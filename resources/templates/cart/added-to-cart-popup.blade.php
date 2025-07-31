<div class="relative z-[100]" role="dialog" aria-modal="true" x-data="{ showCartPopup: false }"
     x-init="
    window.addEventListener('productAddedToCart', (event) => {
        showCartPopup = true;
    });
" x-show="showCartPopup" x-cloak
     @keydown.window.escape="showCartPopup = false">
    <div class="fixed inset-0 bg-black bg-opacity-25 pointer-events-none" aria-hidden="true" x-show="showCartPopup"
         @click="showCartPopup = false"
         x-transition:enter="transition-opacity ease-linear duration-500"
         x-transition:enter-start="opacity-0"
         x-transition:enter-end="opacity-100"
         x-transition:leave="transition-opacity ease-linear duration-500"
         x-transition:leave-start="opacity-100"
         x-transition:leave-end="opacity-0"></div>

    <div class="fixed inset-0 z-40 flex"
         x-show="showCartPopup">
        <div class="fixed inset-0 bg-gray-500/75 transition-opacity" aria-hidden="true"
             x-show="showCartPopup"></div>

        <div class="fixed inset-0 z-10 w-screen overflow-y-auto"
             x-show="showCartPopup"
             x-transition:enter="transition ease-in-out duration-500 transform"
             x-transition:enter-start="translate-x-full"
             x-transition:enter-end="translate-x-0"
             x-transition:leave="transition ease-in-out duration-500 transform"
             x-transition:leave-start="translate-x-0"
             x-transition:leave-end="translate-x-full">
            <div class="flex min-h-full items-end justify-center p-4 text-center sm:items-center sm:p-0">
                <div @click.away="showCartPopup = false"
                     class="relative transform overflow-hidden rounded-lg bg-white px-4 pb-4 pt-5 text-left shadow-xl transition-all sm:my-8 sm:w-full sm:max-w-3xl sm:p-6">
                    <div @click="showCartPopup = false"
                         class="cursor-pointer absolute top-2 right-2 rounded-full p-1 bg-primary-500 text-white trans hover:bg-primary-800 group">
                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5"
                             stroke="currentColor" class="size-6 group-hover:rotate-90 transform trans">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M6 18 18 6M6 6l12 12"/>
                        </svg>
                    </div>
                    <div>
                        <div class="mx-auto flex size-12 items-center justify-center rounded-full bg-green-100">
                            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5"
                                 stroke="currentColor" class="size-6">
                                <path stroke-linecap="round" stroke-linejoin="round"
                                      d="M15.75 10.5V6a3.75 3.75 0 1 0-7.5 0v4.5m11.356-1.993 1.263 12c.07.665-.45 1.243-1.119 1.243H4.25a1.125 1.125 0 0 1-1.12-1.243l1.264-12A1.125 1.125 0 0 1 5.513 7.5h12.974c.576 0 1.059.435 1.119 1.007ZM8.625 10.5a.375.375 0 1 1-.75 0 .375.375 0 0 1 .75 0Zm7.5 0a.375.375 0 1 1-.75 0 .375.375 0 0 1 .75 0Z"/>
                            </svg>

                        </div>
                        @if(session('lastAddedProductInCart'))
                            <div class="mt-6 text-center">
                                @php($product = session('lastAddedProductInCart'))
                                <h3 class="font-semibold text-gray-900 text-xl md:text-3xl" id="modal-title">
                                    {{ Translation::get('added-to-cart', 'cart-popup', 'Toegevoegd aan winkelmandje') }}
                                </h3>
                                <div class="mt-2 flex flex-wrap justify-center items-center gap-4">
                                    @if($product->firstImage)
                                        <x-drift::image
                                            class="w-32"
                                            config="dashed"
                                            :path="$product->firstImage"
                                            :alt="$product->name"
                                            :manipulations="[
                            'widen' => 1000,
                        ]"
                                        />
                                    @endif
                                    <p class="text-base text-gray-500">
                                        {{ $product->name }}
                                    </p>
                                </div>
                            </div>
                            <div class="grid gap-4 md:grid-cols-3 mt-4">
                                <a href="{{ \Dashed\DashedEcommerceCore\Classes\ShoppingCart::getCheckoutUrl() }}"
                                   class="w-full button button-primary font-bold text-sm">
                                    <span>{{ Translation::get('to-the-checkout', 'cart-popup', 'Afrekenen') }}</span>
                                </a>
                                <a href="{{ \Dashed\DashedEcommerceCore\Classes\ShoppingCart::getCartUrl() }}"
                                   class="w-full button button-primary-solid border-primary-500 text-primary-500 font-bold text-sm">
                                    <span>{{ Translation::get('to-the-cart', 'cart-popup', 'Bekijk winkelwagen') }}</span>
                                </a>
                                <a @click="showCartPopup = false"
                                   href="#"
                                   class="w-full button button-primary font-bold text-sm cursor-pointer">
                                    <span>{{ Translation::get('continue-shopping', 'cart-popup', 'Verder winkelen') }}</span>
                                </a>
                            </div>
                        @endif
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
