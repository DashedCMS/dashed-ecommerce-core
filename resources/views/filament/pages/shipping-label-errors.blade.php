<x-filament-panels::page>
    @php($rows = $this->rows())

    @if (count($rows) === 0)
        <x-filament::section>
            <p class="text-sm text-gray-500">Er zijn momenteel geen labels met fouten.</p>
        </x-filament::section>
    @else
        <x-filament::section>
            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead>
                        <tr class="text-left">
                            <th class="py-2 pr-4">Provider</th>
                            <th class="py-2 pr-4">Bestelling</th>
                            <th class="py-2 pr-4">Foutmelding</th>
                            <th class="py-2 pr-4"></th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($rows as $row)
                            <tr class="border-t border-gray-200 dark:border-gray-700 align-top">
                                <td class="py-2 pr-4 whitespace-nowrap">{{ $row['provider_label'] }}</td>
                                <td class="py-2 pr-4 whitespace-nowrap">{{ $row['invoice_id'] ?? '-' }}</td>
                                <td class="py-2 pr-4">{{ $row['error'] }}</td>
                                <td class="py-2 pr-4 whitespace-nowrap">
                                    <div class="flex items-center gap-2">
                                        @if ($row['order_id'])
                                            <x-filament::button
                                                tag="a"
                                                size="xs"
                                                color="gray"
                                                href="{{ route('filament.dashed.resources.orders.view', ['record' => $row['order_id']]) }}"
                                                target="_blank"
                                            >
                                                Naar bestelling
                                            </x-filament::button>
                                        @endif
                                        <x-filament::button
                                            size="xs"
                                            wire:click="retry('{{ $row['provider_key'] }}', {{ $row['id'] }})"
                                        >
                                            Opnieuw in wachtrij
                                        </x-filament::button>
                                    </div>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </x-filament::section>
    @endif
</x-filament-panels::page>
