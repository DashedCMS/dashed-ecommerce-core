<div class="relative mt-2 z-50"  x-data="{ showSearchBarOverview: false, showSearchbar: @entangle('showSearchbar').live }">
    <div x-cloak x-show="showSearchBarOverview" class="fixed right-0 top-5 w-full sm:w-auto px-2 sm:px-0 sm:absolute sm:-right-2 sm:-top-6">
        <div class="z-30">
            <form method="get" action="{{ \Dashed\DashedEcommerceCore\Models\Product::getOverviewPage()->getUrl() }}" @class([
                'relative flex items-center h-10 bg-baby-blue rounded-t-xl rounded-b-xl transition',
                'focus-within:ring-2 focus-within:ring-baby-blue focus-within:bg-baby-blue/80',
                'rounded-b-none' => $products && $products->count(),
            ])>
                <button class="grid w-10 h-10 place-items-center">
                    <x-heroicon-o-magnifying-glass class="w-6 h-6 text-white"/>
                </button>

                <div class="absolute right-2.5 top-2.5" @click="showSearchBarOverview = !showSearchBarOverview" x-cloak x-show="showSearchBarOverview">
                    <x-icon-button icon="lucide-x"/>
                </div>

                <input
                    @class([
                        'pl-0 bg-transparent border-none w-96 placeholder:text-white',
                        'focus:border-none focus:ring-0',
                    ])
                    value="{{Request::get('search')}}"
                    type="text"
                    name="search"
                    wire:model.live.debounce.500ms="search"
                    placeholder="{{ Translation::get('search-products', 'searchbar', 'Zoek een product...') }}"
                >
                <div x-show="showSearchbar" @click.away="showSearchbar = false"
                     class="bg-white shadow-xl max-h-96 absolute w-full px-2 sm:w-[424px] top-12">
                    <div class="overflow-y-auto max-h-96">
                        <ul class="border-t divide-y border-black/5 divide-black/5">
                            @foreach($products ?: [] as $product)
                                <li class="grid relative items-center grid-cols-2 gap-6 p-4 lg:grid-cols-3">
                                    @if($product->firstImageUrl)
                                        <a href="{{ $product->getUrl() }}">
                                            <x-drift::image
                                                class="object-cover aspect-[3/2] rounded-lg"
                                                config="dashed"
                                                :path="$product->firstImageUrl"
                                                :alt="$product->name"
                                                :manipulations="[
                                                        'widen' => 300,
                                                    ]"
                                            />
                                        </a>
                                    @endif

                                    <a href="{{ $product->getUrl() }}" class="lg:col-span-2">
                                        <p class="font-medium">
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
    <div @click="showSearchBarOverview = !showSearchBarOverview" x-show="!showSearchBarOverview">
        <x-icon-button icon="lucide-search"/>
    </div>
</div>
