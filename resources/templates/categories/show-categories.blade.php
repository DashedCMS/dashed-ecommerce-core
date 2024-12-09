<div class="bg-gray-100">
    <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
        <div class="mx-auto max-w-2xl py-16 sm:py-24 lg:max-w-none lg:py-32">
            <h2 class="text-2xl font-bold text-gray-900">
                {{ $productCategory->name ?? Translation::get('all-categories', 'categories', 'Alle categorieen') }}
            </h2>

            @if($productCategories->count() ?? false)
                <div class="mt-6 space-y-12 lg:grid lg:grid-cols-3 lg:gap-8 lg:space-y-0">
                    @foreach($productCategories as $productCategory)
                        @if($productCategory->products->count())
                            <div class="group relative">
                                <div class="relative h-80 w-full overflow-hidden rounded-lg bg-white sm:aspect-h-1 sm:aspect-w-2 lg:aspect-h-1 lg:aspect-w-1 group-hover:opacity-75 sm:h-64">
                                    @if($productCategory->image)
                                        <x-drift::image
                                            class="h-full w-full object-cover object-center"
                                            config="dashed"
                                            :path="$productCategory->image"
                                            :alt="$productCategory->name"
                                            :manipulations="[
                                                    'fit' => [600,600],
                                                ]"
                                        />
                                    @endif
                                </div>
                                <h3 class="mt-6 text-sm text-gray-500">
                                    <a href="{{$productCategory->getUrl()}}">
                                        <span class="absolute inset-0"></span>
                                        {{ Translation::get('amount-of-products', 'categories', ':amount: producten', 'text', [
                                            'amount' => $productCategory->products->count() ?? 0
                                        ]) }}
                                    </a>
                                </h3>
                                <p class="text-base font-semibold text-gray-900">{{$productCategory->name}}</p>
                            </div>
                        @endif
                    @endforeach
                </div>
            @else
                <div class="mt-6">
                    <p>{{ Translation::get('no-underlying-categories-found', 'categories', 'Geen onderliggende categorieen gevonden') }}</p>
                </div>
            @endif
        </div>
    </div>

    @if($singleProductCategory)
        <x-blocks :content="$singleProductCategory->content"></x-blocks>
    @endif
</div>

<x-dashed-core::global-blocks name="category-overview-page"/>
