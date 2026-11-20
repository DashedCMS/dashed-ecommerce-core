@php($channels = $this->getChannels())
<x-filament-widgets::widget>
    <x-dashed-core::aurora.card :title="__('Omzet per verkoopkanaal')" icon="heroicon-o-chart-pie">
        @if ($channels['period'])
            <x-slot name="actions">
                <span class="au-muted" style="font-size:12px;">{{ $channels['period'] }}</span>
            </x-slot>
        @endif

        @if ($channels['rows'] === [])
            <p class="au-muted" style="margin:0;">{{ __('Nog geen betaalde bestellingen in deze periode.') }}</p>
        @else
            <div style="display:flex; flex-wrap:wrap; align-items:center; gap:24px;">
                <figure style="margin:0; position:relative; width:150px; height:150px; flex:none;">
                    <svg viewBox="0 0 42 42" width="150" height="150" role="img"
                         aria-label="{{ __('Verdeling van de omzet over :aantal kanalen', ['aantal' => count($channels['rows'])]) }}"
                         style="transform:rotate(-90deg);">
                        <circle cx="21" cy="21" r="15.915" fill="none" stroke="currentColor" stroke-opacity=".08" stroke-width="6"></circle>
                        @foreach ($channels['rows'] as $row)
                            @if ($row['percentage'] > 0)
                                <circle cx="21" cy="21" r="15.915" fill="none" stroke-width="6"
                                        style="stroke: {{ $row['color'] }};"
                                        stroke-dasharray="{{ round($row['percentage'], 3) }} {{ round(100 - $row['percentage'], 3) }}"
                                        stroke-dashoffset="{{ round(-$row['offset'], 3) }}"></circle>
                            @endif
                        @endforeach
                    </svg>
                    <figcaption style="position:absolute; inset:0; display:flex; flex-direction:column; align-items:center; justify-content:center; text-align:center;">
                        <strong style="font-size:15px;">{{ $channels['totalFormatted'] }}</strong>
                        <span class="au-muted" style="font-size:11px;">{{ __('Totaal') }}</span>
                    </figcaption>
                </figure>

                <table style="flex:1; min-width:220px; border-collapse:collapse; font-size:13px;">
                    <caption class="sr-only">{{ __('Omzet per verkoopkanaal') }}</caption>
                    <thead class="sr-only">
                        <tr>
                            <th scope="col">{{ __('Kanaal') }}</th>
                            <th scope="col">{{ __('Aandeel') }}</th>
                            <th scope="col">{{ __('Bedrag') }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($channels['rows'] as $row)
                            <tr>
                                <th scope="row" style="text-align:left; font-weight:600; padding:6px 8px 6px 0;">
                                    <span aria-hidden="true" style="display:inline-block; width:8px; height:8px; border-radius:999px; margin-right:8px; background: {{ $row['color'] }};"></span>{{ $row['label'] }}
                                </th>
                                <td style="text-align:right; padding:6px 8px; font-variant-numeric:tabular-nums;">{{ $row['percentageFormatted'] }}</td>
                                <td style="text-align:right; padding:6px 0 6px 8px; font-variant-numeric:tabular-nums;">{{ $row['amountFormatted'] }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </x-dashed-core::aurora.card>
</x-filament-widgets::widget>
