<div class="bg-white" x-data="{ filters: false }">
    @php($productCategory ? $productCategoryFirstChilds = $productCategory->getFirstChilds() : null)
    @if($productCategory && count($productCategoryFirstChilds))
        <div class="mb-6" wire:ignore x-cloak>
            <x-container>
                <div class="swiper"
                     data-slides-per-view-default="2"
                     data-slides-per-view="4"
                     data-slides-per-view-md="6"
                     data-space-between="16"
                     data-loop="true"
                >
                    <ul class="swiper-wrapper">
                        @foreach($productCategoryFirstChilds as $child)
                            <li class="swiper-slide">
                                <a href="{{ $child->getUrl() }}" class="group relative bg-white rounded-lg block">
                                    @if($child->image)
                                        <div class="relative aspect-square w-full overflow-hidden rounded-lg bg-white group-hover:opacity-75">
                                            <x-dashed-files::image
                                                class="h-full w-full object-cover object-center"
                                                config="dashed"
                                                :mediaId="$child->image"
                                                :manipulations="['fit' => [600, 600]]"
                                                width="180"
                                                height="180"
                                            />
                                        </div>
                                        <div class="bg-primary/90 absolute bottom-0 w-full h-auto min-h-16 rounded-b-lg flex items-center justify-center px-2">
                                            <p class="font-semibold text-center text-white text-sm">{{ $child->name }}</p>
                                        </div>
                                    @else
                                        <div class="relative aspect-square w-full overflow-hidden rounded-lg bg-gray-100 group-hover:opacity-75 flex items-end">
                                            <div class="bg-primary/90 w-full h-auto min-h-16 rounded-b-lg flex items-center justify-center px-2">
                                                <p class="font-semibold text-center text-white text-sm">{{ $child->name }}</p>
                                            </div>
                                        </div>
                                    @endif
                                </a>
                            </li>
                        @endforeach
                    </ul>
                </div>
            </x-container>
        </div>
    @endif

    {{-- Mobile filter drawer --}}
    <div class="relative z-40 lg:hidden" role="dialog" aria-modal="true" x-show="filters" x-cloak
         @keydown.window.escape="filters = false">
        <div class="fixed inset-0 bg-black/25" aria-hidden="true" x-show="filters"
             x-transition:enter="transition-opacity ease-linear duration-300"
             x-transition:enter-start="opacity-0"
             x-transition:enter-end="opacity-100"
             x-transition:leave="transition-opacity ease-linear duration-300"
             x-transition:leave-start="opacity-100"
             x-transition:leave-end="opacity-0"></div>

        <div class="fixed inset-0 z-40 flex"
             x-show="filters"
             x-transition:enter="transition ease-in-out duration-300 transform"
             x-transition:enter-start="translate-x-full"
             x-transition:enter-end="translate-x-0"
             x-transition:leave="transition ease-in-out duration-300 transform"
             x-transition:leave-start="translate-x-0"
             x-transition:leave-end="translate-x-full">
            <div class="relative ml-auto flex h-full w-full max-w-xs flex-col overflow-y-auto bg-white py-4 pb-12 shadow-xl">
                <div class="flex items-center justify-between px-4">
                    <h2 class="text-lg font-medium text-gray-900">{{ Translation::get('filters', 'products', 'Filters') }}</h2>
                    <button type="button"
                            x-on:click="filters = false"
                            class="-mr-2 flex h-10 w-10 items-center justify-center rounded-md bg-white p-2 text-gray-400 hover:text-gray-500">
                        <span class="sr-only">Sluit filters</span>
                        <svg class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M6 18 18 6M6 6l12 12"/>
                        </svg>
                    </button>
                </div>

                <div class="mt-4 border-t border-gray-200">
                    @if($productCategory && count($productCategoryFirstChilds))
                        <ul class="mx-4 space-y-4 border-b border-gray-200 py-6 text-sm font-medium text-gray-900">
                            @foreach ($productCategoryFirstChilds as $childCategory)
                                <li><a href="{{ $childCategory->getUrl() }}" class="hover:text-primary">{{ $childCategory->name }}</a></li>
                            @endforeach
                        </ul>
                    @endif

                    <div class="mx-4 border-b border-gray-200 py-6">
                        <input type="text" class="custom-form-input border border-primary-500 w-full" id="search-mobile"
                               wire:model.live.debounce.400ms="search"
                               placeholder="{{ Translation::get('search-term', 'products', 'Zoeken...') }}">
                    </div>

                    @foreach ($filters ?? [] as $filter)
                        <div class="mx-4 border-b border-gray-200 py-6" x-data="{ showFilter: false }">
                            <button type="button" @click="showFilter = !showFilter"
                                    class="flex w-full items-center justify-between bg-white py-3 text-sm text-gray-400 hover:text-gray-500">
                                <span class="font-medium text-gray-900">{{ $filter->name }}</span>
                                <span class="ml-6 flex items-center">
                                    <svg x-show="!showFilter" class="h-5 w-5" viewBox="0 0 20 20" fill="currentColor"><path d="M10.75 4.75a.75.75 0 0 0-1.5 0v4.5h-4.5a.75.75 0 0 0 0 1.5h4.5v4.5a.75.75 0 0 0 1.5 0v-4.5h4.5a.75.75 0 0 0 0-1.5h-4.5v-4.5Z"/></svg>
                                    <svg x-show="showFilter" x-cloak class="h-5 w-5" viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M4 10a.75.75 0 0 1 .75-.75h10.5a.75.75 0 0 1 0 1.5H4.75A.75.75 0 0 1 4 10Z" clip-rule="evenodd"/></svg>
                                </span>
                            </button>
                            <div class="pt-6" x-show="showFilter" x-cloak x-transition.opacity.scale.origin.top>
                                <div class="space-y-4">
                                    @foreach ($filter->productFilterOptions as $option)
                                        <div class="flex items-center">
                                            <input id="mobile-filter-{{ $filter->name }}-{{ $option->id }}"
                                                   type="checkbox"
                                                   wire:model.live="activeFilters.{{ $filter->name }}.{{ $option->name }}"
                                                   class="h-4 w-4 rounded border-gray-300 text-primary-600 focus:ring-primary-500">
                                            <label for="mobile-filter-{{ $filter->name }}-{{ $option->id }}"
                                                   class="ml-3 text-sm text-gray-600">{{ $option->name }}</label>
                                        </div>
                                    @endforeach
                                </div>
                            </div>
                        </div>
                    @endforeach
                </div>
            </div>
        </div>
    </div>

    <x-container>
        <div class="border-b border-gray-200 py-6">
            <div class="w-full">
                <div class="flex flex-col w-full gap-4 justify-between">
                    <h1 class="text-4xl font-bold tracking-tight text-gray-900">
                        {{ $productCategory
                            ? Translation::get('products-category-overview', 'products', 'Producten van :category:', 'text', ['category' => $productCategory->name])
                            : Translation::get('products-overview', 'products', 'Producten overzicht') }}
                    </h1>

                    <div class="flex gap-4 flex-wrap items-center justify-between">
                        {{-- Result count --}}
                        <p class="text-sm text-gray-500" wire:loading.class="opacity-50">
                            @if($products)
                                <span wire:loading.remove>
                                    {{ $products->total() }}
                                    {{ $products->total() === 1
                                        ? Translation::get('result', 'products', 'resultaat')
                                        : Translation::get('results', 'products', 'resultaten') }}
                                </span>
                                <span wire:loading class="inline-flex items-center gap-1">
                                    <svg class="animate-spin h-4 w-4 text-primary-500" fill="none" viewBox="0 0 24 24">
                                        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v8z"></path>
                                    </svg>
                                    {{ Translation::get('loading', 'products', 'Laden...') }}
                                </span>
                            @endif
                        </p>

                        <div class="flex items-center gap-3">
                            {{-- Sort dropdown --}}
                            <div class="relative inline-block text-left" x-data="{ sortBy: false }">
                                <button @click="sortBy = !sortBy" type="button"
                                        class="group inline-flex justify-center text-sm font-medium text-gray-700 hover:text-gray-900 focus:outline-none">
                                    {{ Translation::get('filter-by', 'products', 'Sorteren') }}
                                    <svg class="-mr-1 ml-1 h-5 w-5 shrink-0 text-gray-400 group-hover:text-gray-500" viewBox="0 0 20 20" fill="currentColor">
                                        <path fill-rule="evenodd" d="M5.22 8.22a.75.75 0 0 1 1.06 0L10 11.94l3.72-3.72a.75.75 0 1 1 1.06 1.06l-4.25 4.25a.75.75 0 0 1-1.06 0L5.22 9.28a.75.75 0 0 1 0-1.06Z" clip-rule="evenodd"/>
                                    </svg>
                                </button>
                                <div x-show="sortBy" x-cloak x-transition.opacity.scale.origin.top @click.away="sortBy = false" @keydown.window.escape="sortBy = false"
                                     class="absolute right-0 z-10 mt-2 w-44 origin-top-right rounded-md bg-white shadow-2xl ring-1 ring-black/5 focus:outline-none">
                                    <div class="py-1">
                                        @foreach([
                                            '' => Translation::get('filter-sort-by-standard', 'products', 'Standaard'),
                                            'price-asc' => Translation::get('filter-sort-by-price-asc', 'products', 'Prijs oplopend'),
                                            'price-desc' => Translation::get('filter-sort-by-price-desc', 'products', 'Prijs aflopend'),
                                            'purchases' => Translation::get('filter-sort-by-most-sold', 'products', 'Best verkocht'),
                                            'newest' => Translation::get('filter-sort-by-newest', 'products', 'Nieuwste'),
                                        ] as $value => $label)
                                            <a wire:click="setSortByValue('{{ $value }}')" @click="sortBy = false"
                                               @class([
                                                   'font-semibold text-gray-900' => $sortBy == $value || ($value == '' && ($sortBy == '' || $sortBy == 'price')),
                                                   'text-gray-500' => !($sortBy == $value || ($value == '' && ($sortBy == '' || $sortBy == 'price'))),
                                                   'block px-4 py-2 text-sm cursor-pointer hover:bg-gray-50',
                                               ])>{{ $label }}</a>
                                        @endforeach
                                    </div>
                                </div>
                            </div>

                            {{-- Mobile filter trigger --}}
                            <button type="button"
                                    class="flex items-center gap-1.5 text-sm font-medium text-gray-700 hover:text-gray-900 lg:hidden"
                                    x-on:click="filters = !filters">
                                <svg class="h-5 w-5" aria-hidden="true" viewBox="0 0 20 20" fill="currentColor">
                                    <path fill-rule="evenodd" d="M2.628 1.601C5.028 1.206 7.49 1 10 1s4.973.206 7.372.601a.75.75 0 0 1 .628.74v2.288a2.25 2.25 0 0 1-.659 1.59l-4.682 4.683a2.25 2.25 0 0 0-.659 1.59v3.037c0 .684-.31 1.33-.844 1.757l-1.937 1.55A.75.75 0 0 1 8 18.25v-5.757a2.25 2.25 0 0 0-.659-1.591L2.659 6.22A2.25 2.25 0 0 1 2 4.629V2.34a.75.75 0 0 1 .628-.74Z" clip-rule="evenodd"/>
                                </svg>
                                {{ Translation::get('filters', 'products', 'Filters') }}
                            </button>
                        </div>
                    </div>

                    {{-- Active filters + clear all --}}
                    @php($activeFilterCount = collect($activeFilters)->flatMap(fn($v) => array_values($v))->filter()->count())
                    @if($activeFilterCount > 0)
                        <div class="flex flex-wrap items-center gap-2">
                            @foreach($activeFilters as $activeFilter => $value)
                                @foreach($value as $optionValue => $showValue)
                                    @if($showValue)
                                        <button wire:click="removeFilter('{{ $activeFilter }}', '{{ $optionValue }}')"
                                                class="inline-flex items-center gap-1 px-2.5 py-1 text-xs font-medium text-gray-700 bg-primary-50 border border-primary-200 rounded-full hover:bg-primary-100 transition-colors">
                                            <span>{{ $activeFilter }}: {{ $optionValue }}</span>
                                            <svg class="h-3.5 w-3.5 text-gray-500" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                                            </svg>
                                        </button>
                                    @endif
                                @endforeach
                            @endforeach

                            @if($activeFilterCount >= 2)
                                <button wire:click="clearAllFilters"
                                        class="inline-flex items-center gap-1 px-2.5 py-1 text-xs font-medium text-gray-500 hover:text-gray-700 underline underline-offset-2 transition-colors">
                                    {{ Translation::get('clear-all-filters', 'products', 'Alle filters wissen') }}
                                </button>
                            @endif
                        </div>
                    @endif
                </div>

                @if($productCategory && ($productCategory->contentBlocks['top-content'] ?? false))
                    <div class="prose max-w-full w-full mt-4">
                        {!! cms()->convertToHtml($productCategory->contentBlocks['top-content']) !!}
                    </div>
                @endif
            </div>

            <section aria-labelledby="products-heading" class="pb-24 pt-6">
                <div class="grid grid-cols-1 gap-x-8 gap-y-10 lg:grid-cols-4">

                    {{-- Desktop sidebar --}}
                    <form class="hidden lg:block">
                        @if($productCategory && count($productCategoryFirstChilds))
                            <ul class="space-y-4 border-b border-gray-200 pb-6 text-sm font-medium text-gray-900">
                                @foreach ($productCategoryFirstChilds as $childCategory)
                                    <li><a href="{{ $childCategory->getUrl() }}" class="hover:text-primary">{{ $childCategory->name }}</a></li>
                                @endforeach
                            </ul>
                        @endif

                        <div class="border-b border-gray-200 py-6">
                            <input type="text" class="custom-form-input border border-primary-500 w-full" id="search"
                                   wire:model.live.debounce.400ms="search"
                                   placeholder="{{ Translation::get('search-term', 'products', 'Zoeken...') }}">
                        </div>

                        @foreach ($filters ?? [] as $filter)
                            <div class="border-b border-gray-200 py-6" x-data="{ showFilter: false }">
                                <button type="button" @click="showFilter = !showFilter"
                                        class="flex w-full items-center justify-between bg-white py-3 text-sm text-gray-400 hover:text-gray-500">
                                    <span class="font-medium text-gray-900">{{ $filter->name }}</span>
                                    <span class="ml-6 flex items-center">
                                        <svg x-show="!showFilter" class="h-5 w-5" viewBox="0 0 20 20" fill="currentColor"><path d="M10.75 4.75a.75.75 0 0 0-1.5 0v4.5h-4.5a.75.75 0 0 0 0 1.5h4.5v4.5a.75.75 0 0 0 1.5 0v-4.5h4.5a.75.75 0 0 0 0-1.5h-4.5v-4.5Z"/></svg>
                                        <svg x-show="showFilter" x-cloak class="h-5 w-5" viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M4 10a.75.75 0 0 1 .75-.75h10.5a.75.75 0 0 1 0 1.5H4.75A.75.75 0 0 1 4 10Z" clip-rule="evenodd"/></svg>
                                    </span>
                                </button>
                                <div class="pt-6" x-show="showFilter" x-cloak x-transition.opacity.scale.origin.top>
                                    <div class="space-y-4">
                                        @foreach ($filter->productFilterOptions as $option)
                                            <div class="flex items-center">
                                                <input id="filter-{{ $filter->name }}-{{ $option->id }}"
                                                       type="checkbox"
                                                       wire:model.live="activeFilters.{{ $filter->name }}.{{ $option->name }}"
                                                       class="h-4 w-4 rounded border-gray-300 text-primary-600 focus:ring-primary-500">
                                                <label for="filter-{{ $filter->name }}-{{ $option->id }}"
                                                       class="ml-3 text-sm text-gray-600">{{ $option->name }}</label>
                                            </div>
                                        @endforeach
                                    </div>
                                </div>
                            </div>
                        @endforeach
                    </form>

                    {{-- Product grid --}}
                    <div class="lg:col-span-3 relative">
                        {{-- Loading: fade existing grid instead of a blocking spinner --}}
                        <div wire:loading.class.remove="opacity-100"
                             wire:loading.class="opacity-40 pointer-events-none"
                             class="opacity-100 transition-opacity duration-200">

                            @if($products && $products->count())
                                <div class="grid gap-6 items-start grid-cols-1 sm:grid-cols-2 md:grid-cols-3">
                                    @foreach($products as $product)
                                        <x-product.product :product="$product"/>
                                    @endforeach
                                </div>

                                <div class="col-span-6 mx-auto overflow-x-scroll mt-12">
                                    {!! $products->onEachSide(0)->links('dashed.partials.pagination', data: ['scrollTo' => 'body']) !!}
                                </div>

                            @else
                                {{-- Empty state --}}
                                <div class="flex flex-col items-center justify-center py-24 text-center">
                                    <svg class="mx-auto h-16 w-16 text-gray-300" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="m21 21-5.197-5.197m0 0A7.5 7.5 0 1 0 5.196 5.196a7.5 7.5 0 0 0 10.607 10.607Z"/>
                                    </svg>
                                    <h3 class="mt-4 text-lg font-semibold text-gray-800">
                                        {{ Translation::get('no-products-found', 'products', 'Geen producten gevonden') }}
                                    </h3>
                                    <p class="mt-2 text-sm text-gray-500">
                                        {{ Translation::get('no-products-found-description', 'products', 'Probeer andere filters of zoektermen.') }}
                                    </p>
                                    @if($hasActiveFilters || $search)
                                        <button wire:click="clearAllFilters"
                                                class="mt-6 inline-flex items-center gap-2 rounded-md bg-primary px-4 py-2 text-sm font-medium text-white shadow-sm hover:bg-primary/90 transition-colors">
                                            <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                                            </svg>
                                            {{ Translation::get('clear-all-filters', 'products', 'Alle filters wissen') }}
                                        </button>
                                    @endif
                                </div>
                            @endif
                        </div>

                        {{-- Skeleton shown only while loading when there are no current products yet --}}
                        @if(! $products || ! $products->count())
                            <div wire:loading class="grid gap-6 grid-cols-1 sm:grid-cols-2 md:grid-cols-3">
                                @for($i = 0; $i < ($pagination ?? 12); $i++)
                                    <div class="animate-pulse">
                                        <div class="aspect-square w-full rounded-lg bg-gray-200 mb-3"></div>
                                        <div class="h-4 bg-gray-200 rounded w-3/4 mb-2"></div>
                                        <div class="h-4 bg-gray-200 rounded w-1/2"></div>
                                    </div>
                                @endfor
                            </div>
                        @endif
                    </div>
                </div>
            </section>
        </div>
    </x-container>

    <x-dashed-core::global-blocks name="products-overview-page"/>
</div>
