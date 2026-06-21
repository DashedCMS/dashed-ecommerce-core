<x-filament::page>
    <div class="mb-4 max-w-xs">
        <label class="block text-sm font-medium mb-1">Periode</label>
        <select wire:model.live="days"
                class="fi-input block w-full rounded-lg border-gray-300 dark:bg-gray-800">
            <option value="7">Laatste 7 dagen</option>
            <option value="30">Laatste 30 dagen</option>
            <option value="90">Laatste 90 dagen</option>
            <option value="365">Laatste 365 dagen</option>
        </select>
    </div>

    @php($rows = $this->rows())

    <x-filament::section>
        <x-slot name="heading">
            Redenen dat klanten niet afrekenden ({{ $this->total() }} gevallen)
        </x-slot>
        <x-slot name="description">
            Geregistreerd op het moment dat de checkout strandde vóórdat er een bestelling werd aangemaakt.
        </x-slot>

        @if(count($rows) === 0)
            <p class="text-sm text-gray-500">Geen uitval geregistreerd in deze periode.</p>
        @else
            <table class="w-full text-sm">
                <thead>
                    <tr class="text-left text-gray-500 border-b border-gray-200 dark:border-gray-700">
                        <th class="py-2">Reden</th>
                        <th class="py-2 text-right">Aantal</th>
                        <th class="py-2 text-right">Aandeel</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($rows as $row)
                        <tr class="border-b border-gray-100 dark:border-gray-800">
                            <td class="py-2">{{ $row['label'] }}</td>
                            <td class="py-2 text-right font-medium">{{ $row['count'] }}</td>
                            <td class="py-2 text-right text-gray-500">{{ $row['share'] }}%</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        @endif
    </x-filament::section>
</x-filament::page>
