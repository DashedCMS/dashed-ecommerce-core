<x-filament::page>

    <form wire:submit.prevent="submit" method="POST">
        {{ $this->form }}

        @if($loading)
            <p>loading</p>
        @else
            <button
                class="flex items-center gap-3 px-3 py-2 rounded-lg font-medium transition bg-primary-500 text-white mt-4">
                Bestelling aanmaken
            </button>
        @endif
    </form>

</x-filament::page>
