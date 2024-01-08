<div x-data="{ showSearchbar: @entangle('showSearchbar') }" class="z-30">
    <form method="get" action="{{ Products::getOverviewPageUrl() }}" @class([
                'relative flex items-center h-10 bg-black/5 rounded-t-xl rounded-b-xl transition',
                'focus-within:ring-2 focus-within:ring-green focus-within:bg-white',
                'rounded-b-none' => $products && $products->count(),
            ])>
        <button class="grid w-10 h-10 place-items-center">
            <x-heroicon-o-search class="w-6 h-6 text-black/40"/>
        </button>

        <input
            @class([
                'pl-0 bg-transparent border-none w-96',
                'focus:border-none focus:ring-0',
            ])
            value="{{Request::get('search')}}"
            type="text" name="search"
            wire:model.debounce.500ms="search"
            placeholder="{{ Translation::get('search-products', 'searchbar', 'Search games, credits...') }}"
        >
        <div x-show="showSearchbar" @click.away="showSearchbar = false"
             class="bg-white shadow-xl w-full max-h-96 absolute w-[424px] top-10">
            <div
                class="[background-image:url('/assets/iwtg/files/pattern.svg')] absolute inset-0 bg-repeat [background-size:1rem] [mask-image:linear-gradient(white,rgba(255,255,255,0))] opacity-5">
            </div>
            <div class="overflow-y-auto max-h-96">
                <ul class="border-t divide-y border-black/5 divide-black/5">
                    @foreach($products ?: [] as $product)
                        <li class="grid relative items-center grid-cols-2 gap-6 p-4 lg:grid-cols-3">
                            @if($product->firstImageUrl)
                                <a href="{{ $product->getUrl() }}">
                                    <x-drift::image
                                        class="object-cover aspect-[3/2] rounded-lg"
                                        config="assets"
                                        :path="$product->firstImageUrl"
                                        :alt="$product->name"
                                        :manipulations="[
                                                        'widen' => 200,
                                                    ]"
                                    />
                                </a>
                            @endif

                            <a href="{{ $product->getUrl() }}" class="lg:col-span-2">
                                <p class="font-medium">
                                    {{ $product->name }}
                                    @if($product->type != 'physical' && $product->card_value_type == 'variable')
                                        ({{ CurrencyHelper::format($product->price) }})
                                    @endif
                                </p>
                            </a>
                        </li>
                    @endforeach
                </ul>
            </div>
        </div>
    </form>
</div>
