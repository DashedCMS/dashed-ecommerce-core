<div class="rounded-lg bg-white relative group">
    <a href="{{ $product->getUrl() }}">
        @if ($product->discountPrice)
            <div
                class="absolute top-3 right-3 uppercase tracking-wider py-1 px-2 text-xs font-bold bg-primary-500 text-white rounded-lg">
                {{ Translation::get('sale', 'product', 'Uitverkoop') }}
            </div>
        @endif

        <div class="w-full aspect-[4/3] overflow-hidden">
            @if($product->firstImage)
                <x-drift::image
                    class="w-full aspect-[4/3] object-cover object-center group-hover:scale-110 transform trans"
                    config="dashed"
                    :path="$product->firstImage"
                    :alt="$product->name"
                    :manipulations="[
                            'widen' => 1000,
                        ]"
                />
            @endif
        </div>

        <header class="text-black font-medium uppercase flex flex-col mt-2 text-left">
            <p>{{ $product->name }}</p>

            <div class="my-2 flex flex-wrap gap-2 items-center">
                @if($product->discountPrice)
                    <span class="line-through text-red-500 mr-2 font-normal">
                                    {{CurrencyHelper::formatPrice($product->discountPrice)}}
                                </span>
                @endif
                <p class="text-xl tracking-tight font-medium text-gray-900">{{ CurrencyHelper::formatPrice($product->currentPrice) }}</p>
            </div>

            <div class="mb-3 flex items-center">
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
                class="button button--primary w-full"
                href="{{ $product->getUrl() }}"
            >
                {{ Translation::get('view-product', 'product', 'Bekijken') }}
            </button>
        </header>
        {{--    <div class="h-1 bg-gradient-to-r from-primary-200 to-primary-500 rounded-b-lg"></div>--}}
    </a>
</div>
