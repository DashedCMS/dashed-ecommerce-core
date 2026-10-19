@php
    use Dashed\DashedTranslations\Models\Translation;

    // Bij oneindig scrollen is dit de enige crawlbare weg naar de volgende
    // pagina: een echt anker met ?page=N, dat Livewire onderschept en als
    // loadMore afhandelt. Zonder volgende pagina rendert dit niets.
    $absolute = fn (?string $url): string => $url && ! preg_match('#^https?://#', $url) ? url($url) : (string) $url;
@endphp
@if ($paginator->hasMorePages())
    <a href="{{ $absolute($paginator->nextPageUrl()) }}"
       wire:click.prevent="loadMore"
       rel="next"
       class="{{ $class ?? 'inline-flex items-center justify-center rounded-full border border-[rgba(48,84,91,0.12)] bg-white px-6 py-3 text-sm font-semibold text-black transition hover:bg-secondary hover:text-white' }}">
        <span wire:loading.remove wire:target="loadMore">{{ Translation::get('load-more-products', 'products', 'Meer producten laden') }}</span>
        <span wire:loading wire:target="loadMore">{{ Translation::get('loading', 'products', 'Laden...') }}</span>
    </a>
@endif
