@if($product->inStock())
    @if($product->hasDirectSellableStock())
        @if($product->stock() > 10)
            <p class="text-md tracking-wider @if($forceWhite ?? false) text-white @else text-green-500 @endif  flex items-center font-bold"><span
                    class="mr-1"><svg class="w-6 h-6" fill="none"
                                      stroke="currentColor"
                                      viewBox="0 0 24 24"
                                      xmlns="http://www.w3.org/2000/svg"><path
                            stroke-linecap="round" stroke-linejoin="round"
                            stroke-width="2"
                            d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
                                            </span>
                {{Translation::get('product-in-stock', 'product', 'Op voorraad')}}
            </p>
        @else
            <p class="text-md tracking-wider @if($forceWhite ?? false) text-white @else text-green-500 @endif flex items-center font-bold"><span
                    class="mr-1"><svg class="w-6 h-6" fill="none"
                                      stroke="currentColor"
                                      viewBox="0 0 24 24"
                                      xmlns="http://www.w3.org/2000/svg"><path
                            stroke-linecap="round" stroke-linejoin="round"
                            stroke-width="2"
                            d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
                                            </span>
                {{Translation::get('product-in-stock-specific', 'product', 'Nog :count: op voorraad', 'text', [
                'count' => $product->stock()
                ])}}
            </p>
        @endif
    @else
        @if($product->expectedDeliveryInDays())
            <p class="font-bold italic text-md flex items-center gap-1 @if($forceWhite ?? false) text-white @else text-primary-500 @endif">
                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24"
                     fill="currentColor" class="size-8">
                    <path fill-rule="evenodd"
                          d="M12 2.25c-5.385 0-9.75 4.365-9.75 9.75s4.365 9.75 9.75 9.75 9.75-4.365 9.75-9.75S17.385 2.25 12 2.25ZM12.75 6a.75.75 0 0 0-1.5 0v6c0 .414.336.75.75.75h4.5a.75.75 0 0 0 0-1.5h-3.75V6Z"
                          clip-rule="evenodd"/>
                </svg>
                <span>
                    {{ Translation::get('pre-order-product-static-delivery-time', 'product', 'Levering duurt circa :days: dagen', 'text', [
                        'days' => $product->expectedDeliveryInDays()
                    ]) }}
                </span>
            </p>
        @else
            <p class="font-bold italic text-md flex items-center gap-1 @if($forceWhite ?? false) text-white @else text-primary-500 @endif ">
                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24"
                     fill="currentColor" class="size-8">
                    <path fill-rule="evenodd"
                          d="M12 2.25c-5.385 0-9.75 4.365-9.75 9.75s4.365 9.75 9.75 9.75 9.75-4.365 9.75-9.75S17.385 2.25 12 2.25ZM12.75 6a.75.75 0 0 0-1.5 0v6c0 .414.336.75.75.75h4.5a.75.75 0 0 0 0-1.5h-3.75V6Z"
                          clip-rule="evenodd"/>
                </svg>
                <span>
                    {{ Translation::get('pre-order-product-now', 'product', 'Pre order nu, levering op :date:', 'text', [
                        'date' => $product->expectedInStockDate()
                    ]) }}
                </span>
            </p>
        @endif
    @endif
@else
    <p class="font-bold @if($forceWhite ?? false)  text-white @else text-red-500 @endif text-md flex items-center gap-2">
        <x-lucide-x-circle class="h-5 w-5"/>
        {{ Translation::get('not-in-stock', 'product', 'Niet op voorraad') }}
    </p>
@endif
