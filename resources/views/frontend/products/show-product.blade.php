<div>
    <x-blocks.breadcrumbs :breadcrumbs="$product->breadcrumbs()"/>
    <div class="mt-8">
        <x-container>
            <x-dashed-ecommerce-core::frontend.products.schema
                :product="$product"></x-dashed-ecommerce-core::frontend.products.schema>
            <div class="mx-auto max-w-2xl lg:max-w-none">
                <div class="lg:grid lg:grid-cols-2 lg:items-start lg:gap-x-8">
                    <div class="flex flex-col-reverse">
                        <div class="aspect-h-1 aspect-w-1 w-full">
                            @if($images[0])
                                <x-drift::image
                                    class="h-full w-full object-cover object-center sm:rounded-lg"
                                    config="dashed"
                                    :path="$images[0]['image']"
                                    :alt=" $product->name"
                                    :manipulations="[
                                        'widen' => 1000,
                                    ]"
                                />
                            @endif
                        </div>
                    </div>

                    <div class="mt-10 px-4 sm:mt-16 sm:px-0 lg:mt-0">
                        <h2 class="text-3xl font-bold tracking-tight text-gray-900">{{ $name }}</h2>
                        @if($product->productCategories()->count())
                            <p class="mt-1 text-sm text-gray-500">{{$product->productCategories()->first()->name}}</p>
                        @endif

                        <div class="mt-3">
                            <h2 class="sr-only">{{ Translation::get('product-information', 'products', 'Product information') }}</h2>
                            <p class="text-3xl tracking-tight text-gray-900">{{ CurrencyHelper::formatPrice($price) }}
                                @if(Customsetting::get('taxes_prices_include_taxes'))
                                    {{ Translation::get('product-including-tax', 'products', 'incl. TAX') }}
                                @else
                                    {{ Translation::get('product-excluding-tax', 'products', 'excl. TAX') }}
                                @endif
                            </p>
                            @if($discountPrice)
                                <span
                                    class="text-sm line-through ml-2">{{CurrencyHelper::formatPrice($discountPrice)}}</span>
                            @endif
                        </div>

                        <div class="mt-6">
                            @if($product && $product->purchasable())
                                <p class="text-md tracking-wider text-primary-600 flex items-center font-bold"><span
                                        class="mr-1"><svg class="w-6 h-6" fill="none" stroke="currentColor"
                                                          viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg"><path
                                                stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
                                            </span>
                                    {{Translation::get('product-in-stock', 'product', 'In stock')}}
                                </p>
                            @else
                                <p class="text-md tracking-wider text-red-500 flex items-center font-bold"><span
                                        class="mr-1"><svg class="w-6 h-6" fill="none" stroke="currentColor"
                                                          viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg"><path
                                                stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                d="M10 14l2-2m0 0l2-2m-2 2l-2-2m2 2l2 2m7-2a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg></span>{{Translation::get('product-out-of-stock', 'product', 'Out of stock')}}
                                </p>
                            @endif
                        </div>

                        @if($shortDescription)
                            <div class="mt-6">
                                <h3 class="sr-only">{{ Translation::get('product-description', 'products', 'Description') }}</h3>

                                <div class="space-y-6 text-base text-gray-700">
                                    <p>
                                        {{ $shortDescription }}
                                    </p>
                                </div>
                            </div>
                        @endif

                        <div class="mt-6">
                            <livewire:cart.add-to-cart :product="$product"/>
                        </div>

                        @if($description)
                            <div class="mt-6">
                                <h3 class="sr-only">{{ Translation::get('product-description', 'products', 'Description') }}</h3>

                                <div class="space-y-6 text-base text-gray-700">
                                    {!! $description !!}
                                </div>
                            </div>
                        @endif

                        @if($characteristics)
                            <div class="mt-6 bg-gradient-to-tr from-primary-400 to-primary-600 text-white p-4">
                                <h3 class="sr-only">{{Translation::get('product-characteristics', 'product', 'Productkenmerken')}}</h3>
                                <div class="grid grid-cols-2 gap-4">
                                    @foreach($characteristics as $characteristic)
                                        <div class="font-bold">{{$characteristic['name']}}</div>
                                        <div>{{$characteristic['value']}}</div>
                                        @if(!$loop->last)
                                            <hr class="col-span-2">
                                        @endif
                                    @endforeach
                                </div>
                            </div>
                        @endif
                    </div>
                </div>

                <section aria-labelledby="related-heading" class="mt-10 border-t border-gray-200 px-4 py-16 sm:px-0">
                    <h2 id="related-heading"
                        class="text-xl font-bold text-gray-900">{{Translation::get('suggested-products', 'product', 'Also interesting')}}</h2>

                    <div class="mt-8 grid grid-cols-1 gap-y-12 sm:grid-cols-2 sm:gap-x-6 lg:grid-cols-4 xl:gap-x-8">
                        @foreach($suggestedProducts as $suggestedProduct)
                            <x-product :product="$suggestedProduct"></x-product>
                        @endforeach
                    </div>
                </section>
            </div>
        </x-container>

        <x-blocks :content="$product->content"></x-blocks>
    </div>
</div>
