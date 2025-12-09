<div>
    <label for="product-extra-{{$extra->id}}"
           class="block text-md font-bold text-gray-700">
        {{$extra->name}}{{$extra->required ? '*' : ''}}@if($extra->price > 0)
            (+ {{CurrencyHelper::formatPrice($extra->price)}})
        @endif:
    </label>
    <div class="relative flex items-start">
        <input type="{{ $extra->input_type }}"
               @if($extra->input_type == 'numeric')
                   min="{{ $extra->min_length }}" max="{{ $extra->max_length }}"
               @else
                   minlength="{{ $extra->min_length }}" maxlength="{{ $extra->max_length }}"
               @endif
               @if($extra->required) required @endif
               class="custom-form-input"
               id="product-extra-{{$extra->id}}"
               name="product-extra-{{$extra->id}}"
               wire:model.live.debounce.500ms="extras.{{ $extraKey }}.value">
    </div>
    @if($extra->helper_text)
        <p class="text-sm">{{ $extra->helper_text }}</p>
    @endif
</div>
