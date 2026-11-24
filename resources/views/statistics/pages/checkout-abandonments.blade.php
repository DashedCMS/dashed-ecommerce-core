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

    @php($recent = $this->recent())

    <x-filament::section>
        <x-slot name="heading">
            Laatste gevallen ({{ $recent->count() }})
        </x-slot>
        <x-slot name="description">
            Per winkelwagen, met de velden die afgekeurd werden en wat er ingevuld stond.
        </x-slot>

        @if($recent->isEmpty())
            <p class="text-sm text-gray-500">Geen uitval geregistreerd in deze periode.</p>
        @else
            <table class="w-full text-sm">
                <thead>
                    <tr class="text-left text-gray-500 border-b border-gray-200 dark:border-gray-700">
                        <th class="py-2 pr-3">Laatste poging</th>
                        <th class="py-2 pr-3">Winkelwagen</th>
                        <th class="py-2 pr-3">Reden</th>
                        <th class="py-2 pr-3 text-right">Pogingen</th>
                        <th class="py-2">Fouten</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($recent as $abandonment)
                        @php($url = $this->cartUrl($abandonment->cart_id))
                        <tr class="border-b border-gray-100 dark:border-gray-800 align-top">
                            <td class="py-2 pr-3 whitespace-nowrap">{{ $abandonment->updated_at?->timezone('Europe/Amsterdam')->format('d-m-Y H:i') }}</td>
                            <td class="py-2 pr-3 whitespace-nowrap">
                                @if($url)
                                    <a href="{{ $url }}" class="text-primary-600 underline">#{{ $abandonment->cart_id }}</a>
                                @else
                                    -
                                @endif
                                <div class="text-xs text-gray-500">{{ $abandonment->email }}</div>
                            </td>
                            <td class="py-2 pr-3">{{ $this->reasonLabels()[$abandonment->reason] ?? $abandonment->reason }}</td>
                            <td class="py-2 pr-3 text-right">{{ $abandonment->attempts() }}</td>
                            <td class="py-2">
                                @foreach($abandonment->errorLines() as $line)
                                    <div>{{ $line }}</div>
                                @endforeach
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        @endif
    </x-filament::section>
</x-filament::page>
