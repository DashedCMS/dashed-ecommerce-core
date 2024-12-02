<div>
    <label for="product-extra-{{$extra->id}}"
           class="block text-md font-bold text-gray-700">
        {{$extra->name}}{{$extra->required ? '*' : ''}}:
    </label>
    <div class="relative flex items-start">
        <input type="file"
               @if($extra->required) required @endif
               class="form-input"
               id="product-extra-{{$extra->id}}"
               name="product-extra-{{$extra->id}}"
               wire:model.live="files.{{ $extra->id }}.value">
    </div>
</div>
