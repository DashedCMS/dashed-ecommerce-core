<x-filament::page>

    <form wire:submit.prevent="submit" method="POST">
        {{ $this->form }}

        <button class="flex items-center gap-3 px-3 py-2 rounded-lg font-medium transition bg-primary-500 text-white mt-4">Aanpassen</button>
    </form>

</x-filament::page>
