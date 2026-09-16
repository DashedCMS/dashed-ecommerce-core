<button type="button" wire:click="toggle" wire:loading.attr="disabled"
        class="inline-flex items-center gap-2 rounded-full bg-white/90 p-2 text-sm shadow hover:bg-white {{ $inWishlist ? 'text-red-600' : 'text-gray-700' }}"
        aria-pressed="{{ $inWishlist ? 'true' : 'false' }}"
        aria-label="{{ $inWishlist ? __('Van verlanglijst halen') : __('Op verlanglijst zetten') }}">
    <svg class="h-5 w-5" viewBox="0 0 24 24" fill="{{ $inWishlist ? 'currentColor' : 'none' }}" stroke="currentColor" stroke-width="1.8" aria-hidden="true">
        <path stroke-linecap="round" stroke-linejoin="round" d="M21 8.25c0-2.485-2.099-4.5-4.688-4.5-1.935 0-3.597 1.126-4.312 2.733-.715-1.607-2.377-2.733-4.313-2.733C5.1 3.75 3 5.765 3 8.25c0 7.22 9 12 9 12s9-4.78 9-12z"/>
    </svg>
    @if($showLabel)
        <span>{{ $inWishlist ? __('Op je verlanglijst') : __('Bewaar op verlanglijst') }}</span>
    @endif
</button>
