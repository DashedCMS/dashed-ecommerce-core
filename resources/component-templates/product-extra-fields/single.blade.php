<div>
    <label for="product-extra-{{$extra->id}}"
           class="inline-block text-md font-bold text-gray-700">
        {{$extra->name}}{{$extra->required ? '*' : ''}}@if($extra->price > 0)
            (+ {{CurrencyHelper::formatPrice($extra->price)}})
        @endif
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
    @if($extra->helper_text)
        <p class="text-sm">{{ $extra->helper_text }}</p>
    @endif
</div>
