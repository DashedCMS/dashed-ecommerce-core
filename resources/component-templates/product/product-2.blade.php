<a href="{{ $product->url }}" class="card mx-auto w-full max-w-sm md:ml-0 group">
    <x-dashed-ecommerce-core::frontend.products.schema :product="$product"/>
    <div class="img-box rounded-t-3xl w-full aspect-square overflow-hidden border-gray-200 border bg-primary/50">
        @if($product->firstImage)
            <x-dashed-files::image
                class="w-full h-auto transition-all duration-700 group-hover:scale-[1.05] rounded-t-2xl object-cover block group-hover:hidden"
                config="dashed"
                :mediaId="$product->firstImage"
                :alt="$product->name"
                :manipulations="[
                            'widen' => 400,
                        ]"
            />
            <x-dashed-files::image
                class="w-full h-auto transition-all duration-700 group-hover:scale-[1.05] rounded-t-2xl object-cover hidden group-hover:block"
                config="dashed"
                :mediaId="$product->productGroup->images[1] ?? $product->firstImage"
                :alt="$product->name"
                :manipulations="[
                            'widen' => 400,
                        ]"
            />
        @endif
    </div>
    <div class="body border border-t-0 border-gray-200 w-full rounded-b-3xl p-5 shadow-sm shadow-transparent cursor-pointer transition-all duration-500 group-hover:shadow-gray-300 group-hover:bg-gray-50 group-hover:border-gray-300">
        <h2 class="font-medium text-xl leading-8 text-black mb-2">
            {{ $product->name }}
        </h2>
        <div class="flex min-[400px]:items-center justify-between gap-2 flex-col min-[400px]:flex-row">
            <div class="flex items-center gap-2">
                <p class="font-semibold text-xl leading-8 text-black">
                    @if($product->productGroup->showSingleProduct())
                        {{ $product->productGroup->fromPrice() }}
                        {{--                    {{ $product->productGroup->betweenPrice() }}--}}
                    @else
                        {{ CurrencyHelper::formatPrice($product->currentPrice) }}
                    @endif
                </p>
            </div>
            @if($product->discountPrice)
                <span class="line-through text-red-500 mr-2 font-normal">
                                    {{CurrencyHelper::formatPrice($product->discountPrice)}}
                                </span>
            @endif
        </div>
        <div class="flex items-center gap-2">
                @if ($product->discountPrice)
                    <span class="flex items-center gap-1 py-1 px-2 rounded-3xl  text-white font-medium text-sm bg-primary">
                        {{ Translation::get('sale', 'product', 'Aanbieding') }}
                    </span>
                @endif
        </div>
        <x-product.stock-text :product="$product" :forceWhite="false"/>
    </div>
</a>
