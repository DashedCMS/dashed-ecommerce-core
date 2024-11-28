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
                <x-stock-text :product="$product" />
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
