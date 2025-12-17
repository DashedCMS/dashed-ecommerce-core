<div class="grid">
    <label for="filter-{{$filter['id']}}"
           class="inline-block text-md font-bold mb-2">
        {{$filter['name']}}
    </label>

    @if($filter['contentBlocks']['content'] ?? false)
        <div class="mb-2 prose text-sm">
            {!! cms()->convertToHtml($filter['contentBlocks']['content']) !!}
        </div>
    @endif

    <ul class="flex flex-wrap gap-3">
        @foreach($filter['options'] as $option)
            @php
                $id = 'filter-'.$filter['id'].'-option-'.$option['id'];
            @endphp

            <li
                class="
                    px-3 py-1.5 text-sm font-bold bg-white rounded-lg relative min-w-12
                    text-center uppercase transition
                    border-2 border-primary
                    hover:ring-2 hover:ring-inset hover:ring-primary
                    has-[:checked]:bg-primary
                    has-[:checked]:text-white
                "
            >
                <input
                    type="radio"
                    name="filter-{{ $filter['id'] }}"
                    id="{{ $id }}"
                    value="{{ $option['id'] }}"
                    wire:model.live="filters.{{ $filterKey }}.active"
                    class="absolute inset-0 opacity-0 size-full cursor-pointer"
                >

                <label for="{{ $id }}" class="cursor-pointer">
                    {{ $option['name'] }}
                </label>
            </li>
        @endforeach
    </ul>
</div>
