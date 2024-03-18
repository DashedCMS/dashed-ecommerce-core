<div>
    <section class="relative z-10 py-12">
        <x-container>
            <div class="grid grid-cols-1 lg:grid-cols-2 gap-8">
                <div>
                    <h2 class="text-3xl font-bold">
                        {{ $productCategory ? Translation::get('products-overview', 'products', 'Products from :category:', 'text', [
                            'category' => $productCategory->name
                        ]) : Translation::get('products-overview', 'products', 'Product overview') }}
                    </h2>

                    @if($products)
                        <p class="text-xl text-black text-opacity-60">{{$products->total()}} {{$products->total() == 1 ? Translation::get('result', 'products', 'result') : Translation::get('results', 'products', 'results')}}</p>
                    @endif
                </div>
                <div class="lg:ml-auto">
                    <label>{{Translation::get('filter-by', 'products', 'Sorteer op')}}:</label>
                    <select wire:model.live="sortBy"
                            class="border-2 border-primary py-2 px-4 mx-4 focus:outline-none choices" name="sort-by"
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
                                class="inline-flex items-center overflow-hidden">
                                <input type="text" class="form-input border border-primary" id="search" name="search"
                                       wire:model.live="search"
                                       placeholder="{{Translation::get('search-term', 'products', 'Zoeken...')}}">
                            </div>
                        </section>
                        <section class="space-y-2 mt-4">
                            <div class="mb-12">
                                <h4 class="text-lg md:text-2xl font-display truncate">
                                    {{ Translation::get('price', 'products', 'Price') }}
                                </h4>

                                <div class="w-8 h-1 bg-primary"></div>
                            </div>
                            <x-range-slider :options="$defaultSliderOptions" wire:model.lazy="priceSlider.min,priceSlider.max" />
                        </section>

                        @foreach ($filters ?? [] as $filter)
                            @if($filter->hasActiveOptions)
                                <section class="space-y-2 mt-4">
                                    <h4 class="text-lg md:text-2xl font-display truncate">
                                        {{$filter->name}}
                                    </h4>

                                    <div class="w-8 h-1 bg-primary"></div>

                                    <div class="flex flex-col space-y-1">
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
                                {!! $products->onEachSide(0)->links() !!}
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
