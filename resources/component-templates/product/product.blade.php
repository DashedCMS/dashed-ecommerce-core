<div class="rounded-lg bg-white relative group flex flex-col h-full">
    <x-dashed-ecommerce-core::frontend.products.schema :product="$product"/>
    <a href="{{ $product->getUrl() }}" class="flex flex-col h-full">
        @if ($product->discountPrice)
            <div
                class="absolute top-3 right-3 uppercase tracking-wider py-1 px-2 text-xs font-bold bg-primary-500 text-white rounded-lg">
                {{ Translation::get('sale', 'product', 'Uitverkoop') }}
            </div>
        @endif

        <div>
            <div class="w-full aspect-[4/3] overflow-hidden">
                @if($product->firstImage)
                    <x-dashed-files::image
                        class="w-full aspect-[4/3] object-cover object-center group-hover:scale-110 transform trans"
                        config="dashed"
                        :mediaId="$product->firstImage"
                        :alt="$product->name"
                        :manipulations="[
                            'widen' => 1000,
                        ]"
                    />
                @endif
            </div>

            {{--            @if($product->productCategories()->count())--}}
            {{--                <p class="mt-2 text-xs text-white font-medium rounded-lg w-fit px-2 py-1 bg-primary-500">{{$product->productCategories()->first()->name}}</p>--}}
            {{--            @endif--}}
            <p class="font-medium uppercase mt-2 grow">{{ $product->name }}</p>
        </div>

        <div class="text-black grid pt-2 text-left mt-auto">
            <div class="my-2 flex flex-wrap gap-2 items-center">
                @if($product->discountPrice)
                    <span class="line-through text-red-500 mr-2 font-normal">
                                    {{CurrencyHelper::formatPrice($product->discountPrice)}}
                                </span>
                @endif
                <p class="text-xl tracking-tight font-medium text-gray-900">
                    @if($product->productGroup->showSingleProduct())
                        {{ $product->productGroup->fromPrice() }}
                        {{--                    {{ $product->productGroup->betweenPrice() }}--}}
                    @else
                        {{ CurrencyHelper::formatPrice($product->currentPrice) }}
                    @endif
                </p>
            </div>

            <div class="mb-3 flex items-center">
                <x-product.stock-text :product="$product"/>
            </div>

            <button
                class="button button--primary w-full"
                href="{{ $product->getUrl() }}"
            >
                {{ Translation::get('view-product', 'product', 'Bekijken') }}
            </button>
        </div>
    </a>
</div>
