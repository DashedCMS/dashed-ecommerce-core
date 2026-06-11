<div class="@if($data['top_margin'] ?? true) pt-16 sm:pt-24 @endif @if($data['bottom_margin'] ?? true) pb-16 sm:pb-24 @endif">
    <x-container :show="$data['in_container'] ?? true">
        <livewire:order-withdrawal :blockData="$data" />
    </x-container>
</div>
