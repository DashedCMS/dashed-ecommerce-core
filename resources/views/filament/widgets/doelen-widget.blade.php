<x-filament-widgets::widget>
    <x-filament::section>
        <x-slot name="heading">Verkoopdoelen</x-slot>

        <div class="space-y-6">
            @foreach ($this->rows() as $row)
                <div>
                    <div class="text-sm font-semibold text-gray-700 dark:text-gray-200 mb-2">{{ $row['label'] }}</div>

                    @if ($row['hasTarget'])
                        @php
                            $bars = [
                                ['label' => 'Omzet', 'cur' => '€ ' . number_format($row['revenue'], 0, ',', '.'), 'tgt' => '€ ' . number_format($row['revenueTarget'], 0, ',', '.'), 'pct' => $row['revenuePct'], 'show' => $row['revenueTarget'] > 0],
                                ['label' => 'Bestellingen', 'cur' => $row['orders'], 'tgt' => $row['ordersTarget'], 'pct' => $row['ordersPct'], 'show' => $row['ordersTarget'] > 0],
                            ];
                        @endphp
                        @foreach ($bars as $bar)
                            @if ($bar['show'])
                                <div class="mb-3">
                                    <div class="flex items-center justify-between text-xs text-gray-500 dark:text-gray-400 mb-1">
                                        <span>{{ $bar['label'] }}</span>
                                        <span>{{ $bar['cur'] }} / {{ $bar['tgt'] }} · {{ $bar['pct'] }}%</span>
                                    </div>
                                    <div class="h-2 w-full rounded-full bg-gray-100 dark:bg-gray-800">
                                        <div class="h-2 rounded-full bg-primary-500" style="width: {{ min(100, $bar['pct']) }}%"></div>
                                    </div>
                                </div>
                            @endif
                        @endforeach
                    @else
                        <div class="flex items-center gap-2 text-xs text-gray-400 dark:text-gray-500">
                            <x-filament::icon icon="heroicon-o-flag" class="h-4 w-4" />
                            <span>Nog geen doel ingesteld.</span>
                        </div>
                    @endif
                </div>
            @endforeach
        </div>
    </x-filament::section>
</x-filament-widgets::widget>
