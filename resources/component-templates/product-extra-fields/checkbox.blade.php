<div>
    @foreach($extra->productExtraOptions as $option)
        <label for="product-extra-{{$option->id}}"
               class="block text-md font-bold text-gray-700">
            {{$extra->name}}{{$extra->required ? '*' : ''}} @if($extra->price > 0)
                (+ {{CurrencyHelper::formatPrice($extra->price)}})
            @endif:
        </label>
        <div class="relative flex items-start">
            <div class="flex h-6 items-center">
                <input type="checkbox"
                       class="h-4 rounded border-2 border-primary-600 text-primary-600 focus:ring-primary-600 form-input"
                       id="product-extra-{{$option->id}}"
                       name="product-extra-{{$option->id}}"
                       value="{{$option->id}}"
                       wire:model.live="extras.{{ $extraKey }}.value">
            </div>
            <div class="ml-3 text-md leading-6">
                <label for="product-extra-{{$option->id}}"
                       class="font-bold text-gray-900">{{$option->value}} @if($option->price > 0)
                        (+ {{CurrencyHelper::formatPrice($option->price)}})
                    @endif</label>
            </div>
        </div>
    @endforeach
    @if($extra->helper_text)
        <p class="text-sm">{{ $extra->helper_text }}</p>
    @endif
</div>
