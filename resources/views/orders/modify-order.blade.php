<x-filament::page>

    {{-- Geen wire:submit meer: dit scherm herschrijft onomkeerbaar een echte
         bestelling en mag dus nooit via Enter of een native form-submit
         langs de bevestigingsmodal van submitAction() heen gaan. --}}
    {{ $this->modifyOrderForm }}

    <div class="mt-6">
        <x-filament::button type="button" wire:click="mountAction('submitAction')">
            Wijziging doorvoeren
        </x-filament::button>
    </div>

</x-filament::page>
