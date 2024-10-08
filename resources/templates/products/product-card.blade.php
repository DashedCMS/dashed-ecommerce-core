<div>
    <div class="relative">
        <x-dashed-ecommerce-core::frontend.products.schema
                :product="$product"></x-dashed-ecommerce-core::frontend.products.schema>
        <div class="relative h-72 w-full overflow-hidden rounded-lg">
            @if($product->firstImage)
                <a href="{{$product->getUrl()}}">
                    <x-drift::image
                            class="h-full w-full object-cover object-center"
                            config="dashed"
                            :path="$product->firstImage"
                            :alt=" $product->name"
                            :manipulations="[
                                        'fit' => [300,300],
                                    ]"
                    />
                </a>
            @endif
        </div>
        <div class="relative mt-4">
            <h3 class="text-sm font-bold text-gray-900">{{$product->name}}</h3>
            @if($product->productCategories()->count())
                <p class="mt-1 text-sm text-gray-500">{{$product->productCategories()->first()->name}}</p>
            @endif
        </div>
        <div class="absolute inset-x-0 top-0 flex h-72 items-end justify-end overflow-hidden rounded-lg p-4">
            <div aria-hidden="true"
                 class="absolute inset-x-0 bottom-0 h-36 bg-gradient-to-t from-black opacity-50"></div>
            <p class="relative text-lg font-semibold text-white">{{CurrencyHelper::formatPrice($product->currentPrice)}}
                @if($product->discountPrice)
                    <span
                            class="text-xs md:text-sm line-through ml-2">{{CurrencyHelper::formatPrice($product->discountPrice)}}</span>
                @endif
            </p>
        </div>
    </div>
    <div class="mt-6">
        <a href="{{ $product->getUrl() }}"
           class="relative flex items-center justify-center rounded-md border border-transparent bg-primary-500 px-8 py-2 text-sm text-white font-bold hover:bg-primary-700">
            {{ Translation::get('view-product', 'products', 'View product') }}
        </a>
    </div>
</div>
