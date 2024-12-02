<div>
    @if(count($this->cartItems))
        <x-container>
            <div class="py-16">
                <h1 class="text-3xl font-bold tracking-tight text-gray-900 sm:text-4xl">{{ Translation::get('shopping-cart', 'cart', 'Winkelwagen') }}</h1>
                <div class="mt-12 lg:grid lg:grid-cols-12 lg:items-start lg:gap-x-12 xl:gap-x-16">
                    <section aria-labelledby="cart-heading" class="lg:col-span-7">
                        <h2 id="cart-heading"
                            class="sr-only">{{ Translation::get('items-in-your-shopping-cart', 'cart', 'Items in je winkelwagen') }}</h2>
                        <x-cart.cart-items :items="$this->cartItems"/>
                    </section>

                    <section aria-labelledby="summary-heading"
                             class="mt-16 rounded-lg bg-primary-500 text-white px-4 py-6 sm:p-6 lg:col-span-5 lg:mt-0 lg:p-8">
                        <h2 id="summary-heading"
                            class="text-xl font-bold">{{Translation::get('overview', 'cart', 'Overzicht')}}</h2>

                        <div class="mt-2">
                            <form wire:submit.prevent="applyDiscountCode" class="md:flex justify-between gap-2">
                                <input placeholder="{{Translation::get('add-discount-code', 'cart', 'Voeg kortingscode toe')}}"
                                       class="form-input"
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
                               class="button button--primary w-full">
                                {{ Translation::get('checkout', 'cart', 'Naar de checkout') }}
                            </a>
                        </div>
                    </section>
                </div>
            </div>
        </x-container>
    @else
        <x-blocks.header :data="[
                'title' => Translation::get('no-items-in-cart', 'cart', 'Geen items in je winkelwagen!'),
                'subtitle' => Translation::get('keep-shopping', 'cart', 'Verder shoppen!'),
                'image' => Translation::get('image', 'cart', '', 'image'),
            ]"></x-blocks.header>
    @endif

</div>
