<x-filament-widgets::widget>
    <x-filament::section>
        <x-slot name="heading">{{ __('Productweergaven, winkelwagen en checkout: laatste 7 dagen') }}</x-slot>
        <x-slot name="description">{{ __('Deze events krijgen geen logregel per stuk. Hieronder per dag hoeveel er naar Meta zijn verstuurd; mislukte verzendingen staan in het rood en hoogstens één keer per uur in de tabel.') }}</x-slot>

        <div class="overflow-x-auto">
            <table class="w-full text-sm">
                <thead>
                    <tr class="text-left text-gray-500 dark:text-gray-400">
                        <th class="py-1 pr-4 font-medium">{{ __('Dag') }}</th>
                        @foreach ($eventNames as $eventName)
                            <th class="py-1 pr-4 font-medium">{{ $eventName }}</th>
                        @endforeach
                    </tr>
                </thead>
                <tbody>
                    @foreach ($days as $day)
                        <tr class="border-t border-gray-100 dark:border-gray-800">
                            <td class="py-1 pr-4 whitespace-nowrap">{{ \Illuminate\Support\Carbon::parse($day['date'])->format('d-m-Y') }}</td>
                            @foreach ($eventNames as $eventName)
                                <?php $count = $day['events'][$eventName] ?? ['sent' => 0, 'failed' => 0]; ?>
                                <td class="py-1 pr-4 whitespace-nowrap">
                                    {{ number_format($count['sent'], 0, ',', '.') }}
                                    @if ($count['failed'] > 0)
                                        <span class="text-red-600 dark:text-red-400">({{ number_format($count['failed'], 0, ',', '.') }} {{ __('mislukt') }})</span>
                                    @endif
                                </td>
                            @endforeach
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </x-filament::section>
</x-filament-widgets::widget>
