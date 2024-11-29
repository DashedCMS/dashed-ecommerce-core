<div class="relative z-40" role="dialog" aria-modal="true" x-data="{ showCartPopup: @entangle('showCartPopup') }"
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
        <div class="relative ml-auto flex h-full w-full max-w-md flex-col overflow-y-auto bg-white shadow-xl"
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
                            <div class="w-full bg-gray-200 rounded-full h-4">
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

            <div class="grow overflow-y-auto shadow-xl bg-gray-100">
                @foreach($this->cartItems as $item)
                    <li class="flex px-4 py-6 sm:px-6">
                        <div class="flex-shrink max-w-[150px]">
                            @if($item->model->firstImage)
                                <x-drift::image
                                    class="aspect-square rounded-md object-cover object-center w-full"
                                    config="dashed"
                                    :path="$item->model->firstImage"
                                    :alt=" $item->model->name"
                                    :manipulations="[
                                                    'widen' => 200,
                                                ]"
                                />
                            @endif
                        </div>

                        <div class="ml-4 flex flex-1 flex-col justify-between sm:ml-6">
                            <div class="relative pr-9 sm:grid sm:gap-x-6 sm:pr-0">
                                <div>
                                    <div class="flex justify-between">
                                        <h3 class="text-sm pr-6">
                                            <a href="{{ $item->model->getUrl() }}"
                                               class="font-bold text-primary-500 hover:text-primary-800 trans">
                                                {{ $item->model->name }}
                                            </a>
                                        </h3>
                                    </div>
                                    <div class="mt-1 flex text-sm">
                                        @foreach($item->options as $option)
                                            @if($loop->first)
                                                <p class="">{{$option['name'] . ':'}}{{$option['value']}}</p>
                                            @else
                                                <p class="ml-4 border-l border-gray-200 pl-4">{{$option['name'] . ':'}}{{$option['value']}}</p>
                                            @endif
                                        @endforeach
                                    </div>
                                    <p class="mt-1 text-sm font-bold ">{{CurrencyHelper::formatPrice($item->price * $item->qty)}}</p>
                                </div>

                                <div class="mt-4">
                                    <div
                                        class="inline-flex items-center p-1 transition rounded bg-white focus-within:bg-white focus-within:ring-2 focus-within:ring-primary-500">
                                        <button
                                            wire:click="changeQuantity('{{ $item->rowId }}', '{{ $item->qty - 1 }}')"
                                            class="grid w-6 h-6 bg-primary-500 rounded shadow-xl place-items-center text-white hover:bg-primary-500 hover:text-white shadow-primary-500/10 ring-1 ring-black/5"
                                        >
                                            <x-lucide-minus class="w-4 h-4"/>
                                        </button>

                                        <input
                                            class="w-[4ch] px-0 py-0.5 focus:ring-0 text-center bg-transparent border-none text-primary-500 font-bold"
                                            value="{{$item->qty}}"
                                            disabled
                                            min="0" max="{{$item->model->stock()}}">

                                        <button
                                            wire:click="changeQuantity('{{ $item->rowId }}', '{{ $item->qty + 1 }}')"
                                            class="grid w-6 h-6 bg-primary-500 rounded shadow-xl place-items-center text-white hover:bg-primary-500 hover:text-white shadow-primary-500/10 ring-1 ring-black/5"
                                        >
                                            <x-lucide-plus class="w-4 h-4"/>
                                        </button>

                                        <div class="absolute right-0 top-0">
                                            <button
                                                wire:click="changeQuantity('{{ $item->rowId }}', '0')"
                                                type="button"
                                                class="-m-2 inline-flex p-2 text-white hover:text-red-500 rounded-full bg-primary-700 trans">
                                                <span class="sr-only">{{ Translation::get('remove', 'cart', 'Verwijder') }}</span>
                                                <x-lucide-trash class="h-5 w-5"/>
                                            </button>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <div class="mt-4 flex space-x-2 text-sm text-gray-700">
                                <x-stock-text :product="$item->model" />
                            </div>
                        </div>
                    </li>
                @endforeach
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
                        <a href="{{ \Dashed\DashedEcommerceCore\Classes\ShoppingCart::getCartUrl() }}"
                           class="w-full button button--primary font-bold text-sm">
                            <span>{{ Translation::get('to-the-checkout', 'cart-popup', 'Afrekenen') }}</span>
                        </a>
                    </div>
                    <a href="{{ \Dashed\DashedEcommerceCore\Classes\ShoppingCart::getCheckoutUrl() }}"
                       class="w-fit button button--white-outline border-primary-500 text-primary-500 font-bold text-sm">
                        <span>{{ Translation::get('to-the-cart', 'cart-popup', 'Bekijk winkelwagen') }}</span>
                    </a>
                    <a @click="showCartPopup = false"
                       class="w-fit button button--white-outline border-primary-500 text-primary-500 font-bold text-sm">
                        <span>{{ Translation::get('continue-shopping', 'cart-popup', 'Verder winkelen') }}</span>
                    </a>
                </div>
            </div>
        </div>
    </div>
</div>
