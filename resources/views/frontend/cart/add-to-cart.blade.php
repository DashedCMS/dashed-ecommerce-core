<form wire:submit.prevent="addToCart">
    @if($filters)
        <div class="max-w-sm">
            @foreach($filters as $filter)
                @if($filter['correctFilterOptions'] > 1)
                    <label for="filter-{{$filter['id']}}"
                           class="block text-sm font-medium text-gray-700 mt-4">
                        {{$filter['name']}}:
                    </label>
                    <select
                        class="mt-1 block w-full pl-3 pr-10 py-2 text-base border border-primary-500 focus:outline-none focus:ring-primary-500 focus:border-primary-500 sm:text-sm rounded-md"
                        id="filter-{{$filter['id']}}"
                        onchange="$(this).val() ? window.location = $(this).val() : ''">
                        @foreach($filter['values'] as $option)
                            @if($option['url'])
                                <option value="{{$option['url']}}"
                                        @if(!$option['url']) disabled
                                        @endif
                                        @if($option['active']) selected @endif>{{$option['name']}} @if($option['url']) @if($option['in_stock'])
                                        (Op voorraad) @elseif(!$option['in_stock'])
                                        (Uitverkocht) @endif @elseif(!$option['url'])
                                        (Niet beschikbaar) @endif</option>
                            @endif
                        @endforeach
                    </select>
                @endif
            @endforeach
        </div>
    @endif
    @if($extras)
        <div class="max-w-sm">
            @foreach($extras as $extraKey => $extra)
                <label for="product-extra-{{$extra->id}}"
                       class="block text-sm font-medium text-gray-700 mt-4">
                    {{$extra->name}}{{$extra->required ? '*' : ''}}:
                </label>
                <select
                    class="mt-1 block w-full pl-3 pr-10 py-2 text-base border border-primary-500 focus:outline-none focus:ring-primary-500 focus:border-primary-500 sm:text-sm rounded-md"
                    id="product-extra-{{$extra->id}}"
                    name="product-extra-{{$extra->id}}"
                    wire:model="extras.{{ $extraKey }}.value"
                    @if($extra->required) required @endif
                >
                    <option value="">{{Translation::get('make-a-choice', 'product', 'Maak een keuze')}}</option>
                    @foreach($extra->productExtraOptions as $option)
                        <option
                            value="{{$option->id}}">{{$option->value}} @if($option->price > 0)
                                (+ {{CurrencyHelper::formatPrice($option->price)}}
                                ) @endif
                        </option>
                    @endforeach
                </select>
            @endforeach
        </div>
    @endif
    <div class="flex mt-3 space-x-4">
        @if($product->inStock())
            <div
                class="inline-flex items-center h-10 overflow-hidden border border-gray-200">
                <div wire:click="setQuantity('{{$quantity + 1}}')"
                     class="flex items-center justify-center w-10 h-10 bg-gray-50 text-primary-500 cursor-pointer">
                    +
                </div>
                <input class="w-16 h-10 text-center text-primary-500 font-bold" type="number"
                       wire:model.debounce.500ms="quantity"
                       required
                       min="1" max="{{$product->stock()}}">
                <div wire:click="setQuantity('{{$quantity - 1}}')"
                     class="flex items-center justify-center w-10 h-10 bg-gray-50 text-primary-500 cursor-pointer">
                    -
                </div>
            </div>
        @endif

        @if($product->inStock())
            <button type="submit" class="h-10 text-base button button--primary">Toevoegen</button>
        @else
            <div class="h-10 text-base button button--primary pointer-events-none">
                Toevoegen
            </div>
        @endif
    </div>
</form>
