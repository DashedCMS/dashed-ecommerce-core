<div class="bg-gray-50 text-primary-800">
    <section class="relative z-10 py-12">
        <x-container>
            @if($productCategory && count($productCategory->getFirstChilds()))
                <div class="mb-6" wire:ignore x-cloak>
                    <div class="swiper swiper-categories">
                        <ul class="swiper-wrapper">
                            @foreach($productCategory->getFirstChilds() as $child)
                                <li class="swiper-slide">
                                    <a href="{{ $child->getUrl() }}" class="group relative bg-white rounded-lg">
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
                                        {{--                                                                                                        <h3 class="mt-6 text-sm text-gray-500">--}}
                                        {{--                                                                                                            <a href="{{$productCategory->getUrl()}}">--}}
                                        {{--                                                                                                                <span class="absolute inset-0"></span>--}}
                                        {{--                                                                                                                {{ Translation::get('amount-of-products', 'categories', ':amount: products', 'text', [--}}
                                        {{--                                                                                'amount' => $productCategory->products->count() ?? 0--}}
                                        {{--                                                                            ]) }}--}}
                                        {{--                                                                                                            </a>--}}
                                        {{--                                                                                                        </h3>--}}
                                        <div class="bg-primary-500/90 absolute bottom-0 w-full h-auto rounded-b-lg">
                                            <p class="font-semibold text-center text-white">{{$child->name}}</p>
                                        </div>
                                    </a>
                                </li>
                            @endforeach
                        </ul>
                    </div>
                </div>
            @endif
            <div class="grid grid-cols-1 lg:grid-cols-2 gap-8">
                <div>
                    <h2 class="text-3xl font-bold text-primary-800">
                        {{ $productCategory ? Translation::get('products-category-overview', 'products', 'Producten van :category:', 'text', [
                            'category' => $productCategory->name
                        ]) : Translation::get('products-overview', 'products', 'Producten overzicht') }}
                    </h2>

                    @if($products)
                        <p class="text-xl text-primary-800">{{$products->total()}} {{$products->total() == 1 ? Translation::get('result', 'products', 'resultaat') : Translation::get('results', 'products', 'resultaten')}}</p>
                    @endif
                </div>
                <div class="lg:ml-auto text-primary-800">
                    <label>{{Translation::get('filter-by', 'products', 'Sorteer op')}}:</label>
                    <select wire:model.live="sortBy"
                            class="border-2 border-primary-800 text-primary-800 py-2 px-4 focus:outline-none choices rounded-lg"
                            name="sort-by"
                            id="sort-by">
                        <option
                                value="">{{Translation::get('filter-sort-by-standard', 'products', 'Standaard')}}</option>
                        <option
                                value="price-asc">{{Translation::get('filter-sort-by-price-asc', 'products', 'Prijs oplopend')}}</option>
                        <option
                                value="price-desc">{{Translation::get('filter-sort-by-price-desc', 'products', 'Prijs aflopend')}}</option>
                        <option
                                value="purchases">{{Translation::get('filter-sort-by-most-sold', 'products', 'Best verkocht')}}</option>
                        <option
                                value="newest">{{Translation::get('filter-sort-by-newest', 'products', 'Nieuwste')}}</option>
                    </select>
                </div>
            </div>

            <section x-data="{ filters: false }" class="grid grid-cols-4 items-start gap-8">
                <div class="col-span-4 lg:col-span-1">
                    <div class="mt-4 lg:hidden">
                        <div class="cursor-pointer inline-block px-6 py-2 text-center uppercase button--primary w-full"
                             x-on:click="filters = !filters">{{ucfirst(Translation::get('filter', 'products', 'Filters'))}}
                        </div>
                    </div>

                    <aside x-bind:class="filters ? 'flex' : 'hidden'"
                           x-cloak
                           class="flex-col mb-8 space-x-0 lg:flex lg:space-x-0 lg:space-y-8 lg:w-64 md:flex-col whitespace-nowrap pr-4">
                        <section class="space-y-2 mt-4">
                            <div
                                    class="inline-flex items-center overflow-hidden w-full">
                                <input type="text" class="form-input border border-primary-500 w-full" id="search"
                                       name="search"
                                       wire:model.live="search"
                                       placeholder="{{Translation::get('search-term', 'products', 'Zoeken...')}}">
                            </div>
                        </section>
                        <section class="space-y-2 mt-4">
                            <div class="mb-12">
                                <h4 class="text-lg md:text-2xl font-display truncate font-bold">
                                    {{ Translation::get('price', 'products', 'Prijs') }}
                                </h4>

                                <div class="w-8 h-1 bg-primary-500"></div>
                            </div>
                            <x-range-slider :options="$defaultSliderOptions"
                                            wire:model.lazy="priceSlider.min,priceSlider.max"/>
                        </section>

                        @if($productCategory && count($productCategory->getFirstChilds()))
                            <section class="space-y-2 mt-4">
                                <h4 class="text-lg md:text-2xl font-display text-wrap font-bold">
                                    Onderliggende categorieen
                                </h4>

                                <div class="w-8 h-1 bg-primary-500"></div>

                                <div class="flex flex-col space-y-1">
                                    @foreach ($productCategory->getFirstChilds() as $childCategory)
                                        <a href="{{ $childCategory->getUrl() }}"
                                           class="flex items-center space-x-2 cursor-pointer text-wrap transform hover:translate-x-1 trans">
                                            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24"
                                                 stroke-width="1.5" stroke="currentColor" class="size-6">
                                                <path stroke-linecap="round" stroke-linejoin="round"
                                                      d="m8.25 4.5 7.5 7.5-7.5 7.5"/>
                                            </svg>

                                            <label class="text-base cursor-pointer"
                                                   for="child-category-{{ $childCategory->id }}">{{$childCategory->name}}</label>
                                        </a>
                                    @endforeach
                                </div>
                            </section>
                        @endif

                        @foreach ($filters ?? [] as $filter)
                            @if($filter->hasActiveOptions)
                                <section class="space-y-2 mt-4" x-data="{ showFilter: false }">
                                    <div @click="showFilter = !showFilter"
                                         class="cursor-pointer flex items-center justify-between">
                                        <h4 class="text-lg md:text-2xl font-display truncate font-bold">
                                            {{$filter->name}}
                                        </h4>

                                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24"
                                             stroke-width="1.5" stroke="currentColor" class="size-6 trans"
                                             x-bind:class="showFilter ? 'rotate-90' : 'rotate-0'">
                                            <path stroke-linecap="round" stroke-linejoin="round"
                                                  d="m8.25 4.5 7.5 7.5-7.5 7.5"/>
                                        </svg>
                                    </div>

                                    <div class="w-8 h-1 bg-primary-500"></div>

                                    <div class="flex flex-col space-y-1" x-show="showFilter"
                                         x-transition.opacity.scale.origin.top>
                                        @foreach ($filter->productFilterOptions as $option)
                                            @if($option->resultCount > 0)
                                                <div class="flex items-center space-x-2 cursor-pointer truncate">
                                                    <input
                                                            wire:model.live="activeFilters.{{$filter->name}}.{{ $option->name }}"
                                                            type="checkbox"
                                                            id="filter-{{$filter->name}}-{{$option->id}}"
                                                            class="rounded-none form-checkbox text-primary cursor-pointer">
                                                    <label class="text-base cursor-pointer"
                                                           for="filter-{{$filter->name}}-{{$option->id}}">{{$option->name}}</label>
                                                </div>
                                            @endif
                                        @endforeach
                                    </div>
                                </section>
                            @endif
                        @endforeach
                    </aside>
                </div>
                <div class="col-span-4 lg:col-span-3 relative">
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
                            <div class="grid gap-8 items-start grid-cols-6">
                                @foreach($products as $product)
                                    <div class="col-span-3 lg:col-span-2">
                                        <x-product :product="$product"></x-product>
                                    </div>
                                @endforeach
                            </div>
                            <div class="col-span-6 mx-auto overflow-x-scroll mt-4">
                                {!! $products->onEachSide(0)->links('dashed.partials.pagination', data: [
                                    'scrollTo' => 'body'
                                ]) !!}
                            </div>
                        @else
                            <div>
                                <p>{{ Translation::get('no-products-found', 'products', 'No products found') }}</p>
                            </div>
                        @endif
                    </div>
                </div>
            </section>
        </x-container>
    </section>

    @if($productCategory)
        <x-blocks :content="$productCategory->content"></x-blocks>
    @endif

</div>
