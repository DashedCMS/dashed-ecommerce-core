<div class="bg-white" x-data="{ filters: false }">
    @php($productCategory ? $productCategoryFirstChilds = $productCategory->getFirstChilds() : null)
    @if($productCategory && count($productCategoryFirstChilds))
        <div class="mb-6" wire:ignore x-cloak>
            <x-container>
                <div class="swiper swiper-categories">
                    <ul class="swiper-wrapper">
                        @foreach($productCategoryFirstChilds as $child)
                            <li class="swiper-slide">
                                <a href="{{ $child->getUrl() }}" class="group relative bg-white rounded-lg">
                                    @if($child->image)
                                        <div class="relative aspect-square w-full overflow-hidden rounded-lg bg-white group-hover:opacity-75">
                                            <x-dashed-files::image
                                                class="h-full w-full object-cover object-center"
                                                config="dashed"
                                                :mediaId="$child->image"
                                                :manipulations="[
                                                        'fit' => [600,600],
                                                    ]"
                                                width="180"
                                                height="180"
                                            />
                                        </div>
                                        <div class="bg-primary-500/90 absolute bottom-0 w-full h-auto min-h-16 rounded-b-lg">
                                            <p class="font-semibold text-center text-white">{{$child->name}}</p>
                                        </div>
                                    @else
                                        <div class="relative aspect-square w-full overflow-hidden rounded-lg bg-gray-100 group-hover:opacity-75">
                                            <svg class="h-full w-full object-cover object-center text-gray-300"
                                                 fill="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                                                <path
                                                    d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2ZM7 13.5 9.5 16 16 9.5 14.5 8 8 14.5 9.5 16Z"/>
                                            </svg>
                                            <div class="bg-primary-500/90 absolute bottom-0 w-full h-auto min-h-16 rounded-b-lg">
                                                <p class="font-semibold text-center text-white">{{$child->name}}</p>
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
    <div>
        <div class="relative z-40 lg:hidden" role="dialog" aria-modal="true" x-show="filters" x-cloak
             @keydown.window.escape="filters = false">
            <div class="fixed inset-0 bg-black bg-opacity-25" aria-hidden="true" x-show="filters"
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
                                x-on:click="filters = !filters"
                                class="-mr-2 flex h-10 w-10 items-center justify-center rounded-md bg-white p-2 text-gray-400">
                            <span class="sr-only">Close menu</span>
                            <svg class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke-width="1.5"
                                 stroke="currentColor" aria-hidden="true" data-slot="icon">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M6 18 18 6M6 6l12 12"/>
                            </svg>
                        </button>
                    </div>

                    <form class="mt-4 border-t border-gray-200">
                        @if($productCategory && count($productCategoryFirstChilds))
                            <h3 class="sr-only">{{ Translation::get('underlying-categories', 'products', 'Onderliggende categorieen') }}</h3>
                            <ul role="list"
                                class="mx-4 space-y-4 border-b border-gray-200 py-6 text-sm font-medium text-gray-900">
                                @foreach ($productCategoryFirstChilds as $childCategory)
                                    <li>
                                        <a href="{{ $childCategory->getUrl() }}">{{$childCategory->name}}</a>
                                    </li>
                                @endforeach
                            </ul>
                        @endif

                        <section class="mx-4 space-y-4 border-b border-gray-200 py-6 text-sm font-medium text-gray-900">
                            <div
                                class="inline-flex items-center overflow-hidden w-full">
                                <input type="text" class="form-input border border-primary-500 w-full" id="search"
                                       name="search"
                                       wire:model.live="search"
                                       placeholder="{{Translation::get('search-term', 'products', 'Zoeken...')}}">
                            </div>
                        </section>

                        @foreach ($filters ?? [] as $filter)
                            <div class="mx-4 border-b border-gray-200 py-6" x-data="{ showFilter: false }">
                                <h3 class="-my-3 flow-root">
                                    <!-- Expand/collapse section button -->
                                    <button type="button"
                                            @click="showFilter = !showFilter"
                                            class="flex w-full items-center justify-between bg-white py-3 text-sm text-gray-400 hover:text-gray-500"
                                            aria-controls="filter-section-0" aria-expanded="false">
                                        <span class="font-medium text-gray-900">{{ $filter->name }}</span>
                                        <span class="ml-6 flex items-center">
                                                <svg x-show="!showFilter" class="h-5 w-5" viewBox="0 0 20 20"
                                                     fill="currentColor" aria-hidden="true" data-slot="icon">
                                                  <path d="M10.75 4.75a.75.75 0 0 0-1.5 0v4.5h-4.5a.75.75 0 0 0 0 1.5h4.5v4.5a.75.75 0 0 0 1.5 0v-4.5h4.5a.75.75 0 0 0 0-1.5h-4.5v-4.5Z"/>
                                                </svg>
                                                <svg x-show="showFilter" x-cloak class="h-5 w-5" viewBox="0 0 20 20"
                                                     fill="currentColor" aria-hidden="true" data-slot="icon">
                                                  <path fill-rule="evenodd"
                                                        d="M4 10a.75.75 0 0 1 .75-.75h10.5a.75.75 0 0 1 0 1.5H4.75A.75.75 0 0 1 4 10Z"
                                                        clip-rule="evenodd"/>
                                                </svg>
                                              </span>
                                    </button>
                                </h3>
                                <div class="pt-6" id="filter-section-0" x-show="showFilter" x-cloak
                                     x-transition.opacity.scale.origin.top>
                                    <div class="space-y-4">
                                        @foreach ($filter->productFilterOptions as $option)
                                            <div class="flex items-center">
                                                <input id="filter-{{$filter->name}}-{{$option->id}}"
                                                       type="checkbox"
                                                       wire:model.live="activeFilters.{{$filter->name}}.{{ $option->name }}"
                                                       class="h-4 w-4 rounded border-gray-300 text-primary-600 focus:ring-primary-500">
                                                <label for="filter-{{$filter->name}}-{{$option->id}}"
                                                       class="ml-3 text-sm text-gray-600">{{$option->name}}</label>
                                            </div>
                                        @endforeach
                                    </div>
                                </div>
                            </div>
                        @endforeach
                    </form>
                </div>
            </div>
        </div>

        <x-container>
            <div class="border-b border-gray-200 py-6">
                <div class="w-full">
                    <div class="flex flex-col w-full gap-4 justify-between">
                        <h1 class="text-4xl font-bold tracking-tight text-gray-900"> {{ $productCategory ? Translation::get('products-category-overview', 'products', 'Producten van :category:', 'text', [
                                'category' => $productCategory->name
                            ]) : Translation::get('products-overview', 'products', 'Producten overzicht') }}</h1>

                        <div class="flex gap-4 flex-wrap items-center justify-between">
                            @if($products)
                                <p class="text-base text-gray-500">{{$products->total()}} {{$products->total() == 1 ? Translation::get('result', 'products', 'resultaat') : Translation::get('results', 'products', 'resultaten')}}</p>
                            @endif
                            <div class="flex items-center">
                                <div class="relative inline-block text-left" x-data="{ sortBy: false }">
                                    <div>
                                        <button @click="sortBy = !sortBy" type="button"
                                                class="group inline-flex justify-center text-sm font-medium text-gray-700 hover:text-gray-900 focus:outline-none"
                                                id="menu-button" aria-expanded="false" aria-haspopup="true">
                                            {{Translation::get('filter-by', 'products', 'Sorteren')}}
                                            <svg class="-mr-1 ml-1 h-5 w-5 flex-shrink-0 text-gray-400 group-hover:text-gray-500"
                                                 viewBox="0 0 20 20" fill="currentColor" aria-hidden="true"
                                                 data-slot="icon">
                                                <path fill-rule="evenodd"
                                                      d="M5.22 8.22a.75.75 0 0 1 1.06 0L10 11.94l3.72-3.72a.75.75 0 1 1 1.06 1.06l-4.25 4.25a.75.75 0 0 1-1.06 0L5.22 9.28a.75.75 0 0 1 0-1.06Z"
                                                      clip-rule="evenodd"/>
                                            </svg>
                                        </button>
                                    </div>

                                    <div x-show="sortBy"
                                         x-cloak
                                         x-transition.opacity.scale.origin.top
                                         @click.away="sortBy = false"
                                         @keydown.window.escape="sortBy = false"
                                         class="absolute right-0 z-10 mt-2 w-40 origin-top-right rounded-md bg-white shadow-2xl ring-1 ring-black ring-opacity-5 focus:outline-none"
                                         role="menu" aria-orientation="vertical" aria-labelledby="menu-button"
                                         tabindex="-1">
                                        <div class="py-1" role="none">
                                            <a wire:click="setSortByValue('')" @click="sortBy = false" @class([
        'font-bold text-gray-900' => $sortBy == '' || $sortBy == 'price',
        'text-gray-500' => $sortBy != '' && $sortBy != 'price',
        'block px-4 py-2 text-sm cursor-pointer hover:bg-gray-100'
]) role="menuitem" tabindex="-1"
                                               id="menu-item-0">{{Translation::get('filter-sort-by-standard', 'products', 'Standaard')}}</a>
                                            <a wire:click="setSortByValue('price-asc')" @click="sortBy = false" @class([
        'font-bold text-gray-900' => $sortBy == 'price-asc',
        'text-gray-500' => $sortBy != 'price-asc',
        'block px-4 py-2 text-sm cursor-pointer hover:bg-gray-100'
]) role="menuitem" tabindex="-1"
                                               id="menu-item-1">{{Translation::get('filter-sort-by-price-asc', 'products', 'Prijs oplopend')}}</a>
                                            <a wire:click="setSortByValue('price-desc')" @click="sortBy = false" @class([
        'font-bold text-gray-900' => $sortBy == 'price-desc',
        'text-gray-500' => $sortBy != 'price-desc',
        'block px-4 py-2 text-sm cursor-pointer hover:bg-gray-100'
]) role="menuitem" tabindex="-1"
                                               id="menu-item-2">{{Translation::get('filter-sort-by-price-desc', 'products', 'Prijs aflopend')}}</a>
                                            <a wire:click="setSortByValue('purchases')" @click="sortBy = false" @class([
        'font-bold text-gray-900' => $sortBy == 'purchases',
        'text-gray-500' => $sortBy != 'purchases',
        'block px-4 py-2 text-sm cursor-pointer hover:bg-gray-100'
]) role="menuitem" tabindex="-1"
                                               id="menu-item-3">{{Translation::get('filter-sort-by-most-sold', 'products', 'Best verkocht')}}</a>
                                            <a wire:click="setSortByValue('newest')" @click="sortBy = false" @class([
        'font-bold text-gray-900' => $sortBy == 'newest',
        'text-gray-500' => $sortBy != 'newest',
        'block px-4 py-2 text-sm cursor-pointer hover:bg-gray-100'
]) role="menuitem" tabindex="-1"
                                               id="menu-item-4">{{Translation::get('filter-sort-by-newest', 'products', 'Nieuwste')}}</a>
                                        </div>
                                    </div>
                                </div>

                                <button type="button"
                                        class="-m-2 ml-4 p-2 text-gray-400 hover:text-gray-500 sm:ml-6 lg:hidden"
                                        x-on:click="filters = !filters">
                                    <span class="sr-only">Filters</span>
                                    <svg class="h-5 w-5" aria-hidden="true" viewBox="0 0 20 20" fill="currentColor"
                                         data-slot="icon">
                                        <path fill-rule="evenodd"
                                              d="M2.628 1.601C5.028 1.206 7.49 1 10 1s4.973.206 7.372.601a.75.75 0 0 1 .628.74v2.288a2.25 2.25 0 0 1-.659 1.59l-4.682 4.683a2.25 2.25 0 0 0-.659 1.59v3.037c0 .684-.31 1.33-.844 1.757l-1.937 1.55A.75.75 0 0 1 8 18.25v-5.757a2.25 2.25 0 0 0-.659-1.591L2.659 6.22A2.25 2.25 0 0 1 2 4.629V2.34a.75.75 0 0 1 .628-.74Z"
                                              clip-rule="evenodd"/>
                                    </svg>
                                </button>
                            </div>
                        </div>

                        @if(count($activeFilters))
                            <div class="flex flex-wrap gap-2 mt-4">
                                @foreach($activeFilters as $activeFilter => $value)
                                    @foreach($value as $value => $showValue)
                                        @if($showValue)
                                            <div>
                                                <button wire:click="removeFilter('{{$activeFilter}}', '{{$value}}')"
                                                        class="flex items-center px-2 py-1 text-sm font-medium text-gray-700 bg-primary-100 rounded-full">
                                                    <span>{{ $activeFilter }}: {{$value}}</span>
                                                    <svg class="h-4 w-4 ml-1 text-gray-500" fill="none"
                                                         viewBox="0 0 24 24"
                                                         stroke="currentColor" aria-hidden="true" data-slot="icon">
                                                        <path stroke-linecap="round" stroke-linejoin="round"
                                                              stroke-width="2"
                                                              d="M6 18L18 6M6 6l12 12"/>
                                                    </svg>
                                                </button>
                                            </div>
                                        @endif
                                    @endforeach
                                @endforeach
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
                        <form class="hidden lg:block">
                            @if($productCategory && count($productCategoryFirstChilds))
                                <h3 class="sr-only">{{ Translation::get('underlying-categories', 'products', 'Onderliggende categorieen') }}</h3>
                                <ul role="list"
                                    class="space-y-4 border-b border-gray-200 pb-6 text-sm font-medium text-gray-900">
                                    @foreach ($productCategoryFirstChilds as $childCategory)
                                        <li>
                                            <a href="{{ $childCategory->getUrl() }}">{{$childCategory->name}}</a>
                                        </li>
                                    @endforeach
                                </ul>
                            @endif

                            <section class="space-y-4 border-b border-gray-200 py-6 text-sm font-medium text-gray-900">
                                <div
                                    class="inline-flex items-center overflow-hidden w-full">
                                    <input type="text" class="form-input border border-primary-500 w-full" id="search"
                                           name="search"
                                           wire:model.live="search"
                                           placeholder="{{Translation::get('search-term', 'products', 'Zoeken...')}}">
                                </div>
                            </section>

                            @foreach ($filters ?? [] as $filter)
                                <div class="border-b border-gray-200 py-6" x-data="{ showFilter: false }">
                                    <h3 class="-my-3 flow-root">
                                        <!-- Expand/collapse section button -->
                                        <button type="button"
                                                @click="showFilter = !showFilter"
                                                class="flex w-full items-center justify-between bg-white py-3 text-sm text-gray-400 hover:text-gray-500"
                                                aria-controls="filter-section-0" aria-expanded="false">
                                            <span class="font-medium text-gray-900">{{ $filter->name }}</span>
                                            <span class="ml-6 flex items-center">
                                                <svg x-show="!showFilter" class="h-5 w-5" viewBox="0 0 20 20"
                                                     fill="currentColor" aria-hidden="true" data-slot="icon">
                                                  <path d="M10.75 4.75a.75.75 0 0 0-1.5 0v4.5h-4.5a.75.75 0 0 0 0 1.5h4.5v4.5a.75.75 0 0 0 1.5 0v-4.5h4.5a.75.75 0 0 0 0-1.5h-4.5v-4.5Z"/>
                                                </svg>
                                                <svg x-show="showFilter" x-cloak class="h-5 w-5" viewBox="0 0 20 20"
                                                     fill="currentColor" aria-hidden="true" data-slot="icon">
                                                  <path fill-rule="evenodd"
                                                        d="M4 10a.75.75 0 0 1 .75-.75h10.5a.75.75 0 0 1 0 1.5H4.75A.75.75 0 0 1 4 10Z"
                                                        clip-rule="evenodd"/>
                                                </svg>
                                              </span>
                                        </button>
                                    </h3>
                                    <div class="pt-6" id="filter-section-0" x-show="showFilter" x-cloak
                                         x-transition.opacity.scale.origin.top>
                                        <div class="space-y-4">
                                            @foreach ($filter->productFilterOptions as $option)
                                                <div class="flex items-center">
                                                    <input id="filter-{{$filter->name}}-{{$option->id}}"
                                                           type="checkbox"
                                                           wire:model.live="activeFilters.{{$filter->name}}.{{ $option->name }}"
                                                           class="h-4 w-4 rounded border-gray-300 text-primary-600 focus:ring-primary-500">
                                                    <label for="filter-{{$filter->name}}-{{$option->id}}"
                                                           class="ml-3 text-sm text-gray-600">{{$option->name}}</label>
                                                </div>
                                            @endforeach
                                        </div>
                                    </div>
                                </div>
                            @endforeach
                        </form>

                        <div class="lg:col-span-3 relative">
                            <div wire:loading wire:target="activeFilters"
                                 x-cloak
                                 class="absolute w-full h-screen rounded-lg bg-primary/5 z-50">
                                <div role="status" class="absolute left-1/2 top-1/2 z-40">
                                    <svg aria-hidden="true"
                                         class="inline w-8 h-8 mr-2 text-gray-200 animate-spin dark:text-gray-600 fill-primary-600"
                                         viewBox="0 0 100 101" fill="none" xmlns="http://www.w3.org/2000/svg">
                                        <path
                                            d="M100 50.5908C100 78.2051 77.6142 100.591 50 100.591C22.3858 100.591 0 78.2051 0 50.5908C0 22.9766 22.3858 0.59082 50 0.59082C77.6142 0.59082 100 22.9766 100 50.5908ZM9.08144 50.5908C9.08144 73.1895 27.4013 91.5094 50 91.5094C72.5987 91.5094 90.9186 73.1895 90.9186 50.5908C90.9186 27.9921 72.5987 9.67226 50 9.67226C27.4013 9.67226 9.08144 27.9921 9.08144 50.5908Z"
                                            fill="currentColor"/>
                                        <path
                                            d="M93.9676 39.0409C96.393 38.4038 97.8624 35.9116 97.0079 33.5539C95.2932 28.8227 92.871 24.3692 89.8167 20.348C85.8452 15.1192 80.8826 10.7238 75.2124 7.41289C69.5422 4.10194 63.2754 1.94025 56.7698 1.05124C51.7666 0.367541 46.6976 0.446843 41.7345 1.27873C39.2613 1.69328 37.813 4.19778 38.4501 6.62326C39.0873 9.04874 41.5694 10.4717 44.0505 10.1071C47.8511 9.54855 51.7191 9.52689 55.5402 10.0491C60.8642 10.7766 65.9928 12.5457 70.6331 15.2552C75.2735 17.9648 79.3347 21.5619 82.5849 25.841C84.9175 28.9121 86.7997 32.2913 88.1811 35.8758C89.083 38.2158 91.5421 39.6781 93.9676 39.0409Z"
                                            fill="currentFill"/>
                                    </svg>
                                    <span class="sr-only">Loading...</span>
                                </div>
                            </div>
                            <div wire:loading.remove wire:target="activeFilters">
                                @if($products && $products->count())
                                    <div class="grid gap-8 items-start grid-cols-1 sm:grid-cols-2 md:grid-cols-3">
                                        @foreach($products as $product)
                                            <x-product.product :product="$product"/>
                                        @endforeach
                                    </div>
                                    <div class="col-span-6 mx-auto overflow-x-scroll mt-12">
                                        {!! $products->onEachSide(0)->links('dashed.partials.pagination', data: [
                                            'scrollTo' => 'body'
                                        ]) !!}
                                    </div>
                                @else
                                    <div>
                                        <p>{{ Translation::get('no-products-found', 'products', 'Geen producten gevonden') }}</p>
                                    </div>
                                @endif
                            </div>
                        </div>
                    </div>
                </section>
            </div>
        </x-container>
    </div>
    <x-dashed-core::global-blocks name="products-overview-page"/>
</div>

