<div class="border-4 border-white rounded-lg bg-white relative">
    <a href="{{ $product->getUrl() }}">
    @if ($product->discountPrice)
        <div
                class="absolute top-3 right-3 uppercase px-1 tracking-wider py-0.5 text-xs font-bold bg-primary-800 text-white">
            {{ Translation::get('sale', 'product', 'Sale') }}
        </div>
    @endif

    @if($product->firstImage)
        <x-drift::image
                class="w-full aspect-[4/3] object-contain mix-blend-multiply p-4 object-center"
                config="dashed"
                :path="$product->firstImage"
                :alt="$product->name"
                :manipulations="[
                            'widen' => 1000,
                        ]"
        />
    @endif

    <header class="p-4 @if($product->firstImage) border-t border-primary-500 @endif text-center text-black flex flex-col">
        <p>{{ $product->name }}</p>

        <div class="mt-2 mb-4 flex items-baseline justify-center gap-1">
            @if ($product->discountPrice)
                <p
                        class="text-gray-400 line-through text-sm">{{ CurrencyHelper::formatPrice($product->discountPrice) }}</p>
            @endif
            <p class="font-bold text-primary-800">{{ CurrencyHelper::formatPrice($product->currentPrice) }}</p>
        </div>

        <div class="my-3 flex items-center justify-center">
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

        <button
                class="button button--primary-dark w-full"
                href="{{ $product->getUrl() }}"
        >
            Bekijken
        </button>
    </header>
    {{--    <div class="h-1 bg-gradient-to-r from-primary-200 to-primary-500 rounded-b-lg"></div>--}}
    </a>
</div>
