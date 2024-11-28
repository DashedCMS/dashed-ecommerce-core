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
                @if($product && $product->purchasable())
                    @if($product->stock() > 10)
                        <p class="text-md tracking-wider text-primary-600 flex items-center font-bold"><span
                                    class="mr-1"><svg class="w-6 h-6" fill="none" stroke="currentColor"
                                                      viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg"><path
                                            stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                            d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
                                            </span>
                            {{Translation::get('product-in-stock', 'product', 'Op voorraad')}}
                        </p>
                    @else
                        <p class="text-md tracking-wider text-primary-600 flex items-center font-bold"><span
                                    class="mr-1"><svg class="w-6 h-6" fill="none" stroke="currentColor"
                                                      viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg"><path
                                            stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                            d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
                                            </span>
                            {{Translation::get('product-in-stock-specific', 'product', 'Nog :count: op voorraad', 'text', [
    'count' => $product->stock()
    ])}}
                        </p>
                    @endif
                @else
                    <p class="text-md tracking-wider text-red-500 flex items-center font-bold"><span
                                class="mr-1"><svg class="w-6 h-6" fill="none" stroke="currentColor"
                                                  viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg"><path
                                        stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                        d="M10 14l2-2m0 0l2-2m-2 2l-2-2m2 2l2 2m7-2a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg></span>{{Translation::get('product-out-of-stock', 'product', 'Niet op voorraad')}}
                    </p>
                @endif
            </div>
        </div>

        <livewire:cart.add-to-cart :product="$product" view="add-to-cart-small"/>
    </header>
</div>
