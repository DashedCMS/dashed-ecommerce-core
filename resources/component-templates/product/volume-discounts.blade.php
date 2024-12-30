@if($product && $volumeDiscounts)
    <div class="my-4 rounded-lg bg-gray-100 p-4 grid gap-4">
        <div>
            <h3 class="text-lg font-bold">{{Translation::get('volume-discounts', 'product', 'Volume kortingen')}}</h3>
        </div>
        <div class="grid grid-cols-4 border-b pb-4 border-gray-300">
            <span>Aantal</span>
            <span>Prijs per stuk</span>
            <span>Korting</span>
            <span></span>
        </div>
        @foreach($volumeDiscounts as $volumeDiscount)
            <div class="grid grid-cols-4 @if(!$loop->last) pb-4 border-b border-gray-300 @endif">
                    <span class="my-auto">
                        {{Translation::get('minimum-quantity-for-volume-discount', 'product', 'Vanaf :quantity: stuks', 'text', [
                        'quantity' => $volumeDiscount['min_quantity']
                    ])}}
                    </span>
                <span class="my-auto">{{ $volumeDiscount['price'] }}</span>
                <span class="font-bold text-green-500 my-auto">{{ $volumeDiscount['discountString'] }}</span>
                <span class="my-auto">
                    @if($product->inStock() && $product->stock() >= $volumeDiscount['min_quantity'])
                        <div wire:click="addSpecificQuantity({{ $volumeDiscount['min_quantity'] }})"
                             class="cursor-pointer button button--primary text-xs py-1 px-1">
                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5"
                         stroke="currentColor" class="w-5 h-5">
                        <path stroke-linecap="round" stroke-linejoin="round"
                              d="M2.25 3h1.386c.51 0 .955.343 1.087.835l.383 1.437M7.5 14.25a3 3 0 0 0-3 3h15.75m-12.75-3h11.218c1.121-2.3 2.1-4.684 2.924-7.138a60.114 60.114 0 0 0-16.536-1.84M7.5 14.25 5.106 5.272M6 20.25a.75.75 0 1 1-1.5 0 .75.75 0 0 1 1.5 0Zm12.75 0a.75.75 0 1 1-1.5 0 .75.75 0 0 1 1.5 0Z"/>
                    </svg>
                </div>
                    @else
                        <div class="cursor-pointer button button--primary text-xs py-1 px-1" disabled>
                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5"
                         stroke="currentColor" class="w-5 h-5">
                        <path stroke-linecap="round" stroke-linejoin="round"
                              d="M2.25 3h1.386c.51 0 .955.343 1.087.835l.383 1.437M7.5 14.25a3 3 0 0 0-3 3h15.75m-12.75-3h11.218c1.121-2.3 2.1-4.684 2.924-7.138a60.114 60.114 0 0 0-16.536-1.84M7.5 14.25 5.106 5.272M6 20.25a.75.75 0 1 1-1.5 0 .75.75 0 0 1 1.5 0Zm12.75 0a.75.75 0 1 1-1.5 0 .75.75 0 0 1 1.5 0Z"/>
                    </svg>
                </div>
                    @endif
                </span>
            </div>
        @endforeach
    </div>
@endif
