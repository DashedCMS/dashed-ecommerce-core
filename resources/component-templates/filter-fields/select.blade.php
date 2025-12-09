<div class="grid">
    <label for="filter-{{$filter['id']}}"
           class="inline-block text-md font-bold mb-2">
        {{$filter['name']}}
    </label>
    @if($filter['contentBlocks']['content'] ?? false)
        <div class="mb-4 prose">
            {!! cms()->convertToHtml($filter['contentBlocks']['content']) !!}
        </div>
    @endif
    <select
            class="custom-form-input"
            id="filter-{{$filter['id']}}"
            wire:model.live="filters.{{$filterKey}}.active">
        <option
                value="">{{ Translation::get('choose-a-option', 'product', 'Kies een optie') }}</option>
        @foreach($filter['options'] as $option)
            <option value="{{ $option['id'] }}">
                {{$option['name']}}
            </option>
        @endforeach
    </select>
</div>
