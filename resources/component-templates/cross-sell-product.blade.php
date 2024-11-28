<div class="bg-gray-100 p-4 relative group flex gap-4">
    @if($product->firstImage)
        <x-drift::image
            class="w-28 h-28 aspect-square object-cover object-center group-hover:scale-110 transform trans"
            config="dashed"
            :path="$product->firstImage"
            :alt="$product->name"
            :manipulations="[
                    'widen' => 200,
                ]"
        />
    @endif

    <header class="text-black font-medium uppercase flex flex-col text-left grow">
        <p>{{ $product->name }}</p>

        <div class="flex flex-wrap gap-2 md:gap-6 items-center">
            <div class="my-2 flex flex-wrap gap-2 items-center">
                @if($product->discountPrice)
                    <span class="line-through text-red-500 mr-2 font-normal">
                                    {{CurrencyHelper::formatPrice($product->discountPrice)}}
                                </span>
                @endif
                <p class="text-xl tracking-tight font-medium text-gray-900">{{ CurrencyHelper::formatPrice($product->currentPrice) }}</p>
            </div>

            <div class="flex items-center">
                @if($product->inStock())
                    @if($product->hasDirectSellableStock())
                        @if($product->stock() > 10)
                            <p class="text-md tracking-wider text-white flex items-center font-bold"><span
                                    class="mr-1"><svg class="w-6 h-6" fill="none" stroke="currentColor"
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
                                    class="mr-1"><svg class="w-6 h-6" fill="none" stroke="currentColor"
                                                      viewBox="0 0 24 24"
                                                      xmlns="http://www.w3.org/2000/svg"><path
                                            stroke-linecap="round" stroke-linejoin="round"
                                            stroke-width="2"
                                            d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
                                            </span>
                                {{Translation::get('product-in-stock-specific', 'product', 'Nog :count: op voorraad', 'text', [
        'count' => $product->stock()
        ])}}
                            </p>
                        @endif
                    @else
                        @if($product->expectedDeliveryInDays())
                            <p class="font-bold italic text-md flex items-center gap-1 text-primary-500">
                                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24"
                                     fill="currentColor" class="size-8 text-primary-500">
                                    <path fill-rule="evenodd"
                                          d="M12 2.25c-5.385 0-9.75 4.365-9.75 9.75s4.365 9.75 9.75 9.75 9.75-4.365 9.75-9.75S17.385 2.25 12 2.25ZM12.75 6a.75.75 0 0 0-1.5 0v6c0 .414.336.75.75.75h4.5a.75.75 0 0 0 0-1.5h-3.75V6Z"
                                          clip-rule="evenodd"/>
                                </svg>
                                <span>{{ Translation::get('pre-order-product-static-delivery-time', 'product', 'Levering duurt circa :days: dagen', 'text', [
                                                'days' => $product->expectedDeliveryInDays()
                                            ]) }}</span>
                            </p>
                        @else
                            <p class="font-bold italic text-md flex items-center gap-1 text-primary-500">
                                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24"
                                     fill="currentColor" class="size-8 text-primary-500">
                                    <path fill-rule="evenodd"
                                          d="M12 2.25c-5.385 0-9.75 4.365-9.75 9.75s4.365 9.75 9.75 9.75 9.75-4.365 9.75-9.75S17.385 2.25 12 2.25ZM12.75 6a.75.75 0 0 0-1.5 0v6c0 .414.336.75.75.75h4.5a.75.75 0 0 0 0-1.5h-3.75V6Z"
                                          clip-rule="evenodd"/>
                                </svg>
                                <span>
                                                {{ Translation::get('pre-order-product-now', 'product', 'Pre order nu, levering op :date:', 'text', [
                                                'date' => $product->expectedInStockDate()
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

        <livewire:cart.add-to-cart :product="$product" view="add-to-cart-small"/>
    </header>
</div>
