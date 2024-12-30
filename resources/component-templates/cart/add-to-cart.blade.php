<form wire:submit="addToCart" class="grid gap-4">
    @foreach($filters ?? [] as $filterKey => $filter)
        @if(count($filter['options']))
            <x-dynamic-component
                :component="'filter-fields.' . $filter['type']"
                :filter="$filter"
                :filterKey="$filterKey"/>
        @endif
    @endforeach
    @if($productExtras ?? [])
        @foreach($productExtras as $extraKey => $extra)
            <x-dynamic-component
                :component="'product-extra-fields.' . $extra['type']"
                :extra="$extra"
                :extras="$extras"
                :extraKey="$extraKey"/>
        @endforeach
    @endif

    @if($product)
        <x-product.volume-discounts :product="$product" :volumeDiscounts="$volumeDiscounts"/>
    @endif

    <div class="mt-4 flex flex-wrap gap-4">
        <div
            class="inline-flex items-center justify-between md:justify-start p-4 transition bg-gray-100 focus-within:bg-white focus-within:ring-2 focus-within:ring-primary-800 w-full md:w-fit rounded-lg">
            <div
                wire:click="setQuantity('{{ $quantity - 1 }}')"
                class="grid w-6 h-6 bg-white rounded shadow-xl cursor-pointer place-items-center text-primary-800 hover:bg-primary-800 hover:text-white shadow-primary-800/10 ring-1 ring-black/5 trans"
            >
                <x-lucide-minus class="w-4 h-4"/>
            </div>

            <input
                class="w-[4ch] px-0 py-0.5 focus:ring-0 text-center bg-transparent border-none"
                type="number" value="1" id="qty"
                name="qty" disabled
                wire:model="quantity"
                min="1" max="{{$product?->stock()}}">

            <div
                wire:click="setQuantity('{{ $quantity + 1 }}')"
                class="grid w-6 h-6 bg-white rounded shadow-xl cursor-pointer place-items-center text-primary-800 hover:bg-primary-800 hover:text-white shadow-primary-800/10 ring-1 ring-black/5 trans"
            >
                <x-lucide-plus class="w-4 h-4"/>
            </div>
        </div>

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
    </div>
</form>
