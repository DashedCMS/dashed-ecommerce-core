<x-filament-panels::page>
    <div class="space-y-6">
        <div class="fi-section rounded-xl bg-white p-5 ring-1 ring-gray-950/5 dark:bg-gray-900 dark:ring-white/10">
            {{ $this->form }}
        </div>

        @php
            $strategies = $this->explanation['strategies'] ?? [];
            $ranking = $this->explanation['ranking'] ?? [];
        @endphp

        @if(empty($strategies) && empty($ranking))
            <div class="fi-section rounded-xl bg-white p-5 ring-1 ring-gray-950/5 dark:bg-gray-900 dark:ring-white/10">
                <p class="text-sm text-gray-600 dark:text-gray-400">
                    Kies een product + placement om de strategie-uitsplitsing en ranking te zien.
                </p>
            </div>
        @else
            <div class="fi-section rounded-xl bg-white p-5 ring-1 ring-gray-950/5 dark:bg-gray-900 dark:ring-white/10">
                <h2 class="mb-3 text-base font-semibold">Final ranking</h2>
                @if(empty($ranking))
                    <p class="text-sm text-gray-500">Geen producten gerankt voor deze input.</p>
                @else
                    <table class="min-w-full divide-y divide-gray-200 text-sm dark:divide-white/10">
                        <thead><tr class="text-left">
                            <th class="py-2 pr-4">#</th>
                            <th class="py-2 pr-4">Product</th>
                            <th class="py-2 pr-4">Score</th>
                            <th class="py-2 pr-4">Reasons</th>
                        </tr></thead>
                        <tbody class="divide-y divide-gray-100 dark:divide-white/5">
                            @foreach($ranking as $i => $row)
                                <tr>
                                    <td class="py-1 pr-4 text-xs text-gray-400">{{ $i + 1 }}</td>
                                    <td class="py-1 pr-4">{{ $row['name'] }} <span class="text-xs text-gray-400">#{{ $row['product_id'] }}</span></td>
                                    <td class="py-1 pr-4 font-mono">{{ $row['score'] }}</td>
                                    <td class="py-1 pr-4 text-xs text-gray-500">{{ implode(' | ', $row['reasons']) }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                @endif
            </div>

            @foreach($strategies as $strategyKey => $rows)
                <div class="fi-section rounded-xl bg-white p-5 ring-1 ring-gray-950/5 dark:bg-gray-900 dark:ring-white/10">
                    <h2 class="mb-3 text-base font-semibold">
                        Strategy: <code class="text-sm">{{ $strategyKey }}</code>
                        <span class="ml-2 text-xs text-gray-500">{{ count($rows) }} candidates</span>
                    </h2>
                    @if(empty($rows))
                        <p class="text-sm text-gray-500">Deze strategy heeft geen candidates teruggegeven (appliesTo=false of leeg resultaat).</p>
                    @else
                        <table class="min-w-full divide-y divide-gray-200 text-sm dark:divide-white/10">
                            <thead><tr class="text-left">
                                <th class="py-2 pr-4">Product</th>
                                <th class="py-2 pr-4">Raw</th>
                                <th class="py-2 pr-4">Weighted</th>
                                <th class="py-2 pr-4">Reasons</th>
                            </tr></thead>
                            <tbody class="divide-y divide-gray-100 dark:divide-white/5">
                                @foreach($rows as $row)
                                    <tr>
                                        <td class="py-1 pr-4">{{ $row['name'] }} <span class="text-xs text-gray-400">#{{ $row['product_id'] }}</span></td>
                                        <td class="py-1 pr-4 font-mono">{{ $row['raw'] }}</td>
                                        <td class="py-1 pr-4 font-mono">{{ $row['weighted'] }}</td>
                                        <td class="py-1 pr-4 text-xs text-gray-500">{{ implode(' | ', $row['reasons']) }}</td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    @endif
                </div>
            @endforeach
        @endif
    </div>
</x-filament-panels::page>
