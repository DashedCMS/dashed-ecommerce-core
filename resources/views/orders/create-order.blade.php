<x-filament::page>

    <form wire:submit.prevent="submit" method="POST">
        {{ $this->form }}

        @if($loading)
            <p>loading</p>
        @else
            <x-filament::button type="submit"
                                class="flex items-center gap-3 px-3 py-2 rounded-lg font-medium transition bg-primary-500 text-white mt-4">
                <svg wire:loading wire:target="submit"
                     class="w-6 h-6 mr-1 -ml-2 rtl:ml-1 rtl:-mr-2 filament-button-icon animate-spin"
                     xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor">
                    <path d="M2 12C2 6.47715 6.47715 2 12 2V5C8.13401 5 5 8.13401 5 12H2Z"></path>
                </svg>
                <span>Bestelling aanmaken</span>
            </x-filament::button>
        @endif
    </form>

</x-filament::page>
