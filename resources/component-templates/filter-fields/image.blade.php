<div>
    <label for="filter-{{$filter['id']}}"
           class="inline-block text-md font-bold mb-2">
        {{$filter['name']}}
    </label>
    <div class="grid grid-cols-4 sm:grid-cols-5 md:grid-cols-6 gap-4">
        @foreach($filter['options'] as $option)
            <div class="grid items-center cursor-pointer relative"
                 wire:click="setFilterValue({{ $filterKey }}, {{$option['id']}})">
                @if($filter['active'] == $option['id'])
                    <div class="absolute top-1 right-1 text-white bg-green-500 rounded-full">
                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24"
                             stroke-width="1.5" stroke="currentColor" class="w-6 h-6">
                            <path stroke-linecap="round" stroke-linejoin="round"
                                  d="M9 12.75 11.25 15 15 9.75M21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z"/>
                        </svg>
                    </div>
                @endif
                <x-dashed-files::image
                    class="w-full h-full"
                    config="dashed"
                    :mediaId="$option['image']"
                    :alt="$option['name']"
                    :manipulations="[
                                                'fit' => [150,150],
                                            ]"
                />
                <span class="font-brand text-center">{{$option['name']}}</span>
            </div>
        @endforeach
    </div>
</div>
