<x-filament-widgets::widget>
    <x-filament::section>
        <x-slot name="heading">Print queue</x-slot>

        <div class="grid grid-cols-2 gap-4">
            <div class="rounded-lg bg-gray-50 dark:bg-gray-800 p-4">
                <div class="text-sm text-gray-500 dark:text-gray-400">In wachtrij</div>
                <div class="text-3xl font-semibold">{{ $pendingCount }}</div>
            </div>
            <div @class([
                'rounded-lg p-4',
                'bg-red-50 dark:bg-red-900/30' => $failedToday > 0,
                'bg-gray-50 dark:bg-gray-800' => $failedToday === 0,
            ])>
                <div class="text-sm text-gray-500 dark:text-gray-400">Mislukt vandaag</div>
                <div @class([
                    'text-3xl font-semibold',
                    'text-red-700 dark:text-red-300' => $failedToday > 0,
                ])>{{ $failedToday }}</div>
            </div>
        </div>

        @if ($printers->isNotEmpty())
            <div class="mt-4 space-y-2">
                @foreach ($printers as $printer)
                    <div class="flex items-center justify-between rounded-md border border-gray-200 dark:border-gray-700 p-3">
                        <div class="flex items-center gap-3">
                            <span @class([
                                'h-2 w-2 rounded-full',
                                'bg-green-500' => $printer->isOnline(),
                                'bg-gray-300' => ! $printer->isOnline(),
                            ])></span>
                            <span class="font-medium">{{ $printer->name }}</span>
                            <span class="text-sm text-gray-500">{{ $printer->location }}</span>
                        </div>
                        <div class="flex items-center gap-4 text-sm">
                            <span>{{ $printer->pending_count }} pending</span>
                            <span class="text-gray-500">{{ $printer->last_ping_at?->diffForHumans() ?? 'nooit gepingd' }}</span>
                        </div>
                    </div>
                @endforeach
            </div>
        @endif
    </x-filament::section>
</x-filament-widgets::widget>
