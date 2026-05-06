<x-filament::page>

    <form wire:submit.prevent="submit" method="POST">
        {{ $this->form }}
    </form>

    @php($s = $this->stats)
    @php($period = $s['period'] ?? null)
    @php($totals = $s['totals'] ?? null)

    @if($period && $totals)
        <div class="grid grid-cols-1 gap-4 md:grid-cols-4 mt-6">
            <div class="rounded-lg border border-gray-200 bg-white p-4 dark:border-gray-700 dark:bg-gray-900">
                <div class="text-xs text-gray-500 dark:text-gray-400">Periode</div>
                <div class="text-base font-medium">{{ $period['start'] }} t/m {{ $period['end'] }}</div>
            </div>
            <div class="rounded-lg border border-gray-200 bg-white p-4 dark:border-gray-700 dark:bg-gray-900">
                <div class="text-xs text-gray-500 dark:text-gray-400">Bestellingen</div>
                <div class="text-2xl font-semibold">{{ $totals['orders'] }}</div>
            </div>
            <div class="rounded-lg border border-gray-200 bg-white p-4 dark:border-gray-700 dark:bg-gray-900">
                <div class="text-xs text-gray-500 dark:text-gray-400">Omzet</div>
                <div class="text-2xl font-semibold">{{ $totals['revenue'] }}</div>
            </div>
            <div class="rounded-lg border border-gray-200 bg-white p-4 dark:border-gray-700 dark:bg-gray-900">
                <div class="text-xs text-gray-500 dark:text-gray-400">Met herkomst-data</div>
                <div class="text-2xl font-semibold">{{ $totals['with_utm'] }} <span class="text-sm text-gray-400">({{ $totals['with_utm_percentage'] }}%)</span></div>
                <div class="text-xs text-gray-500">Zonder UTM: {{ $totals['without_utm'] }}</div>
            </div>
        </div>

        <div class="grid grid-cols-1 gap-6 lg:grid-cols-3 mt-6">
            @foreach([
                ['title' => 'Top bronnen', 'rows' => $s['by_source'] ?? [], 'empty' => 'Geen orders met utm_source in deze periode.'],
                ['title' => 'Top mediums', 'rows' => $s['by_medium'] ?? [], 'empty' => 'Geen orders met utm_medium in deze periode.'],
                ['title' => 'Top campagnes', 'rows' => $s['by_campaign'] ?? [], 'empty' => 'Geen orders met utm_campaign in deze periode.'],
            ] as $block)
                <div class="rounded-lg border border-gray-200 bg-white p-4 dark:border-gray-700 dark:bg-gray-900">
                    <div class="mb-3 flex items-center justify-between">
                        <h3 class="text-base font-semibold">{{ $block['title'] }}</h3>
                    </div>
                    @if(empty($block['rows']))
                        <div class="text-sm text-gray-500">{{ $block['empty'] }}</div>
                    @else
                        <table class="w-full text-sm">
                            <thead class="border-b text-left text-xs uppercase text-gray-500 dark:border-gray-700 dark:text-gray-400">
                                <tr>
                                    <th class="py-2">Naam</th>
                                    <th class="py-2 text-right">Orders</th>
                                    <th class="py-2 text-right">Omzet</th>
                                    <th class="py-2 text-right">Aandeel</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach($block['rows'] as $row)
                                    <tr class="border-b last:border-b-0 dark:border-gray-700">
                                        <td class="py-2">
                                            <div class="font-medium">{{ $row['label'] }}</div>
                                            <div class="text-xs text-gray-500">Gem. {{ $row['avg_order_value'] }}</div>
                                        </td>
                                        <td class="py-2 text-right tabular-nums">{{ $row['orders'] }}</td>
                                        <td class="py-2 text-right tabular-nums">{{ $row['revenue'] }}</td>
                                        <td class="py-2 text-right tabular-nums">{{ $row['revenue_share'] }}%</td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    @endif
                </div>
            @endforeach
        </div>
    @endif

</x-filament::page>
