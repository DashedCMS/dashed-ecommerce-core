@php
    use Dashed\DashedEcommerceCore\Models\Order;
    $concepts = Order::concept()
        ->with(['orderProducts', 'user'])
        ->latest()
        ->get();
@endphp

<div class="space-y-3 text-gray-900 dark:text-gray-100">
    @forelse ($concepts as $concept)
        <div class="flex items-center justify-between border border-gray-200 dark:border-gray-700 rounded p-3 bg-white dark:bg-gray-900">
            <div>
                <div class="font-medium text-gray-900 dark:text-gray-100">
                    #{{ $concept->id }} -
                    {{ $concept->orderProducts->sum('quantity') }} items -
                    € {{ number_format($concept->total, 2, ',', '.') }}
                </div>
                <div class="text-sm text-gray-600 dark:text-gray-400">
                    {{ $concept->created_at->diffForHumans() }}
                    @if ($concept->user)
                        · {{ $concept->user->name ?? $concept->user->email }}
                    @endif
                </div>
            </div>
            <div class="flex gap-2">
                <x-filament::button
                    x-on:click="$wire.loadConcept({{ $concept->id }})"
                    size="sm">
                    {{ __('Laden') }}
                </x-filament::button>
                <x-filament::button
                    x-on:click="$wire.cancelConcept({{ $concept->id }})"
                    color="danger"
                    size="sm">
                    {{ __('Verwijderen') }}
                </x-filament::button>
            </div>
        </div>
    @empty
        <p class="text-gray-600 dark:text-gray-400">{{ __('Geen concepten in de wachtrij.') }}</p>
    @endforelse
</div>
