<x-filament-panels::page>
    <div class="space-y-8">
        {{-- Cashflow --}}
        <div class="space-y-3">
            <h2 class="text-base font-semibold text-gray-950 dark:text-white">Cashflow</h2>
            <div class="grid grid-cols-2 gap-4 sm:grid-cols-3">
                @php
                    $cards = [
                        ['Omzet vandaag', $this->euro($cashflow['revenue_today'] ?? 0), null],
                        ['Omzet deze maand', $this->euro($cashflow['revenue_month'] ?? 0), null],
                        ['Nog te ontvangen', $this->euro($cashflow['outstanding'] ?? 0), ($cashflow['outstanding'] ?? 0) > 0 ? 'text-danger-600 dark:text-danger-400' : null],
                        ['Btw deze maand', $this->euro($cashflow['vat_month'] ?? 0), null],
                        ['Bestellingen (maand)', (string) ($cashflow['orders_month'] ?? 0), null],
                        ['Gem. orderwaarde', $this->euro($cashflow['average_order_value'] ?? 0), null],
                    ];
                @endphp
                @foreach ($cards as [$label, $value, $tone])
                    <div class="fi-section rounded-xl bg-white p-4 ring-1 ring-gray-950/5 dark:bg-gray-900 dark:ring-white/10">
                        <div class="text-xs text-gray-500 dark:text-gray-400">{{ $label }}</div>
                        <div class="mt-1 text-2xl font-bold {{ $tone ?? 'text-gray-950 dark:text-white' }}">{{ $value }}</div>
                    </div>
                @endforeach
            </div>
        </div>

        {{-- Inkoopadvies --}}
        <div class="space-y-3">
            <h2 class="text-base font-semibold text-gray-950 dark:text-white">Inkoopadvies</h2>
            <p class="text-sm text-gray-500 dark:text-gray-400">
                Op basis van verkoopsnelheid (laatste {{ $meta['velocity_days'] ?? 30 }} dagen). Getoond: producten die binnen {{ $meta['horizon_days'] ?? 21 }} dagen opraken. Advies dekt {{ $meta['cover_days'] ?? 30 }} dagen.
            </p>

            @if (empty($reorder))
                <div class="fi-section flex items-center gap-2 rounded-xl bg-white p-5 text-sm text-gray-500 ring-1 ring-gray-950/5 dark:bg-gray-900 dark:text-gray-400 dark:ring-white/10">
                    <x-filament::icon icon="heroicon-o-check-circle" class="h-5 w-5 text-success-500" />
                    Niets dringend bij te bestellen — geen hardlopers die binnenkort opraken.
                </div>
            @else
                <div class="fi-section overflow-hidden rounded-xl bg-white ring-1 ring-gray-950/5 dark:bg-gray-900 dark:ring-white/10">
                    <table class="w-full text-sm">
                        <thead class="border-b border-gray-200 text-left text-xs uppercase text-gray-500 dark:border-white/10 dark:text-gray-400">
                            <tr>
                                <th class="px-4 py-2 font-medium">Product</th>
                                <th class="px-4 py-2 font-medium text-right">Voorraad</th>
                                <th class="px-4 py-2 font-medium text-right">Verkoop/week</th>
                                <th class="px-4 py-2 font-medium text-right">Nog</th>
                                <th class="px-4 py-2 font-medium text-right">Bestel</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100 dark:divide-white/5">
                            @foreach ($reorder as $r)
                                <tr>
                                    <td class="px-4 py-2.5">
                                        <div class="font-medium text-gray-950 dark:text-white">{{ $r['name'] }}</div>
                                        @if (!empty($r['sku']))
                                            <div class="text-xs text-gray-500 dark:text-gray-400">{{ $r['sku'] }}</div>
                                        @endif
                                    </td>
                                    <td class="px-4 py-2.5 text-right tabular-nums {{ ($r['stock'] ?? 0) <= 0 ? 'text-danger-600 dark:text-danger-400 font-semibold' : 'text-gray-700 dark:text-gray-300' }}">{{ $r['stock'] }}</td>
                                    <td class="px-4 py-2.5 text-right tabular-nums text-gray-700 dark:text-gray-300">~{{ $r['per_week'] }}</td>
                                    <td class="px-4 py-2.5 text-right font-semibold tabular-nums {{ ($r['days_left'] ?? 0) <= 7 ? 'text-danger-600 dark:text-danger-400' : 'text-warning-600 dark:text-warning-400' }}">
                                        {{ ($r['days_left'] ?? 0) === 0 ? 'op' : $r['days_left'] . 'd' }}
                                    </td>
                                    <td class="px-4 py-2.5 text-right">
                                        <span class="inline-flex items-center rounded-full bg-primary-50 px-2.5 py-0.5 text-xs font-semibold text-primary-700 dark:bg-primary-400/10 dark:text-primary-400">+{{ $r['suggested_qty'] }}</span>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </div>
    </div>
</x-filament-panels::page>
