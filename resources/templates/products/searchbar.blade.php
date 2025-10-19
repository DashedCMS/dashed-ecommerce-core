<div class="relative z-50 mt-1"
     x-data="{ showSearchBarOverview: false, showSearchbar: @entangle('showSearchbar').live }">
    <div x-cloak x-show="showSearchBarOverview"
         class="fixed right-0 top-5 w-full sm:w-auto px-2 sm:px-0 sm:absolute sm:-right-2 sm:-top-6">
        <div class="z-30">
            <form method="get" action="{{ \Dashed\DashedEcommerceCore\Models\Product::getOverviewPage()->getUrl() }}" @class([
                'relative flex items-center h-10 bg-primary-500 text-white rounded-t-xl rounded-b-xl transition',
                'focus-within:ring-2 focus-within:ring-primary-500 focus-within:bg-primary-500/80',
                'rounded-b-none' => $products && $products->count(),
            ])>
                <button class="grid w-10 h-10 place-items-center">
                    <x-heroicon-o-magnifying-glass class="w-6 h-6 text-white"/>
                </button>

                <div class="absolute right-2.5 top-2.5" @click="showSearchBarOverview = !showSearchBarOverview" x-cloak
                     x-show="showSearchBarOverview">
                    <x-icon-button icon="lucide-x" class="text-white hover:text-black"/>
                </div>

                <input
                    @class([
                        'pl-0 bg-transparent border-none w-96 placeholder:text-white',
                        'focus:border-none focus:ring-0',
                    ])
                    value="{{Request::get('search')}}"
                    type="text"
                    id="searchField"
                    name="search"
                    wire:model.live.debounce.500ms="search"
                    placeholder="{{ Translation::get('search-products', 'searchbar', 'Zoek een product...') }}"
                >
                <div x-show="showSearchbar" @click.away="showSearchbar = false"
                     class="bg-white text-black shadow-xl max-h-96 absolute w-full px-2 sm:w-[424px] top-12">
                    <div class="overflow-y-auto max-h-96">
                        <ul class="border-t divide-y border-black/5 divide-black/5">
                            @if($search && !count($products ?: []))
                                <li class="p-4">
                                    <p>{{ Translation::get('no-results-found', 'searchbar', 'Geen resultaten gevonden') }}</p>
                                </li>
                            @endif
                            @foreach($products ?: [] as $product)
                                <li class="grid relative items-center grid-cols-2 gap-6 p-4 lg:grid-cols-3">
                                    @if($product->firstImage)
                                        <a href="{{ $product->getUrl() }}">
                                            <x-dashed-files::image
                                                class="object-cover aspect-3/2 rounded-lg"
                                                config="dashed"
                                                :mediaId="$product->firstImage"
                                                :alt="$product->name"
                                                :manipulations="[
                                                        'widen' => 300,
                                                    ]"
                                            />
                                        </a>
                                    @endif

                                    <a href="{{ $product->getUrl() }}" class="lg:col-span-2">
                                        <p class="font-bold">
                                            {{ $product->name }}
                                            ({{ CurrencyHelper::formatPrice($product->currentPrice) }})
                                        </p>
                                    </a>
                                </li>
                            @endforeach
                        </ul>
                    </div>
                </div>
            </form>
        </div>
    </div>
    <div @click="showSearchBarOverview = !showSearchBarOverview" x-show="!showSearchBarOverview"
         onclick="focusOnSearch()">
        <x-icon-button icon="lucide-search"/>
    </div>
    <script>
        function focusOnSearch() {
            setTimeout(() => {
                document.getElementById('searchField').focus();
            }, 100);
        }
    </script>
</div>
