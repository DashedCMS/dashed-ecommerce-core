@php
    use Dashed\DashedTranslations\Models\Translation;

    if (! isset($scrollTo)) {
        $scrollTo = 'body';
    }

    $scrollIntoViewJsSnippet = ($scrollTo !== false)
        ? <<<JS
           (\$el.closest('{$scrollTo}') || document.querySelector('{$scrollTo}')).scrollIntoView()
        JS
        : '';

    $btnBase = 'grid size-11 place-items-center rounded-full transition';
    $btnActive = 'border border-[rgba(48,84,91,0.12)] bg-white text-black hover:bg-secondary hover:text-white';
    $btnDisabled = 'border border-[rgba(48,84,91,0.08)] bg-white text-[rgba(48,84,91,0.3)] cursor-not-allowed';
@endphp

{{--
    Crawlbare paginering: elke knop is een anker met de kale ?page=N-URL, dat
    Livewire met wire:click.prevent onderschept. Een crawler volgt de href, een
    bezoeker blijft in het component met zijn filters en sortering. rel=prev en
    rel=next alleen op de vorige- en volgende-knop.
--}}
@if ($paginator->hasPages())
    <nav class="flex flex-wrap items-center justify-center gap-2" aria-label="{{ Translation::get('pagination', 'pagination', 'Paginering') }}">
        {{-- Previous Page Link --}}
        @if ($paginator->onFirstPage())
            <span class="hidden md:grid {{ $btnBase }} {{ $btnDisabled }}" aria-disabled="true">
                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.8" stroke="currentColor" class="h-5 w-5">
                    <path stroke-linecap="round" stroke-linejoin="round" d="m18.75 4.5-7.5 7.5 7.5 7.5m-6-15L5.25 12l7.5 7.5" />
                </svg>
            </span>
            <span class="{{ $btnBase }} {{ $btnDisabled }}" aria-disabled="true">
                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.8" stroke="currentColor" class="h-5 w-5">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M15.75 19.5 8.25 12l7.5-7.5" />
                </svg>
            </span>
        @else
            <a href="{{ $paginator->url(1) }}" class="hidden md:grid {{ $btnBase }} {{ $btnActive }}"
               wire:click.prevent="gotoPage(1)"
               x-on:click="{{ $scrollIntoViewJsSnippet }}"
               aria-label="{{ Translation::get('pagination-first', 'pagination', 'Eerste pagina') }}">
                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.8" stroke="currentColor" class="h-5 w-5">
                    <path stroke-linecap="round" stroke-linejoin="round" d="m18.75 4.5-7.5 7.5 7.5 7.5m-6-15L5.25 12l7.5 7.5" />
                </svg>
            </a>
            <a href="{{ $paginator->previousPageUrl() }}" class="{{ $btnBase }} {{ $btnActive }}"
               wire:click.prevent="previousPage"
               x-on:click="{{ $scrollIntoViewJsSnippet }}"
               rel="prev"
               aria-label="{{ Translation::get('pagination-previous', 'pagination', 'Vorige pagina') }}">
                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.8" stroke="currentColor" class="h-5 w-5">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M15.75 19.5 8.25 12l7.5-7.5" />
                </svg>
            </a>
        @endif

        {{-- Pagination Elements --}}
        @foreach ($elements as $element)
            {{-- "Three Dots" Separator --}}
            @if (is_string($element))
                <span class="grid size-11 place-items-center text-[rgba(48,84,91,0.5)]">{{ $element }}</span>
            @endif

            {{-- Array Of Links --}}
            @if (is_array($element))
                @foreach ($element as $page => $url)
                    @if ($page == $paginator->currentPage())
                        <span aria-current="page" class="{{ $btnBase }} bg-black font-semibold text-white">{{ $page }}</span>
                    @else
                        <a href="{{ $url }}" class="{{ $btnBase }} {{ $btnActive }} font-semibold"
                           wire:click.prevent="gotoPage({{ $page }})"
                           x-on:click="{{ $scrollIntoViewJsSnippet }}">{{ $page }}</a>
                    @endif
                @endforeach
            @endif
        @endforeach

        {{-- Next Page Link --}}
        @if ($paginator->hasMorePages())
            <a href="{{ $paginator->nextPageUrl() }}" class="{{ $btnBase }} {{ $btnActive }}"
               wire:click.prevent="nextPage"
               x-on:click="{{ $scrollIntoViewJsSnippet }}"
               rel="next"
               aria-label="{{ Translation::get('pagination-next', 'pagination', 'Volgende pagina') }}">
                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.8" stroke="currentColor" class="h-5 w-5">
                    <path stroke-linecap="round" stroke-linejoin="round" d="m8.25 4.5 7.5 7.5-7.5 7.5" />
                </svg>
            </a>
            <a href="{{ $paginator->url($paginator->lastPage()) }}" class="hidden md:grid {{ $btnBase }} {{ $btnActive }}"
               wire:click.prevent="gotoPage({{ $paginator->lastPage() }})"
               x-on:click="{{ $scrollIntoViewJsSnippet }}"
               aria-label="{{ Translation::get('pagination-last', 'pagination', 'Laatste pagina') }}">
                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.8" stroke="currentColor" class="h-5 w-5">
                    <path stroke-linecap="round" stroke-linejoin="round" d="m5.25 4.5 7.5 7.5-7.5 7.5m6-15 7.5 7.5-7.5 7.5" />
                </svg>
            </a>
        @else
            <span class="{{ $btnBase }} {{ $btnDisabled }}" aria-disabled="true">
                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.8" stroke="currentColor" class="h-5 w-5">
                    <path stroke-linecap="round" stroke-linejoin="round" d="m8.25 4.5 7.5 7.5-7.5 7.5" />
                </svg>
            </span>
            <span class="hidden md:grid {{ $btnBase }} {{ $btnDisabled }}" aria-disabled="true">
                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.8" stroke="currentColor" class="h-5 w-5">
                    <path stroke-linecap="round" stroke-linejoin="round" d="m5.25 4.5 7.5 7.5-7.5 7.5m6-15 7.5 7.5-7.5 7.5" />
                </svg>
            </span>
        @endif
    </nav>
@endif
