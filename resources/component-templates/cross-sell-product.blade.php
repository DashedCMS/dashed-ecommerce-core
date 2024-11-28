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
                <x-stock-text :product="$product" />
            </div>
        </div>

        <livewire:cart.add-to-cart :product="$product" view="add-to-cart-small"/>
    </header>
</div>
