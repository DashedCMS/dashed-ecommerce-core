<x-filament-widgets::widget>
    <x-filament::section>
        <x-slot name="heading">Verkoopdoelen</x-slot>

        @php
            // Omtrek van de ring (r = 40) — voor stroke-dasharray/offset.
            $circumference = 2 * M_PI * 40;
        @endphp

        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
            @foreach ($this->rows() as $row)
                <div class="rounded-xl border border-gray-200 dark:border-white/10 bg-white dark:bg-gray-900 shadow-sm p-4">
                    <div class="text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400 mb-4">
                        {{ $row['label'] }}
                    </div>

                    @if ($row['hasTarget'])
                        @php
                            $gauges = [];
                            if ($row['revenueTarget'] > 0) {
                                $gauges[] = [
                                    'label' => 'Omzet',
                                    'pct' => $row['revenuePct'],
                                    'cur' => '€ ' . number_format($row['revenue'], 0, ',', '.'),
                                    'tgt' => '€ ' . number_format($row['revenueTarget'], 0, ',', '.'),
                                ];
                            }
                            if ($row['ordersTarget'] > 0) {
                                $gauges[] = [
                                    'label' => 'Bestellingen',
                                    'pct' => $row['ordersPct'],
                                    'cur' => $row['orders'],
                                    'tgt' => $row['ordersTarget'],
                                ];
                            }
                        @endphp

                        <div class="flex items-start justify-around gap-4">
                            @foreach ($gauges as $g)
                                @php
                                    $shown = min(100, max(0, $g['pct']));
                                    $offset = $circumference * (1 - $shown / 100);
                                    $reached = $g['pct'] >= 100;
                                    $accent = $reached ? 'var(--success-500)' : 'var(--primary-500)';
                                @endphp
                                <div class="flex flex-col items-center gap-2">
                                    <div class="relative h-24 w-24">
                                        <svg viewBox="0 0 96 96" class="h-24 w-24">
                                            {{-- Track --}}
                                            <circle cx="48" cy="48" r="40" fill="none" stroke-width="9"
                                                class="text-gray-200 dark:text-gray-700" stroke="currentColor" />
                                            {{-- Voortgang --}}
                                            <circle cx="48" cy="48" r="40" fill="none" stroke-width="9"
                                                stroke-linecap="round"
                                                transform="rotate(-90 48 48)"
                                                style="stroke: rgb({{ $accent }}); stroke-dasharray: {{ $circumference }}; stroke-dashoffset: {{ $offset }}; transition: stroke-dashoffset .5s ease;" />
                                        </svg>
                                        <div class="absolute inset-0 flex items-center justify-center">
                                            <span class="text-lg font-semibold"
                                                style="{{ $reached ? 'color: rgb(var(--success-600));' : '' }}"
                                                @class(['text-gray-900 dark:text-white' => ! $reached])>
                                                {{ $g['pct'] }}%
                                            </span>
                                        </div>
                                    </div>
                                    <div class="text-center">
                                        <div class="text-sm font-medium text-gray-700 dark:text-gray-200">{{ $g['label'] }}</div>
                                        <div class="text-xs text-gray-500 dark:text-gray-400">{{ $g['cur'] }} / {{ $g['tgt'] }}</div>
                                    </div>
                                </div>
                            @endforeach
                        </div>
                    @else
                        <div class="flex flex-col items-center justify-center gap-2 py-6 text-center">
                            <x-filament::icon icon="heroicon-o-flag" class="h-6 w-6 text-gray-300 dark:text-gray-600" />
                            <span class="text-xs text-gray-400 dark:text-gray-500">Nog geen doel ingesteld.</span>
                        </div>
                    @endif
                </div>
            @endforeach
        </div>
    </x-filament::section>
</x-filament-widgets::widget>
