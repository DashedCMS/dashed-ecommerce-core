<div class="relative z-[100]" role="dialog" aria-modal="true" x-data="{ showCartPopup: @entangle('showCartPopup') }"
     x-init="
    window.addEventListener('productAddedToCart', (event) => {
        showCartPopup = true;
    });

    window.addEventListener('openCartPopup', (event) => {
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
         x-show="showCartPopup"
         x-transition:enter="transition ease-in-out duration-500 transform"
         x-transition:enter-start="translate-x-full"
         x-transition:enter-end="translate-x-0"
         x-transition:leave="transition ease-in-out duration-500 transform"
         x-transition:leave-start="translate-x-0"
         x-transition:leave-end="translate-x-full">
        <div class="relative ml-auto flex h-full w-full max-w-[90%] sm:max-w-md flex-col overflow-y-auto bg-white shadow-xl"
             @click.away="showCartPopup = false">
            <div class="flex items-center justify-between px-4 bg-primary-500 text-white">
                <div class="mx-auto py-4 grid gap-2">
                    <div class="flex flex-wrap gap-2 items-center justify-center">
                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5"
                             stroke="currentColor" class="size-8">
                            <path stroke-linecap="round" stroke-linejoin="round"
                                  d="M15.75 10.5V6a3.75 3.75 0 1 0-7.5 0v4.5m11.356-1.993 1.263 12c.07.665-.45 1.243-1.119 1.243H4.25a1.125 1.125 0 0 1-1.12-1.243l1.264-12A1.125 1.125 0 0 1 5.513 7.5h12.974c.576 0 1.059.435 1.119 1.007ZM8.625 10.5a.375.375 0 1 1-.75 0 .375.375 0 0 1 .75 0Zm7.5 0a.375.375 0 1 1-.75 0 .375.375 0 0 1 .75 0Z"/>
                        </svg>

                        <h2 class="text-lg font-medium">{{ Translation::get('cart', 'cart-popup', 'Winkelwagen') }}</h2>
                    </div>

                    <div class="grid gap-2">
                        @if($cartTotal < $freeShippingThreshold)
                            <p class="font-medium text-center">
                            {!! Translation::get('almost-free-shipping-info', 'cart-popup', 'Bestel nog voor <b>:amountLeft:</b> voor <b>gratis</b> verzending', 'editor', [
                                'amountLeft' => CurrencyHelper::formatPrice($freeShippingThreshold - $cartTotal)
                            ]) !!}
                        @else
                            <p class="font-bold text-center">{{ Translation::get('free-shipping-info', 'cart-popup', 'Je bestelling wordt gratis verzonden') }}</p>
                        @endif

                        <div x-data="{ progress: {{ $freeShippingPercentage }} }">
                            <div class="w-full bg-gray-200 rounded-full h-4 border-2 border-gray-200">
                                <div class="bg-primary-600 h-full rounded-full" :style="`width: ${progress}%;`"></div>
                            </div>
                        </div>
                    </div>
                </div>
                <button type="button"
                        x-on:click="showCartPopup = !showCartPopup"
                        class="absolute top-2 right-2 flex h-10 w-10 items-center justify-center">
                    <span class="sr-only">Close menu</span>
                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5"
                         stroke="currentColor" class="size-8">
                        <path stroke-linecap="round" stroke-linejoin="round"
                              d="m9.75 9.75 4.5 4.5m0-4.5-4.5 4.5M21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z"/>
                    </svg>
                </button>
            </div>

            <div class="grow overflow-y-auto shadow-xl bg-white">
                @if(count($this->cartItems))
                    <div class="p-6">
                        <x-cart.cart-items :items="$this->cartItems"/>
                    </div>
                @else
                    <div class="p-6 text-center">
                        <p class="text-gray-700">{{ Translation::get('no-items-in-cart', 'cart-popup', 'Er zitten geen producten in je winkelwagen, ga snel verder met winkelen') }}</p>
                    </div>
                @endif
            </div>
            <div class="p-6 grid gap-4">
                <div class="space-y-2 text-gray-700">
                    <div class="flex items-center justify-between">
                        <dt class="text-sm">{{Translation::get('subtotal', 'cart-popup', 'Subtotaal')}}</dt>
                        <dd class="text-sm font-medium">{{ CurrencyHelper::formatPrice($cartSubtotal) }}</dd>
                    </div>
                    <div class="flex items-center justify-between">
                        <dt class="text-sm">{{Translation::get('btw', 'cart-popup', 'BTW')}}</dt>
                        <dd class="text-sm font-medium">{{ CurrencyHelper::formatPrice($cartTax) }}</dd>
                    </div>
                    <div class="flex items-center justify-between border-t-2 border-gray-200 pt-2">
                        <dt class="text-base font-medium">{{Translation::get('total', 'cart-popup', 'Totaal')}}</dt>
                        <dd class="text-base font-medium">{{ CurrencyHelper::formatPrice($cartTotal) }}</dd>
                    </div>
                </div>
                <div class="grid gap-4 md:grid-cols-2">
                    <div class="md:col-span-2">
                        <a href="{{ \Dashed\DashedEcommerceCore\Classes\ShoppingCart::getCheckoutUrl() }}"
                           class="w-full button button--primary font-bold text-sm">
                            <span>{{ Translation::get('to-the-checkout', 'cart-popup', 'Afrekenen') }}</span>
                        </a>
                    </div>
                    <a href="{{ \Dashed\DashedEcommerceCore\Classes\ShoppingCart::getCartUrl() }}"
                       class="w-full md:w-fit button button--white-outline border-primary-500 text-primary-500 font-bold text-sm">
                        <span>{{ Translation::get('to-the-cart', 'cart-popup', 'Bekijk winkelwagen') }}</span>
                    </a>
                    <a @click="showCartPopup = false"
                       href="#"
                       class="w-full md:w-fit button button--white-outline border-primary-500 text-primary-500 font-bold text-sm cursor-pointer">
                        <span>{{ Translation::get('continue-shopping', 'cart-popup', 'Verder winkelen') }}</span>
                    </a>
                </div>
            </div>
        </div>
    </div>
</div>
