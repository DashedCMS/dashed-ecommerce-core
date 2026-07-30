<x-filament::page>

    <form wire:submit.prevent="submit" method="POST">
        {{ $this->modifyOrderForm }}

        <div class="mt-6">
            <x-filament::button type="submit">
                Wijziging doorvoeren
            </x-filament::button>
        </div>
    </form>

</x-filament::page>
