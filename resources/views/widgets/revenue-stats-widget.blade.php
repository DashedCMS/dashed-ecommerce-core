<x-filament::stats :columns="$this->getColumns()" class="col-span-full">
    @foreach($this->getCards() as $data)
        <div class="bg-white overflow-hidden shadow rounded-lg">
            <div class="py-4 px-6">
                <dl>
                    <dt class="text-sm leading-5 font-medium text-gray-500 truncate">
                        {{ $data['name'] }}
                    </dt>
                    <dd class="mt-1 text-3xl leading-9 text-gray-900">
                        {{ $data['number'] }}
                    </dd>
                    @if($data['retourNumber'] != 0 && $data['retourNumber'] != '€0,-')
                        <span class="text-sm font-medium text-gray-500">
                                  {{ $data['retourNumber'] }} retour
                                </span>
                    @endif
                </dl>
            </div>
        </div>
    @endforeach
</x-filament::stats>
