<x-filament-widgets::widget>
    <x-filament::section>
        <x-slot name="heading">{{ __('Per betaalmethode') }}</x-slot>

        @php($rows = $this->graphData['rows'] ?? [])

        @if (count($rows))
            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead>
                        <tr class="text-left text-gray-500 dark:text-gray-400 border-b border-gray-200 dark:border-white/10">
                            <th class="py-2 pr-4 font-medium">{{ __('Betaalmethode') }}</th>
                            <th class="py-2 px-4 font-medium text-right">{{ __('Pogingen') }}</th>
                            <th class="py-2 px-4 font-medium text-right">{{ __('Betaald') }}</th>
                            <th class="py-2 px-4 font-medium text-right">{{ __('Mislukt of geannuleerd') }}</th>
                            <th class="py-2 px-4 font-medium text-right">{{ __('Open') }}</th>
                            <th class="py-2 px-4 font-medium text-right">{{ __('Slagingspercentage') }}</th>
                            <th class="py-2 pl-4 font-medium text-right">{{ __('Betaald bedrag') }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($rows as $row)
                            <tr class="border-b border-gray-100 dark:border-white/5">
                                <td class="py-2 pr-4 font-medium">{{ $row['name'] }}</td>
                                <td class="py-2 px-4 text-right tabular-nums">{{ $row['attempts'] }}</td>
                                <td class="py-2 px-4 text-right tabular-nums">
                                    @if ($row['paid_url'] && $row['paid'])
                                        <a href="{{ $row['paid_url'] }}" class="text-primary-600 hover:underline">{{ $row['paid'] }}</a>
                                    @else
                                        {{ $row['paid'] }}
                                    @endif
                                </td>
                                <td class="py-2 px-4 text-right tabular-nums">
                                    @if ($row['failed_url'] && $row['failed'])
                                        <a href="{{ $row['failed_url'] }}" class="text-primary-600 hover:underline">{{ $row['failed'] }}</a>
                                    @else
                                        {{ $row['failed'] }}
                                    @endif
                                </td>
                                <td class="py-2 px-4 text-right tabular-nums">{{ $row['open'] }}</td>
                                <td class="py-2 px-4 text-right tabular-nums">
                                    {{ $row['success_rate'] !== null ? number_format($row['success_rate'], 1, ',', '.') . '%' : '-' }}
                                </td>
                                <td class="py-2 pl-4 text-right tabular-nums">{{ \Dashed\DashedEcommerceCore\Classes\CurrencyHelper::formatPrice($row['amount']) }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>

            <p class="mt-4 text-xs text-gray-500 dark:text-gray-400">
                {{ __('Een poging telt op de datum van de betaling; de datumfilter van de bestellingenlijst gaat over de besteldatum. Aan de randen van de periode kan de lijst daardoor iets anders tonen dan het getal hier.') }}
            </p>
        @else
            <p class="text-sm text-gray-500 dark:text-gray-400">{{ __('Geen betaalpogingen in deze periode.') }}</p>
        @endif
    </x-filament::section>
</x-filament-widgets::widget>
