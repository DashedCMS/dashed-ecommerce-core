<div>
    @if(count($this->cartItems))
        <x-container>
            <div class="py-16">
                <h1 class="text-3xl font-bold tracking-tight text-gray-900 sm:text-4xl">{{ Translation::get('shopping-cart', 'cart', 'Winkelwagen') }}</h1>
                <div class="mt-12 lg:grid lg:grid-cols-12 lg:items-start lg:gap-x-12 xl:gap-x-16">
                    <section aria-labelledby="cart-heading" class="lg:col-span-7">
                        <h2 id="cart-heading"
                            class="sr-only">{{ Translation::get('items-in-your-shopping-cart', 'cart', 'Items in je winkelwagen') }}</h2>
                        <ul role="list" class="divide-y divide-gray-200 border-b border-t border-gray-200">
                            @foreach($this->cartItems as $item)
                                <li class="flex py-6 sm:py-10">
                                    <div class="flex-shrink-0">
                                        @if($item->model->firstImage)
                                            <a href="{{ $item->model->getUrl() }}">
                                                <x-drift::image
                                                    class="h-24 w-24 rounded-md object-cover object-center sm:h-48 sm:w-48"
                                                    config="dashed"
                                                    :path="$item->model->firstImage"
                                                    :alt=" $item->model->name"
                                                    :manipulations="[
                                                    'widen' => 400,
                                                ]"
                                                />
                                            </a>
                                        @endif
                                    </div>

                                    <div class="ml-4 flex flex-1 flex-col justify-between sm:ml-6">
                                        <div class="relative pr-9 sm:grid sm:grid-cols-2 sm:gap-x-6 sm:pr-0">
                                            <div>
                                                <div class="flex justify-between">
                                                    <h3 class="text-sm">
                                                        <a href="{{ $item->model->getUrl() }}"
                                                           class="font-bold text-gray-700 hover:text-gray-800">
                                                            {{ $item->model->name }}
                                                        </a>
                                                    </h3>
                                                </div>
                                                <div class="mt-1 flex text-sm">
                                                    @foreach($item->options as $option)
                                                        @if($loop->first)
                                                            <p class="text-gray-500">{{$option['name'] . ':'}}{{$option['value']}}</p>
                                                        @else
                                                            <p class="ml-4 border-l border-gray-200 pl-4 text-gray-500">{{$option['name'] . ':'}}{{$option['value']}}</p>
                                                        @endif
                                                    @endforeach
                                                </div>
                                                <p class="mt-1 text-sm font-bold text-gray-900">{{CurrencyHelper::formatPrice($item->model->currentPrice * $item->qty)}}</p>
                                            </div>

                                            <div class="mt-4 sm:mt-0 sm:pr-9">
                                                <div
                                                    class="inline-flex items-center p-1 transition rounded bg-black/5 focus-within:bg-white focus-within:ring-2 focus-within:ring-primary-500">
                                                    <button
                                                        wire:click="changeQuantity('{{ $item->rowId }}', '{{ $item->qty - 1 }}')"
                                                        class="grid w-6 h-6 bg-white rounded shadow-xl place-items-center text-primary-500 hover:bg-primary-500 hover:text-white shadow-primary-500/10 ring-1 ring-black/5 trans"
                                                    >
                                                        <x-lucide-minus class="w-4 h-4"/>
                                                    </button>

                                                    <input
                                                        class="w-[4ch] px-0 py-0.5 focus:ring-0 text-center bg-transparent border-none"
                                                        value="{{$item->qty}}"
                                                        disabled
                                                        min="0" max="{{$item->model->stock()}}">

                                                    <button
                                                        wire:click="changeQuantity('{{ $item->rowId }}', '{{ $item->qty + 1 }}')"
                                                        class="grid w-6 h-6 bg-white rounded shadow-xl place-items-center text-primary-500 hover:bg-primary-500 hover:text-white shadow-primary-500/10 ring-1 ring-black/5 trans"
                                                    >
                                                        <x-lucide-plus class="w-4 h-4"/>
                                                    </button>

                                                    <div class="absolute right-0 top-0">
                                                        <button
                                                            wire:click="changeQuantity('{{ $item->rowId }}', '0')"
                                                            type="button"
                                                            class="-m-2 inline-flex p-2 text-white hover:text-red-500 rounded-full bg-primary-500 trans">
                                                            <span class="sr-only">{{ Translation::get('remove', 'cart', 'Verwijder') }}</span>
                                                            <x-lucide-trash class="h-5 w-5"/>
                                                        </button>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>

                                        <div class="mt-4 flex space-x-2 text-sm text-gray-700">
                                            @if($item->model->inStock())
                                                @if($item->model->hasDirectSellableStock())
                                                    @if($item->model->stock() > 10)
                                                        <p class="text-md tracking-wider text-white flex items-center font-bold"><span
                                                                class="mr-1"><svg class="w-6 h-6" fill="none"
                                                                                  stroke="currentColor"
                                                                                  viewBox="0 0 24 24"
                                                                                  xmlns="http://www.w3.org/2000/svg"><path
                                                                        stroke-linecap="round" stroke-linejoin="round"
                                                                        stroke-width="2"
                                                                        d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
                                            </span>
                                                            {{Translation::get('product-in-stock', 'product', 'Op voorraad')}}
                                                        </p>
                                                    @else
                                                        <p class="text-md tracking-wider text-white flex items-center font-bold"><span
                                                                class="mr-1"><svg class="w-6 h-6" fill="none"
                                                                                  stroke="currentColor"
                                                                                  viewBox="0 0 24 24"
                                                                                  xmlns="http://www.w3.org/2000/svg"><path
                                                                        stroke-linecap="round" stroke-linejoin="round"
                                                                        stroke-width="2"
                                                                        d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
                                            </span>
                                                            {{Translation::get('product-in-stock-specific', 'product', 'Nog :count: op voorraad', 'text', [
                                    'count' => $item->model->stock()
                                    ])}}
                                                        </p>
                                                    @endif
                                                @else
                                                    @if($item->model->expectedDeliveryInDays())
                                                        <p class="font-bold italic text-md flex items-center gap-1 text-primary-500">
                                                            <svg xmlns="http://www.w3.org/2000/svg" fill="none"
                                                                 viewBox="0 0 24 24" stroke-width="1.5"
                                                                 stroke="currentColor" class="size-6">
                                                                <path stroke-linecap="round" stroke-linejoin="round"
                                                                      d="M8.25 18.75a1.5 1.5 0 0 1-3 0m3 0a1.5 1.5 0 0 0-3 0m3 0h6m-9 0H3.375a1.125 1.125 0 0 1-1.125-1.125V14.25m17.25 4.5a1.5 1.5 0 0 1-3 0m3 0a1.5 1.5 0 0 0-3 0m3 0h1.125c.621 0 1.129-.504 1.09-1.124a17.902 17.902 0 0 0-3.213-9.193 2.056 2.056 0 0 0-1.58-.86H14.25M16.5 18.75h-2.25m0-11.177v-.958c0-.568-.422-1.048-.987-1.106a48.554 48.554 0 0 0-10.026 0 1.106 1.106 0 0 0-.987 1.106v7.635m12-6.677v6.677m0 4.5v-4.5m0 0h-12"/>
                                                            </svg>
                                                            <span>{{ Translation::get('pre-order-product-static-delivery-time', 'product', 'Levering duurt circa :days: dagen', 'text', [
                                                'days' => $item->model->expectedDeliveryInDays()
                                            ]) }}</span>
                                                        </p>
                                                    @else
                                                        <p class="font-bold italic text-md flex items-center gap-1 text-primary-500">
                                                            <svg xmlns="http://www.w3.org/2000/svg" fill="none"
                                                                 viewBox="0 0 24 24" stroke-width="1.5"
                                                                 stroke="currentColor" class="size-6">
                                                                <path stroke-linecap="round" stroke-linejoin="round"
                                                                      d="M8.25 18.75a1.5 1.5 0 0 1-3 0m3 0a1.5 1.5 0 0 0-3 0m3 0h6m-9 0H3.375a1.125 1.125 0 0 1-1.125-1.125V14.25m17.25 4.5a1.5 1.5 0 0 1-3 0m3 0a1.5 1.5 0 0 0-3 0m3 0h1.125c.621 0 1.129-.504 1.09-1.124a17.902 17.902 0 0 0-3.213-9.193 2.056 2.056 0 0 0-1.58-.86H14.25M16.5 18.75h-2.25m0-11.177v-.958c0-.568-.422-1.048-.987-1.106a48.554 48.554 0 0 0-10.026 0 1.106 1.106 0 0 0-.987 1.106v7.635m12-6.677v6.677m0 4.5v-4.5m0 0h-12"/>
                                                            </svg>
                                                            <span>
                                                {{ Translation::get('pre-order-product-now', 'product', 'Pre order nu, levering op :date:', 'text', [
                                                'date' => $item->model->expectedInStockDate()
                                            ]) }}
                                            </span>
                                                        </p>
                                                    @endif
                                                @endif
                                            @else
                                                <p class="font-bold text-red-500 text-md flex items-center gap-2">
                                                    <x-lucide-x-circle class="h-5 w-5"/>
                                                    {{ Translation::get('not-in-stock', 'product', 'Niet op voorraad') }}
                                                </p>
                                            @endif
                                        </div>
                                    </div>
                                </li>
                            @endforeach
                        </ul>
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
                               class="button button--primary-light w-full">
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
