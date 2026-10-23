<x-filament-panels::page>
    @php($summary = $this->summary())

    @if (count($summary))
        <x-filament::section :heading="__('Producten die iets missen')" :description="__('Klik op een controle om de producten te zien.')" collapsible>
            <div class="flex flex-wrap gap-2">
                @foreach ($summary as $item)
                    <a href="{{ $item['url'] }}">
                        <x-filament::badge :color="$item['color']" size="lg">
                            {{ $item['label'] }}: {{ number_format($item['count'], 0, ',', '.') }}
                        </x-filament::badge>
                    </a>
                @endforeach
            </div>
        </x-filament::section>
    @endif

    {{ $this->table }}
</x-filament-panels::page>
