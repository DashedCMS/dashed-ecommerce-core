<form wire:submit.prevent="addToCart">
    @if($filters)
        <div class="max-w-sm">
            @foreach($filters as $filter)
                @if($filter['correctFilterOptions'] > 1)
                    <label for="filter-{{$filter['id']}}"
                           class="block text-sm font-medium text-primary mt-4">
                        {{$filter['name']}}:
                    </label>
                    <select
                        class="mt-1 block w-full pl-3 pr-10 py-2 text-base border border-primary-500 focus:outline-none focus:ring-primary-500 focus:border-primary-500 sm:text-sm rounded-md"
                        id="filter-{{$filter['id']}}"
                        onchange="$(this).val() ? window.location = $(this).val() : ''">
                        @foreach($filter['values'] as $option)
                            @if($option['url'])
                                <option value="{{$option['url']}}"
                                        @if(!$option['url']) disabled @endif
                                        @if($option['active']) selected @endif
                                >
                                    {{$option['name']}}
                                    @if($option['url'])
                                        @if($option['in_stock'])
                                            ({{Translation::get('product-out-of-stock', 'product', 'In stock')}})
                                        @elseif(!$option['in_stock'])
                                            ({{Translation::get('product-out-of-stock', 'product', 'Out of stock')}})
                                        @endif
                                    @elseif(!$option['url'])
                                        ({{Translation::get('product-unavailable', 'product', 'Unavailable')}})
                                    @endif
                                </option>
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
                @if($extra->type == 'single')
                    <label for="product-extra-{{$extra->id}}"
                           class="block text-sm font-medium text-primary mt-4">
                        {{$extra->name}}{{$extra->required ? '*' : ''}}:
                    </label>
                    <select
                        class="mt-1 block w-full pl-3 pr-10 py-2 text-base border border-primary-500 focus:outline-none focus:ring-primary-500 focus:border-primary-500 sm:text-sm rounded-md"
                        id="product-extra-{{$extra->id}}"
                        name="product-extra-{{$extra->id}}"
                        wire:model="extras.{{ $extraKey }}.value"
                        @if($extra->required) required @endif
                    >
                        <option value="">{{Translation::get('make-a-choice', 'product', 'Make a choice')}}</option>
                        @foreach($extra->productExtraOptions as $option)
                            <option
                                value="{{$option->id}}">{{$option->value}} @if($option->price > 0)
                                    (+ {{CurrencyHelper::formatPrice($option->price)}})
                                @endif
                            </option>
                        @endforeach
                    </select>
                @elseif($extra->type == 'multiple')
                    <p>Niet ondersteund</p>
                @elseif($extra->type == 'checkbox')
                    <div>
                        @foreach($extra->productExtraOptions as $option)
                            <label for="product-extra-{{$option->id}}"
                                   class="block text-sm font-medium text-primary mt-4">
                                {{$extra->name}}{{$extra->required ? '*' : ''}}:
                            </label>
                            <div class="relative flex items-start">
                                <div class="flex h-6 items-center">
                                    <input type="checkbox"
                                           class="h-4 w-4 rounded border-gray-300 text-primary-600 focus:ring-primary-600"
                                           id="product-extra-{{$option->id}}"
                                           name="product-extra-{{$option->id}}"
                                           value="{{$option->id}}"
                                           wire:model="extras.{{ $extraKey }}.value">
                                </div>
                                <div class="ml-3 text-sm leading-6">
                                    <label for="product-extra-{{$option->id}}"
                                           class="font-medium text-gray-900">{{$option->value}} @if($option->price > 0)
                                            (+ {{CurrencyHelper::formatPrice($option->price)}})
                                        @endif</label>
                                </div>
                            </div>
                        @endforeach
                    </div>
                @elseif($extra->type == 'input')
                    <div>
                        <label for="product-extra-{{$extra->id}}"
                               class="block text-sm font-medium text-primary mt-4">
                            {{$extra->name}}{{$extra->required ? '*' : ''}}:
                        </label>
                        <div class="relative flex items-start">
                            <input type="{{ $extra->input_type }}"
                                   @if($extra->input_type == 'numeric')
                                       min="{{ $extra->min_length }}" max="{{ $extra->max_length }}"
                                   @else
                                       minlength="{{ $extra->min_length }}" maxlength="{{ $extra->max_length }}"
                                   @endif
                                   @if($extra->required) required @endif
                                   class="mt-1 block w-full pl-3 pr-10 py-2 text-base border border-primary-500 focus:outline-none focus:ring-primary-500 focus:border-primary-500 sm:text-sm rounded-md"
                                   id="product-extra-{{$extra->id}}"
                                   name="product-extra-{{$extra->id}}"
                                   wire:model.debounce.500ms="extras.{{ $extraKey }}.value">
                        </div>
                    </div>
                @elseif($extra->type == 'file')
                    <div>
                        <label for="product-extra-{{$extra->id}}"
                               class="block text-sm font-medium text-primary mt-4">
                            {{$extra->name}}{{$extra->required ? '*' : ''}}:
                        </label>
                        <div class="relative flex items-start">
                            <input type="file"
                                   @if($extra->required) required @endif
                                   class="mt-1 block w-full pl-3 pr-10 py-2 text-base border border-primary-500 focus:outline-none focus:ring-primary-500 focus:border-primary-500 sm:text-sm rounded-md"
                                   id="product-extra-{{$extra->id}}"
                                   name="product-extra-{{$extra->id}}"
                                   wire:model="files.{{ $extra->id }}.value">
                        </div>
                    </div>
                @endif
            @endforeach
        </div>
    @endif
    <div class="mt-4 grid gap-4 max-w-sm">
        @if($product->inStock())
            <button type="submit"
                    class="h-10 w-full text-base button button-white-on-primary">{{Translation::get('add-to-cart', 'product', 'Add to cart')}}</button>
        @else
            <div class="h-10 w-full text-base button button-white-on-primary pointer-events-none">
                {{Translation::get('add-to-cart', 'product', 'Add to cart')}}
            </div>
        @endif
    </div>
</form>
