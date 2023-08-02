<section class="relative z-10 py-12">
    <x-container>
        <div class="grid grid-cols-1 md:grid-cols-2 gap-8">
            <div>
                <h2 class="text-3xl font-bold">
                    {{ $productCategory ? Translation::get('products-overview', 'products', 'Products from :category:', 'text', [
                        'category' => $productCategory->name
                    ]) : Translation::get('products-overview', 'products', 'Product overview') }}
                </h2>

                <p class="text-xl text-black text-opacity-60">{{$products->total()}} {{$products->total() == 1 ? Translation::get('result', 'products', 'result') : Translation::get('results', 'products', 'results')}}</p>
            </div>
            <div class="md:ml-auto">
                <label>{{Translation::get('filter-by', 'products', 'Sorteer op')}}:</label>
                <select wire:model="sortBy"
                        class="border-2 border-primary py-2 px-4 mx-4 focus:outline-none" name="sort-by"
                        id="sort-by">
                    <option
                        value="">{{Translation::get('filter-sort-by-standard', 'products', 'Standaard')}}</option>
                    <option @if($this->sortBy == 'price-asc') selected
                            @endif value="price-asc">{{Translation::get('filter-sort-by-price-asc', 'products', 'Prijs oplopend')}}</option>
                    <option @if($this->sortBy == 'price-desc') selected
                            @endif value="price-desc">{{Translation::get('filter-sort-by-price-desc', 'products', 'Prijs aflopend')}}</option>
                    <option @if($this->sortBy == 'most-sold')  selected
                            @endif value="most-sold">{{Translation::get('filter-sort-by-most-sold', 'products', 'Best verkocht')}}</option>
                    <option @if($this->sortBy == 'newest')  selected
                            @endif value="newest">{{Translation::get('filter-sort-by-newest', 'products', 'Nieuwste')}}</option>
                </select>
            </div>
        </div>

        <section x-data="{ filters: false }" class="grid grid-cols-4 items-start gap-8">
            <div class="col-span-4 md:col-span-1">
                <div class="mt-4 lg:hidden">
                    <div class="cursor-pointer inline-block px-6 py-2 text-center uppercase button--primary w-full"
                         x-on:click="filters = !filters">{{ucfirst(Translation::get('filter', 'products', 'Filters'))}}
                    </div>
                </div>

                <aside x-bind:class="filters ? 'flex' : 'hidden'"
                       class="flex-col mb-8 space-x-0 lg:flex lg:space-x-0 lg:space-y-8 lg:w-64 md:flex-col whitespace-nowrap pr-4">
                    <section class="space-y-2 mt-4">
                        <div
                            class="inline-flex items-center overflow-hidden">
                            <input type="text" class="form-input border border-primary" id="search" name="search"
                                   wire:model.debounce.500ms="search"
                                   placeholder="{{Translation::get('search-term', 'products', 'Zoeken...')}}">
                        </div>
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
                                                    wire:model="activeFilters.{{$filter->name}}.{{ $option->name }}"
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

            @if($products->count())
                <div class="grid gap-8 items-start grid-cols-6 col-span-4 md:col-span-3">
                    @foreach($products as $product)
                        <div class="col-span-3 lg:col-span-2">
                            <x-product :product="$product"></x-product>
                        </div>
                    @endforeach
                    <div class="col-span-6 mx-auto overflow-x-scroll">
                        {!! $products->onEachSide(0)->links('qcommerce.partials.pagination') !!}
                    </div>
                </div>
            @else
                <div>
                    <p>{{ Translation::get('no-products-found', 'products', 'No products found') }}</p>
                </div>
            @endif
        </section>
    </x-container>
</section>

@if($productCategory)
    <x-blocks :content="$productCategory->content"></x-blocks>
@endif
