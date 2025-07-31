<div class="bg-gray-100 p-4 relative group flex gap-4">
    <x-dashed-ecommerce-core::frontend.products.schema :product="$product"/>
    @if($product->firstImage)
        <a href="{{ $product->getUrl() }}">
            <x-dashed-files::image
                class="w-28 h-28 aspect-square object-cover object-center group-hover:scale-110 transform trans"
                config="dashed"
                :mediaId="$product->firstImage"
                :alt="$product->name"
                :manipulations="[
                    'widen' => 200,
                ]"
            />
        </a>
    @endif

    <header class="text-black font-medium uppercase flex flex-col text-left grow">
        <a href="{{ $product->getUrl() }}"><p>{{ $product->name }}</p></a>

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
                <x-product.stock-text :product="$product"/>
            </div>
        </div>

        <form wire:submit="addToCart({{ $product->id }})" class="grid gap-4">
            <div class="grid gap-4 grow">
                @if($product && $product->inStock())
                    <button type="submit"
                            class="button button--small button--primary">
                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5"
                             stroke="currentColor" class="w-6 h-6">
                            <path stroke-linecap="round" stroke-linejoin="round"
                                  d="M2.25 3h1.386c.51 0 .955.343 1.087.835l.383 1.437M7.5 14.25a3 3 0 0 0-3 3h15.75m-12.75-3h11.218c1.121-2.3 2.1-4.684 2.924-7.138a60.114 60.114 0 0 0-16.536-1.84M7.5 14.25 5.106 5.272M6 20.25a.75.75 0 1 1-1.5 0 .75.75 0 0 1 1.5 0Zm12.75 0a.75.75 0 1 1-1.5 0 .75.75 0 0 1 1.5 0Z"/>
                        </svg>

                        <span>{{Translation::get('add-to-cart', 'product', 'Toevoegen aan winkelmandje')}}</span>
                    </button>
                @elseif(!$product)
                    <div class="button button--small button--primary-outline pointer-events-none">
                        {{Translation::get('choose-another-product', 'product', 'Kies een ander product')}}
                    </div>
                @else
                    <div class="button button--small button--primary-outline pointer-events-none">
                        {{Translation::get('add-to-cart-not-in-stock', 'product', 'Niet op voorraad')}}
                    </div>
                @endif
            </div>
        </form>
    </header>
</div>
