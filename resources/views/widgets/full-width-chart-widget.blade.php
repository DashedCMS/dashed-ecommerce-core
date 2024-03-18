<script src="https://cdn.jsdelivr.net/npm/apexcharts"></script>
@php($data = $this->getData())
<x-filament::card class="col-span-full">
    <div class="flex items-center justify-between gap-8">
{{--        <x-filament::card.heading>--}}
            {{ $this->getHeading() }}
{{--        </x-filament::card.heading>--}}

        @if ($filters = $this->getFilters())
            <select
                wire:model="filter"
                class="text-gray-900 border-gray-300 block h-10 transition duration-75 rounded-lg shadow-sm focus:border-primary-600 focus:ring-1 focus:ring-inset focus:ring-primary-600"
            >
                @foreach ($filters as $value => $label)
                    <option value="{{ $value }}">
                        {{ $label }}
                    </option>
                @endforeach
            </select>
        @endif
    </div>

    <hr />

    <div>
        @php($id = 'chart' . rand(1000,10000))
        <div id="{{$id}}"></div>

        <script>
            var options = {
                chart: {
                    type: 'area',
                    height: 300,
                },
                series: @json($data['values']),
                xaxis: {
                    categories: @json($data['labels'])
                },
                colors: @json($data['colors']),
                dataLabels: {
                    enabled: false
                },
            }

            var chart = new ApexCharts(document.querySelector("#{{$id}}"), options);
            chart.render();
        </script>
    </div>
</x-filament::card>
