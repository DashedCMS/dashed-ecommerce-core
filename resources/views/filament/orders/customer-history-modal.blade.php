@php
    use Dashed\DashedEcommerceCore\Classes\CurrencyHelper;
    $aggregates = [
        'Totaal aantal bestellingen' => $history->totalCount(),
        'Betaalde bestellingen' => $history->paidCount(),
        'Totaal besteed' => CurrencyHelper::formatPrice($history->lifetimeSpent()),
        'Gemiddelde orderwaarde' => $history->paidCount() > 0
            ? CurrencyHelper::formatPrice($history->averageOrderValue())
            : '—',
        'Eerste bestelling' => $history->firstOrderAt()?->format('d-m-Y') ?? '—',
        'Laatste bestelling' => $history->lastOrderAt()?->format('d-m-Y') ?? '—',
        'Dagen sinds laatste' => match (true) {
            $history->daysSinceLastOrder() === null => '—',
            $history->daysSinceLastOrder() === 0 => 'Vandaag',
            default => $history->daysSinceLastOrder() . ' dag(en) geleden',
        },
        'Favoriete betaalmethode' => $history->favoritePaymentMethod() ?? '—',
        'Klant-type' => $history->customerType(),
    ];
    $recent = $history->recentOrders(10);
    $anchorId = $history->anchor->id;
@endphp

<div class="space-y-6">
    <div>
        <h3 class="text-sm font-semibold text-gray-700 dark:text-gray-200 mb-2">Overzicht</h3>
        <dl class="grid grid-cols-2 gap-x-6 gap-y-2 text-sm">
            @foreach ($aggregates as $label => $value)
                <dt class="text-gray-500 dark:text-gray-400">{{ $label }}</dt>
                <dd class="text-gray-900 dark:text-gray-100 font-medium">{{ $value }}</dd>
            @endforeach
        </dl>
    </div>

    <div>
        <h3 class="text-sm font-semibold text-gray-700 dark:text-gray-200 mb-2">Laatste 10 bestellingen</h3>

        @if ($recent->isEmpty())
            <p class="text-sm text-gray-500">Geen bestellingen gevonden.</p>
        @else
            <div class="overflow-x-auto rounded border border-gray-200 dark:border-gray-700">
                <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700 text-sm">
                    <thead class="bg-gray-50 dark:bg-gray-800 text-left text-gray-500">
                        <tr>
                            <th class="px-3 py-2 font-medium">Datum</th>
                            <th class="px-3 py-2 font-medium">Order</th>
                            <th class="px-3 py-2 font-medium">Status</th>
                            <th class="px-3 py-2 font-medium">Methode</th>
                            <th class="px-3 py-2 font-medium text-right">Totaal</th>
                            <th class="px-3 py-2"></th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-200 dark:divide-gray-700">
                        @foreach ($recent as $row)
                            <tr class="{{ $row->id === $anchorId ? 'bg-primary-50 dark:bg-primary-900/20' : '' }}">
                                <td class="px-3 py-2 whitespace-nowrap">{{ $row->created_at?->format('d-m-Y') }}</td>
                                <td class="px-3 py-2 whitespace-nowrap">
                                    #{{ $row->invoice_id }}
                                    @if ($row->id === $anchorId)
                                        <span class="ml-1 text-xs px-1.5 py-0.5 rounded bg-primary-100 text-primary-700">Huidig</span>
                                    @endif
                                </td>
                                <td class="px-3 py-2 whitespace-nowrap">{{ ucfirst(str_replace('_', ' ', $row->status ?? '')) }}</td>
                                <td class="px-3 py-2 whitespace-nowrap">{{ $row->payment_method ?? '—' }}</td>
                                <td class="px-3 py-2 whitespace-nowrap text-right">{{ CurrencyHelper::formatPrice((float) $row->total) }}</td>
                                <td class="px-3 py-2 whitespace-nowrap text-right">
                                    @if ($row->id !== $anchorId)
                                        <a href="{{ route('filament.dashed.resources.orders.view', ['record' => $row->id]) }}"
                                           class="text-primary-600 hover:text-primary-700 inline-flex items-center">
                                            →
                                        </a>
                                    @endif
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </div>
</div>
