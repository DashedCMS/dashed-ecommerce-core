<x-filament::page>
    <div class="grid gap-4 sm:grid-cols-4 mb-4">
        <div>
            <label class="block text-sm font-medium mb-1">{{ __('Periode') }}</label>
            <select wire:model.live="days" class="fi-input block w-full rounded-lg border-gray-300 dark:bg-gray-800">
                @foreach(\Dashed\DashedEcommerceCore\Classes\PurchaseAdvice::PERIODS as $periode)
                    <option value="{{ $periode }}">{{ __('Laatste :aantal dagen', ['aantal' => $periode]) }}</option>
                @endforeach
            </select>
        </div>
        <div>
            <label class="block text-sm font-medium mb-1">{{ __('Horizon in dagen') }}</label>
            <input type="number" min="1" max="365" wire:model.live.debounce.500ms="horizon" class="fi-input block w-full rounded-lg border-gray-300 dark:bg-gray-800">
        </div>
        <div>
            <label class="block text-sm font-medium mb-1">{{ __('Categorie') }}</label>
            <select wire:model.live="categoryId" class="fi-input block w-full rounded-lg border-gray-300 dark:bg-gray-800">
                <option value="">{{ __('Alle categorieën') }}</option>
                @foreach($this->categoryOptions() as $id => $naam)
                    <option value="{{ $id }}">{{ $naam }}</option>
                @endforeach
            </select>
        </div>
        <label class="flex items-end gap-2 text-sm pb-2">
            <input type="checkbox" wire:model.live="onlyWithinHorizon" class="rounded border-gray-300">
            {{ __('Alleen wat binnen de horizon op is') }}
        </label>
    </div>

    @php($rows = $this->rows())

    <x-filament::section>
        <x-slot name="heading">{{ __('Inkoopadvies') }} ({{ count($rows) }})</x-slot>
        <x-slot name="description">{{ __('Uit de verkoop van betaalde bestellingen in de gekozen periode. Het advies is wat er bij moet om de horizon te halen.') }}</x-slot>

        @if(count($rows) === 0)
            <p class="text-sm text-gray-500">{{ __('Geen producten om in te kopen met deze filters.') }}</p>
        @else
            <table class="w-full text-sm">
                <thead>
                    <tr class="text-left text-gray-500 border-b border-gray-200 dark:border-gray-700">
                        <th class="py-2">{{ __('Product') }}</th>
                        <th class="py-2">{{ __('SKU') }}</th>
                        <th class="py-2 text-right">{{ __('Voorraad') }}</th>
                        <th class="py-2 text-right">{{ __('Verkocht') }}</th>
                        <th class="py-2 text-right">{{ __('Per dag') }}</th>
                        <th class="py-2 text-right">{{ __('Dagen tot op') }}</th>
                        <th class="py-2 text-right">{{ __('Advies') }}</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($rows as $row)
                        <tr class="border-b border-gray-100 dark:border-gray-800">
                            <td class="py-2">{{ $row['name'] }}</td>
                            <td class="py-2 text-gray-500">{{ $row['sku'] }}</td>
                            <td class="py-2 text-right">{{ $row['stock'] }}</td>
                            <td class="py-2 text-right">{{ $row['sold'] }}</td>
                            <td class="py-2 text-right">{{ number_format($row['per_day'], 2, ',', '.') }}</td>
                            <td class="py-2 text-right {{ $row['days_left'] !== null && $row['days_left'] <= 7 ? 'text-danger-600 font-medium' : '' }}">{{ $row['days_left'] ?? '-' }}</td>
                            <td class="py-2 text-right font-medium">{{ $row['advice'] }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        @endif
    </x-filament::section>
</x-filament::page>
