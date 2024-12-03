<div>
    <label for="product-extra-{{$extra->id}}"
           class="inline-block text-md font-bold text-gray-700">
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
                <span class="font-brand text-center">{{$option->value}}</span>
                @if($option->price > 0)
                    <span class="font-bold text-center">+ {{CurrencyHelper::formatPrice($option->price)}}</span>
                @endif
            </div>
        @endforeach
    </div>
</div>
