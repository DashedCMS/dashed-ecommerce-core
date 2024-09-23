<x-filament::page>

    <form wire:submit.prevent="submit" method="POST">
        {{ $this->createOrderForm }}
    </form>

</x-filament::page>
