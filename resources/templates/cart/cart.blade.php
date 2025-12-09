<div>
    @if(count($this->cartItems))
        <div class="relative overflow-hidden">
            <div class="absolute right-0 top-0 hidden h-full w-1/2 bg-linear-to-tr from-primary-700 to-primary-300 lg:block"
                 aria-hidden="true"></div>
            <x-container>
                <div class="py-16">
                    <h1 class="text-3xl font-bold tracking-tight text-gray-900 sm:text-4xl">{{ Translation::get('shopping-cart', 'cart', 'Winkelwagen') }}</h1>
                    <div class="mt-12 lg:grid lg:grid-cols-12 lg:items-start lg:gap-x-12 xl:gap-x-16 ">
                        <section aria-labelledby="cart-heading"
                                 class="lg:col-span-7 border-black/5 bg-white/10 backdrop-blur-2xl rounded-xl">
                            <h2 id="cart-heading"
                                class="sr-only">{{ Translation::get('items-in-your-shopping-cart', 'cart', 'Items in je winkelwagen') }}</h2>
                            <div class="px-4">
                                <x-cart.cart-items :items="$this->cartItems"/>
                            </div>
                        </section>

                        <section aria-labelledby="summary-heading"
                                 class="mt-16 rounded-lg bg-primary-500 lg:bg-transparent text-white px-4 py-6 sm:p-6 lg:col-span-5 lg:mt-0 lg:p-8 sticky top-28">
                            <h2 id="summary-heading"
                                class="text-xl font-bold">{{Translation::get('overview', 'cart', 'Overzicht')}}</h2>

                            <div class="mt-2">
                                <form wire:submit.prevent="applyDiscountCode"
                                      class="flex flex-col lg:flex-row justify-between gap-2">
                                    <input placeholder="{{Translation::get('add-discount-code', 'cart', 'Voeg kortingscode toe')}}"
                                           class="custom-form-input h-14"
                                           wire:model.lazy="discountCode">
                                    <button type="submit"
                                            class="w-full button button--primary-light"
                                            aria-label="Apply button">{{Translation::get('add-discount', 'cart', 'Toevoegen')}}</button>
                                </form>
                            </div>

                            <dl class="mt-6 space-y-4">
                                <div class="flex items-center justify-between">
                                    <dt class="text-sm">{{Translation::get('subtotal', 'cart', 'Subtotaal')}}</dt>
                                    <dd class="text-sm font-bold">{{ $subtotal }}</dd>
                                </div>
                                @if($discount)
                                    <div class="flex items-center justify-between border-t border-gray-200 pt-4">
                                        <dt class="text-sm">{{Translation::get('discount', 'cart', 'Korting')}}</dt>
                                        <dd class="text-sm font-bold">{{ $discount }}</dd>
                                    </div>
                                @endif
                                <div class="flex items-center justify-between border-t border-gray-200 pt-4">
                                    <dt class="text-sm">{{Translation::get('btw', 'cart', 'BTW')}}</dt>
                                    <dd class="text-sm font-bold">{{ $tax }}</dd>
                                </div>
                                <div class="flex items-center justify-between border-t border-gray-200 pt-4">
                                    <dt class="text-sm">{{Translation::get('total', 'cart', 'Totaal')}}</dt>
                                    <dd class="text-sm font-bold">{{ $total }}</dd>
                                </div>
                            </dl>

                            <div class="mt-6">
                                <a href="{{ ShoppingCart::getCheckoutUrl() }}"
                                   class="button button--white w-full">
                                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24"
                                         stroke-width="1.5" stroke="currentColor" class="size-6">
                                        <path stroke-linecap="round" stroke-linejoin="round"
                                              d="M2.25 3h1.386c.51 0 .955.343 1.087.835l.383 1.437M7.5 14.25a3 3 0 0 0-3 3h15.75m-12.75-3h11.218c1.121-2.3 2.1-4.684 2.924-7.138a60.114 60.114 0 0 0-16.536-1.84M7.5 14.25 5.106 5.272M6 20.25a.75.75 0 1 1-1.5 0 .75.75 0 0 1 1.5 0Zm12.75 0a.75.75 0 1 1-1.5 0 .75.75 0 0 1 1.5 0Z"/>
                                    </svg>

                                    <span>{{ Translation::get('checkout', 'cart', 'Naar de checkout') }}</span>
                                </a>
                            </div>
                        </section>
                    </div>
                </div>
            </x-container>
        </div>

        <x-dashed-core::global-blocks name="cart-page"/>

        <x-blocks.few-products :data="[
    'title' => Translation::get('suggested-products', 'cart', 'Misschien vind je dit ook leuk'),
    'backgroundColor' => 'bg-primary-100'
]" :products="$suggestedProducts"/>
    @else
        <x-blocks.header :data="[
                'title' => Translation::get('no-items-in-cart', 'cart', 'Geen items in je winkelwagen!'),
                'subtitle' => Translation::get('keep-shopping', 'cart', 'Verder shoppen!'),
                'image' => Translation::get('image', 'cart', '', 'image'),
            ]"></x-blocks.header>
    @endif
</div>
