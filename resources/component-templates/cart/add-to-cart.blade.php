<form wire:submit="addToCart" class="grid gap-4">
    @if($filters)
        <div class="">
            @foreach($filters as $filterKey => $filter)
                @if(count($filter['options']))
                    <div class="grid">
                        <label for="filter-{{$filter['id']}}"
                               class="inline-block text-sm mb-2">
                            {{$filter['name']}}
                        </label>
                        <select
                                class="form-input"
                                id="filter-{{$filter['id']}}"
                                wire:model.live="filters.{{$filterKey}}.active">
                            <option
                                    value="">{{ Translation::get('choose-a-option', 'product', 'Kies een optie') }}</option>
                            @foreach($filter['options'] as $option)
                                <option value="{{ $option['id'] }}"
                                >
                                    {{$option['name']}}
                                </option>
                            @endforeach
                        </select>
                    </div>
                @endif
            @endforeach
        </div>
    @endif
    @if($productExtras)
        <div class="">
            @foreach($productExtras as $extraKey => $extra)
                @if($extra->type == 'single')
                    <div>
                        <label for="product-extra-{{$extra->id}}"
                               class="inline-block text-sm mb-2">
                            {{$extra->name}}{{$extra->required ? '*' : ''}}
                        </label>
                        <select
                                class="form-input"
                                id="product-extra-{{$extra->id}}"
                                name="product-extra-{{$extra->id}}"
                                wire:model.live="extras.{{ $extraKey }}.value"
                                @if($extra->required) required @endif
                        >
                            <option value="">{{Translation::get('make-a-choice', 'product', 'Maak een keuze')}}</option>
                            @foreach($extra->productExtraOptions as $option)
                                <option
                                        value="{{$option->id}}">{{$option->value}} @if($option->price > 0)
                                        (+ {{CurrencyHelper::formatPrice($option->price)}})
                                    @endif
                                </option>
                            @endforeach
                        </select>
                    </div>
                @elseif($extra->type == 'imagePicker')
                    <div>
                        <label for="product-extra-{{$extra->id}}"
                               class="inline-block text-sm mb-2">
                            {{$extra->name}}{{$extra->required ? '*' : ''}}
                        </label>
                        <div class="grid grid-cols-2 md:grid-cols-3 gap-4">
                            @foreach($extra->productExtraOptions as $option)
                                <div class="grid items-center cursor-pointer relative"
                                     wire:click="setProductExtraValue({{ $extraKey }}, {{$option->id}})">
                                    @if(($extras[$extraKey]['value'] ?? null) == $option->id)
                                        <div class="absolute top-1 right-1 text-white bg-green-500 rounded-full">
                                            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24"
                                                 stroke-width="1.5" stroke="currentColor" class="w-6 h-6">
                                                <path stroke-linecap="round" stroke-linejoin="round"
                                                      d="M9 12.75 11.25 15 15 9.75M21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z"/>
                                            </svg>
                                        </div>
                                    @endif
                                    <x-drift::image
                                            class="w-full h-full"
                                            config="dashed"
                                            :path="$option->image"
                                            :alt="$option->value"
                                            :manipulations="[
                                                'fit' => [150,150],
                                            ]"
                                    />
                                    <span class="font-brand text-center">{{$option->value}} @if($option->price > 0)
                                            (+ {{CurrencyHelper::formatPrice($option->price)}})
                                        @endif
                                    </span>
                                </div>
                            @endforeach
                        </div>
                    </div>
                @elseif($extra->type == 'multiple')
                    <p>Niet ondersteund</p>
                @elseif($extra->type == 'checkbox')
                    <div>
                        @foreach($extra->productExtraOptions as $option)
                            <label for="product-extra-{{$option->id}}"
                                   class="block text-sm font-bold text-primary mt-4">
                                {{$extra->name}}{{$extra->required ? '*' : ''}}:
                            </label>
                            <div class="relative flex items-start">
                                <div class="flex h-6 items-center">
                                    <input type="checkbox"
                                           class="h-4 w-4 rounded border-gray-300 text-primary-600 focus:ring-primary-600"
                                           id="product-extra-{{$option->id}}"
                                           name="product-extra-{{$option->id}}"
                                           value="{{$option->id}}"
                                           wire:model.live="extras.{{ $extraKey }}.value">
                                </div>
                                <div class="ml-3 text-sm leading-6">
                                    <label for="product-extra-{{$option->id}}"
                                           class="font-bold text-gray-900">{{$option->value}} @if($option->price > 0)
                                            (+ {{CurrencyHelper::formatPrice($option->price)}})
                                        @endif</label>
                                </div>
                            </div>
                        @endforeach
                    </div>
                @elseif($extra->type == 'input')
                    <div>
                        <label for="product-extra-{{$extra->id}}"
                               class="block text-sm font-bold text-primary mt-4">
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
                                   wire:model.live.debounce.500ms="extras.{{ $extraKey }}.value">
                        </div>
                    </div>
                @elseif($extra->type == 'file')
                    <div>
                        <label for="product-extra-{{$extra->id}}"
                               class="block text-sm font-bold text-primary mt-4">
                            {{$extra->name}}{{$extra->required ? '*' : ''}}:
                        </label>
                        <div class="relative flex items-start">
                            <input type="file"
                                   @if($extra->required) required @endif
                                   class="mt-1 block w-full pl-3 pr-10 py-2 text-base border border-primary-500 focus:outline-none focus:ring-primary-500 focus:border-primary-500 sm:text-sm rounded-md"
                                   id="product-extra-{{$extra->id}}"
                                   name="product-extra-{{$extra->id}}"
                                   wire:model.live="files.{{ $extra->id }}.value">
                        </div>
                    </div>
                @endif
            @endforeach
        </div>
    @endif
    <div
            class="inline-flex items-center p-1 transition rounded bg-black/5 focus-within:bg-white focus-within:ring-2 focus-within:ring-primary-800 w-fit">
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
                min="1" max="{{$product->stock()}}">

        <div
                wire:click="setQuantity('{{ $quantity + 1 }}')"
                class="grid w-6 h-6 bg-white rounded shadow-xl cursor-pointer place-items-center text-primary-800 hover:bg-primary-800 hover:text-white shadow-primary-800/10 ring-1 ring-black/5 trans"
        >
            <x-lucide-plus class="w-4 h-4"/>
        </div>
    </div>
    <div class="my-4">
        <p class="flex items-center text-sm">
            <x-dashed-files::image
                    class="h-10 rounded-lg mr-2"
                    :mediaId="Translation::get('pay-in-terms-logo', 'products', '', 'image')"
            />
            {!! Translation::get('pay-in-terms', 'products', 'Betaal in 3 termijnen: &nbsp; <b>:term:</b> &nbsp; per termijn', 'text', [
                'term' => CurrencyHelper::formatPrice($price / 3),
                ]) !!}
        </p>
    </div>
    <div class="mt-4 grid gap-4">
        @if($product && $product->inStock())
            <button type="submit"
                    class="w-full button button--primary-light">
                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5"
                     stroke="currentColor" class="w-6 h-6">
                    <path stroke-linecap="round" stroke-linejoin="round"
                          d="M2.25 3h1.386c.51 0 .955.343 1.087.835l.383 1.437M7.5 14.25a3 3 0 0 0-3 3h15.75m-12.75-3h11.218c1.121-2.3 2.1-4.684 2.924-7.138a60.114 60.114 0 0 0-16.536-1.84M7.5 14.25 5.106 5.272M6 20.25a.75.75 0 1 1-1.5 0 .75.75 0 0 1 1.5 0Zm12.75 0a.75.75 0 1 1-1.5 0 .75.75 0 0 1 1.5 0Z"/>
                </svg>

                <span>{{Translation::get('add-to-cart', 'product', 'Toevoegen aan winkelmandje')}}</span>
            </button>
        @elseif(!$product)
            <div class="w-full button button--primary-dark pointer-events-none">
                {{Translation::get('choose-another-product', 'product', 'Kies een ander product')}}
            </div>
        @else
            <div class="w-full button button--primary-dark pointer-events-none">
                {{Translation::get('add-to-cart-not-in-stock', 'product', 'Niet op voorraad')}}
            </div>
        @endif
    </div>

    <div class="my-4 flex flex-wrap items-center md:gap-8">
        <div class="flex flex-col gap-2">
            <p class="text-xs">Klanten beoordelen ons met een:</p>
            <div class="flex gap-2 items-center justify-center">
                <x-drift::image
                        class="w-12 rounded-xl"
                        config="dashed"
                        :path="Translation::get('product-review-image', 'product', '', 'image')"
                        alt=""
                        :manipulations="[
                                                        'widen' => 500,
                                                    ]"
                />
                <div class="flex gap-1 items-center justify-center">
                    @for($i = 5; $i > 0; $i--)
                        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor"
                             class="size-8 text-yellow-500">
                            <path fill-rule="evenodd"
                                  d="M10.788 3.21c.448-1.077 1.976-1.077 2.424 0l2.082 5.006 5.404.434c1.164.093 1.636 1.545.749 2.305l-4.117 3.527 1.257 5.273c.271 1.136-.964 2.033-1.96 1.425L12 18.354 7.373 21.18c-.996.608-2.231-.29-1.96-1.425l1.257-5.273-4.117-3.527c-.887-.76-.415-2.212.749-2.305l5.404-.434 2.082-5.005Z"
                                  clip-rule="evenodd"/>
                        </svg>
                    @endfor
                </div>
            </div>

            <p class="text-primary-800 text-xs xl:text-lg"><span
                        class="font-bold text-primary-300">{{ Customsetting::get('google_maps_rating') }}</span>
                op
                basis van <span
                        class="font-bold text-primary-300">{{ Customsetting::get('google_maps_review_count') }}</span>
                reviews</p>
        </div>
    </div>
</form>
