<div>
    <div class="mt-8">
        <x-container>
            @if($product)
                <x-dashed-ecommerce-core::frontend.products.schema :product="$product"/>
            @endif
            @if($productFaqs)
                <x-dashed-ecommerce-core::frontend.products.faq-schema :faqs="$productFaqs"/>
            @endif
            <div class="mx-auto max-w-2xl lg:max-w-none">
                <div class="lg:grid lg:grid-cols-2 lg:items-start lg:gap-x-8">
                    <div class="flex flex-col-reverse lg:sticky lg:top-32 bg-pattern rounded-lg">
                        <div class="w-full h-full select-none">
                            <div class="swiper my-product-swiper"
                                 data-slides-per-view="1"
                                 data-slides-per-view-md="1"
                                 data-space-between="0"
                                 data-loop="true">
                                <ul class="swiper-wrapper">
                                    @foreach($images as $image)
                                        <li class="swiper-slide">
                                            <img
                                                class="object-contain object-center w-full rounded-lg"
                                                src="{{ mediaHelper()->getSingleMedia($image, ['fit' => [800,800]])->url ?? '' }}"
                                            >
                                        </li>
                                    @endforeach
                                </ul>

                                @if(count($images) > 1)
                                    <div class="swiper-button-prev rounded-full h-12 w-12 bg-primary text-white"></div>
                                    <div class="swiper-button-next rounded-full h-12 w-12 bg-primary text-white"></div>
                                @endif
                            </div>

                            @if(count($images))
                                <div class="swiper swiper-thumbs mt-4">
                                    <ul class="swiper-wrapper">
                                        @foreach($images as $image)
                                            <li class="swiper-slide cursor-pointer">
                                                <img
                                                    class="object-contain object-center rounded-lg"
                                                    src="{{ mediaHelper()->getSingleMedia($image, ['fit' => [300,300]])->url ?? '' }}"
                                                >
                                            </li>
                                        @endforeach
                                    </ul>
                                </div>
                            @endif
                        </div>
                    </div>

                    <div class="mt-10 px-4 sm:mt-16 sm:px-0 lg:mt-0">
                        <div class="my-4 flex flex-wrap items-center md:gap-8">
                            <valued-widget layout="button" size="medium" align="left" theme="dark"
                                           class="z-20"></valued-widget>
                            {{--                            <div class="flex flex-wrap items-center justify-start gap-2">--}}
                            {{--                                <div class="flex gap-2 items-center justify-center">--}}

                            {{--                                    <x-dashed-files::image--}}
                            {{--                                            class="w-6 rounded-xl"--}}
                            {{--                                            config="dashed"--}}
                            {{--                                            :mediaId="Translation::get('product-review-image', 'product', '', 'image')"--}}
                            {{--                                            alt=""--}}
                            {{--                                            :manipulations="[--}}
                            {{--                                                        'widen' => 500,--}}
                            {{--                                                    ]"--}}
                            {{--                                    />--}}
                            {{--                                    <div class="flex gap-0 items-center justify-center">--}}
                            {{--                                        @for($i = 5; $i > 0; $i--)--}}
                            {{--                                            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24"--}}
                            {{--                                                 fill="currentColor"--}}
                            {{--                                                 class="size-6 text-yellow-500">--}}
                            {{--                                                <path fill-rule="evenodd"--}}
                            {{--                                                      d="M10.788 3.21c.448-1.077 1.976-1.077 2.424 0l2.082 5.006 5.404.434c1.164.093 1.636 1.545.749 2.305l-4.117 3.527 1.257 5.273c.271 1.136-.964 2.033-1.96 1.425L12 18.354 7.373 21.18c-.996.608-2.231-.29-1.96-1.425l1.257-5.273-4.117-3.527c-.887-.76-.415-2.212.749-2.305l5.404-.434 2.082-5.005Z"--}}
                            {{--                                                      clip-rule="evenodd"/>--}}
                            {{--                                            </svg>--}}
                            {{--                                        @endfor--}}
                            {{--                                    </div>--}}
                            {{--                                </div>--}}

                            {{--                                <p class="text-primary text-xs flex gap-1 items-center justify-start">--}}
                            {{--                                    {{ (floor(Customsetting::get('google_maps_review_count') / 10) * 10)  . '+'}}--}}
                            {{--                                    <span class="font-bold"> {{ Translation::get('reviews', 'product', 'reviews') }}</span>--}}
                            {{--                                </p>--}}
                            {{--                            </div>--}}
                        </div>
                        <h1 class="text-3xl font-bold tracking-tight text-gray-900">{{ $name }}</h1>
                        @if($product && $product->productCategories()->count())
                            <div class="flex flex-wrap gap-2">
                                @foreach($product->productCategories as $productCategory)
                                    <a href="{{ $productCategory->getUrl() }}"
                                       class="mt-1 text-sm bg-primary font-bold text-white px-2 py-1 rounded-lg hover:bg-primary/70 trans">{{$productCategory->name}}</a>
                                @endforeach
                            </div>
                        @endif

                        <div class="mt-3">
                            <h2 class="sr-only">{{ Translation::get('product-information', 'products', 'Product information') }}</h2>
                            <div class="flex flex-wrap gap-2 items-center">
                                @if($discountPrice)
                                    <span class="line-through text-red-500 mr-2 font-normal">
                                    {{CurrencyHelper::formatPrice($discountPrice)}}
                                </span>
                                @endif
                                <p class="text-3xl tracking-tight font-bold text-primary">{{ CurrencyHelper::formatPrice($price) }}</p>
                            </div>
                            {{--                                @if(Customsetting::get('taxes_prices_include_taxes'))--}}
                            {{--                                    {{ Translation::get('product-including-tax', 'products', 'incl. TAX') }}--}}
                            {{--                                @else--}}
                            {{--                                    {{ Translation::get('product-excluding-tax', 'products', 'excl. TAX') }}--}}
                            {{--                                @endif--}}
                        </div>

                        @if(count($globalDiscounts))
                            <div class="flex flex-wrap gap-2 mt-2">
                                @foreach($globalDiscounts as $globalDiscount)
                                    <div class="text-sm bg-primary font-bold text-primary px-2 py-1 rounded-lg  trans">
                                        @if($globalDiscount->end_date)
                                            {{ Translation::get('global-discount-with-end-date', 'products', ':name:: :value::discountType: korting tot :endDate:', 'text', [
        'name'  =>$globalDiscount->name,
        'discountType' => $globalDiscount->type == 'percentage' ? '%' : '',
        'value' => $globalDiscount->type == 'percentage' ? $globalDiscount->discount_percentage : CurrencyHelper::formatPrice($globalDiscount->discount_amount),
        'endDate' => $globalDiscount->end_date->format('d-m-Y')
    ]) }}
                                        @else
                                            {{ Translation::get('global-discount', 'products', ':name:: :value::discountType: korting', 'text', [
        'name'  =>$globalDiscount->name,
        'discountType' => $globalDiscount->type == 'percentage' ? '%' : '',
        'value' => $globalDiscount->type == 'percentage' ? $globalDiscount->discount_percentage : CurrencyHelper::formatPrice($globalDiscount->discount_amount),
    ]) }}
                                        @endif
                                    </div>
                                @endforeach
                            </div>
                        @endif

                        <div class="mt-6 grid gap-2">
                            @if($product)
                                <x-product.stock-text :product="$product"/>
                            @endif

                            <div class="flex items-center text-sm">
                                <x-dashed-files::image
                                    class="h-8 rounded-lg mr-2"
                                    :mediaId="Translation::get('pay-in-terms-logo', 'products', '', 'image')"
                                />
                                <div>
                                    {!! Translation::get('pay-in-terms', 'products', 'Betaal in 3 termijnen: <b>:term:</b> per termijn', 'text', [
                                    'term' => CurrencyHelper::formatPrice($price / 3),
                                    ]) !!}
                                </div>
                            </div>

                            <div class="flex items-center text-sm">
                                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor"
                                     class="h-8 mr-2 text-primary">
                                    <path
                                        d="M3.375 4.5C2.339 4.5 1.5 5.34 1.5 6.375V13.5h12V6.375c0-1.036-.84-1.875-1.875-1.875h-8.25ZM13.5 15h-12v2.625c0 1.035.84 1.875 1.875 1.875h.375a3 3 0 1 1 6 0h3a.75.75 0 0 0 .75-.75V15Z"/>
                                    <path
                                        d="M8.25 19.5a1.5 1.5 0 1 0-3 0 1.5 1.5 0 0 0 3 0ZM15.75 6.75a.75.75 0 0 0-.75.75v11.25c0 .087.015.17.042.248a3 3 0 0 1 5.958.464c.853-.175 1.522-.935 1.464-1.883a18.659 18.659 0 0 0-3.732-10.104 1.837 1.837 0 0 0-1.47-.725H15.75Z"/>
                                    <path d="M19.5 19.5a1.5 1.5 0 1 0-3 0 1.5 1.5 0 0 0 3 0Z"/>
                                </svg>


                                <div>
                                    {!! Translation::get('shipping-free-over', 'products', 'Gratis verzending in <b>Nederland</b> & <b>Belgie</b> vanaf €99,-', 'editor', [
                                    'term' => CurrencyHelper::formatPrice($price / 3),
                                    ]) !!}
                                </div>
                            </div>

                            @if(count($paymentMethods))
                                <div class="flex items-center gap-2">
                                    <div class="flex items-center justify-center text-sm gap-1">
                                        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor"
                                             class="size-8 text-green-500">
                                            <path fill-rule="evenodd"
                                                  d="M12 1.5a5.25 5.25 0 0 0-5.25 5.25v3a3 3 0 0 0-3 3v6.75a3 3 0 0 0 3 3h10.5a3 3 0 0 0 3-3v-6.75a3 3 0 0 0-3-3v-3c0-2.9-2.35-5.25-5.25-5.25Zm3.75 8.25v-3a3.75 3.75 0 1 0-7.5 0v3h7.5Z"
                                                  clip-rule="evenodd"/>
                                        </svg>

                                        <h3 class="font-normal">{{ Translation::get('pay-safe-with', 'products', 'Betaal veilig met') }}</h3>
                                    </div>
                                    <div class="flex gap-2 flex-wrap items-center justify-center">
                                        @foreach($paymentMethods as $paymentMethod)
                                            @if($paymentMethod->image)
                                                <x-dashed-files::image
                                                    :mediaId="$paymentMethod->image"
                                                    :alt="$paymentMethod->name"
                                                    :manipulations="[ 'widen' => 100 ]"
                                                    class="w-8"
                                                />
                                            @endif
                                        @endforeach
                                    </div>
                                </div>
                            @endif

                            {{--                            @if($product->contentBlocks['delivery_time'] ?? false)--}}
                            {{--                                <p class="mt-1 text-orange-500 flex flex-wrap gap-1 items-center">--}}
                            {{--                                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24"--}}
                            {{--                                         stroke-width="1.5" stroke="currentColor" class="size-6">--}}
                            {{--                                        <path stroke-linecap="round" stroke-linejoin="round"--}}
                            {{--                                              d="M12 9v3.75m-9.303 3.376c-.866 1.5.217 3.374 1.948 3.374h14.71c1.73 0 2.813-1.874 1.948-3.374L13.949 3.378c-.866-1.5-3.032-1.5-3.898 0L2.697 16.126ZM12 15.75h.007v.008H12v-.008Z"/>--}}
                            {{--                                    </svg>--}}

                            {{--                                    <span>{{ $product->contentBlocks['delivery_time'] }}</span>--}}
                            {{--                                </p>--}}
                            {{--                            @endif--}}
                        </div>

                        @if($shortDescription)
                            <div class="mt-6">
                                <h3 class="sr-only">{{ Translation::get('product-short-description', 'products', 'Korte beschrijving') }}</h3>

                                <div class="space-y-6 text-base text-gray-700">
                                    <p>
                                        {{ $shortDescription }}
                                    </p>
                                </div>
                            </div>
                        @endif

                        @if($description)
                            <div class="mt-6">
                                <h3 class="sr-only">{{ Translation::get('product-description', 'products', 'Beschrijving') }}</h3>

                                <div class="space-y-6 text-base text-gray-700 prose">
                                    {!! cms()->convertToHtml($description) !!}
                                </div>
                            </div>
                        @endif

                        <div class="mt-6">
                            <x-cart.add-to-cart :product="$product" :filters="$filters" :productExtras="$productExtras"
                                                :extras="$extras"
                                                :quantity="$quantity" :price="$price" :discountPrice="$discountPrice"/>
                        </div>

                        {{--                        @if(count($paymentMethods))--}}
                        {{--                            <div class="mt-6 grid gap-2">--}}
                        {{--                                <div class="flex items-center justify-center text-xs gap-1">--}}
                        {{--                                    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor"--}}
                        {{--                                         class="size-4 text-green-500">--}}
                        {{--                                        <path fill-rule="evenodd"--}}
                        {{--                                              d="M12 1.5a5.25 5.25 0 0 0-5.25 5.25v3a3 3 0 0 0-3 3v6.75a3 3 0 0 0 3 3h10.5a3 3 0 0 0 3-3v-6.75a3 3 0 0 0-3-3v-3c0-2.9-2.35-5.25-5.25-5.25Zm3.75 8.25v-3a3.75 3.75 0 1 0-7.5 0v3h7.5Z"--}}
                        {{--                                              clip-rule="evenodd"/>--}}
                        {{--                                    </svg>--}}


                        {{--                                    <h3>{{ Translation::get('pay-safe-with', 'products', 'Betaal veilig met') }}</h3>--}}
                        {{--                                </div>--}}
                        {{--                                <div class="flex gap-4 flex-wrap items-center justify-center">--}}
                        {{--                                    @foreach($paymentMethods as $paymentMethod)--}}
                        {{--                                        @if($paymentMethod->image)--}}
                        {{--                                            <x-dashed-files::image--}}
                        {{--                                                    :mediaId="$paymentMethod->image"--}}
                        {{--                                                    :alt="$paymentMethod->name"--}}
                        {{--                                                    :manipulations="[ 'widen' => 100 ]"--}}
                        {{--                                                    class="w-8"--}}
                        {{--                                            />--}}
                        {{--                                        @endif--}}
                        {{--                                    @endforeach--}}
                        {{--                                </div>--}}
                        {{--                            </div>--}}
                        {{--                        @endif--}}

                        <div wire:key="product-cross-sells-{{ $product->id ?? $productGroup->id }}">
                            @if(count($crossSellProducts))
                                <div class="mt-6 grid gap-4">
                                    <h3 class="text-sm font-bold text-gray-900 text-center">{{ Translation::get('product-cross-sell', 'product', 'Vaak samen gekocht') }}</h3>
                                    <div class="grid gap-4">
                                        @foreach($crossSellProducts as $crossSellProduct)
                                            <x-product.cross-sell-product :product="$crossSellProduct"/>
                                        @endforeach
                                    </div>
                                </div>
                            @endif
                        </div>

                        <div class="mt-6 grid gap-6" wire:key="product-tabs-{{ $product->id ?? $productGroup->id }}"
                             x-data="{
                                activeTab: '',

                                openTab(tab) {
                                    this.activeTab = this.activeTab == tab ? '' : tab;
                                },
                            }">
                            @if($description && false)
                                <div class="bg-gray-100 rounded-lg">
                                    <div class="flex flex-wrap items-center justify-between cursor-pointer-not p-4"
                                         @click="openTab('description')">
                                        <h3>{{ Translation::get('product-description', 'products', 'Beschrijving') }}</h3>
                                        {{--                                        <svg x-show="activeTab == 'description'"--}}
                                        {{--                                             xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor"--}}
                                        {{--                                             class="size-6">--}}
                                        {{--                                            <path fill-rule="evenodd"--}}
                                        {{--                                                  d="M4.25 12a.75.75 0 0 1 .75-.75h14a.75.75 0 0 1 0 1.5H5a.75.75 0 0 1-.75-.75Z"--}}
                                        {{--                                                  clip-rule="evenodd"/>--}}
                                        {{--                                        </svg>--}}
                                        {{--                                        <svg x-cloak x-show="activeTab != 'description'"--}}
                                        {{--                                             xmlns="http://www.w3.org/2000/svg"--}}
                                        {{--                                             viewBox="0 0 24 24" fill="currentColor" class="size-6">--}}
                                        {{--                                            <path fill-rule="evenodd"--}}
                                        {{--                                                  d="M12 3.75a.75.75 0 0 1 .75.75v6.75h6.75a.75.75 0 0 1 0 1.5h-6.75v6.75a.75.75 0 0 1-1.5 0v-6.75H4.5a.75.75 0 0 1 0-1.5h6.75V4.5a.75.75 0 0 1 .75-.75Z"--}}
                                        {{--                                                  clip-rule="evenodd"/>--}}
                                        {{--                                        </svg>--}}
                                    </div>
                                    <div
                                        class="px-4 pb-4 prose-base"
                                        {{--                                            x-cloak--}}
                                        {{--                                            x-show="activeTab == 'description'"--}}
                                        x-transition.opacity.scale.origin.top
                                    >
                                        {!! cms()->convertToHtml($description) !!}
                                    </div>
                                </div>
                            @endif

                            @if($characteristics)
                                <div class="bg-gray-100 rounded-lg">
                                    <div class="flex flex-wrap items-center justify-between cursor-pointer-not p-4"
                                         @click="openTab('characteristics')">
                                        <h3>{{ Translation::get('product-characteristics', 'product', 'Productkenmerken') }}</h3>
                                        {{--                                        <svg x-cloak x-show="activeTab != 'characteristics'"--}}
                                        {{--                                             xmlns="http://www.w3.org/2000/svg"--}}
                                        {{--                                             viewBox="0 0 24 24" fill="currentColor" class="size-6">--}}
                                        {{--                                            <path fill-rule="evenodd"--}}
                                        {{--                                                  d="M12 3.75a.75.75 0 0 1 .75.75v6.75h6.75a.75.75 0 0 1 0 1.5h-6.75v6.75a.75.75 0 0 1-1.5 0v-6.75H4.5a.75.75 0 0 1 0-1.5h6.75V4.5a.75.75 0 0 1 .75-.75Z"--}}
                                        {{--                                                  clip-rule="evenodd"/>--}}
                                        {{--                                        </svg>--}}
                                        {{--                                        <svg x-show="activeTab == 'characteristics'"--}}
                                        {{--                                             xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor"--}}
                                        {{--                                             class="size-6">--}}
                                        {{--                                            <path fill-rule="evenodd"--}}
                                        {{--                                                  d="M4.25 12a.75.75 0 0 1 .75-.75h14a.75.75 0 0 1 0 1.5H5a.75.75 0 0 1-.75-.75Z"--}}
                                        {{--                                                  clip-rule="evenodd"/>--}}
                                        {{--                                        </svg>--}}
                                    </div>
                                    <div
                                        class="px-4 pb-4"
                                        {{--                                            x-cloak--}}
                                        {{--                                            x-show="activeTab == 'characteristics'"--}}
                                        x-transition.opacity.scale.origin.top
                                    >
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
                                </div>
                            @endif

                            @if($productFaqs->count())
                                @foreach($productFaqs as $faqGroup)
                                    <div class="bg-gray-100">
                                        <div class="flex flex-wrap items-center justify-between cursor-pointer-not p-4"
                                             @click="openTab('faq')">
                                            <h3>{{ $faqGroup->name }}</h3>
                                            {{--                                        <svg x-cloak x-show="activeTab != 'faq'" xmlns="http://www.w3.org/2000/svg"--}}
                                            {{--                                             viewBox="0 0 24 24" fill="currentColor" class="size-6">--}}
                                            {{--                                            <path fill-rule="evenodd"--}}
                                            {{--                                                  d="M12 3.75a.75.75 0 0 1 .75.75v6.75h6.75a.75.75 0 0 1 0 1.5h-6.75v6.75a.75.75 0 0 1-1.5 0v-6.75H4.5a.75.75 0 0 1 0-1.5h6.75V4.5a.75.75 0 0 1 .75-.75Z"--}}
                                            {{--                                                  clip-rule="evenodd"/>--}}
                                            {{--                                        </svg>--}}
                                            {{--                                        <svg x-show="activeTab == 'faq'"--}}
                                            {{--                                             xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor"--}}
                                            {{--                                             class="size-6">--}}
                                            {{--                                            <path fill-rule="evenodd"--}}
                                            {{--                                                  d="M4.25 12a.75.75 0 0 1 .75-.75h14a.75.75 0 0 1 0 1.5H5a.75.75 0 0 1-.75-.75Z"--}}
                                            {{--                                                  clip-rule="evenodd"/>--}}
                                            {{--                                        </svg>--}}
                                        </div>
                                        <div
                                            class="px-4 pb-4"
                                            {{--                                            x-cloak--}}
                                            {{--                                            x-show="activeTab == 'faq'"--}}
                                            x-transition.opacity.scale.origin.top
                                        >
                                            <div class="grid gap-4" x-data="{ openFaq: '' }">
                                                @foreach($faqGroup->questions as $faq)
                                                    <div class="bg-white">
                                                        <div
                                                            class="flex flex-wrap items-center justify-between cursor-pointer p-4"
                                                            @click="openFaq == '{{ $loop->iteration }}' ? openFaq = '' : openFaq = '{{ $loop->iteration }}'">
                                                            <h3>{{ $faq['question'] }}</h3>
                                                            <svg x-cloak x-show="openFaq != '{{ $loop->iteration }}'"
                                                                 xmlns="http://www.w3.org/2000/svg"
                                                                 viewBox="0 0 24 24" fill="currentColor" class="size-6">
                                                                <path fill-rule="evenodd"
                                                                      d="M12 3.75a.75.75 0 0 1 .75.75v6.75h6.75a.75.75 0 0 1 0 1.5h-6.75v6.75a.75.75 0 0 1-1.5 0v-6.75H4.5a.75.75 0 0 1 0-1.5h6.75V4.5a.75.75 0 0 1 .75-.75Z"
                                                                      clip-rule="evenodd"/>
                                                            </svg>
                                                            <svg x-show="openFaq == '{{ $loop->iteration }}'"
                                                                 xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24"
                                                                 fill="currentColor"
                                                                 class="size-6">
                                                                <path fill-rule="evenodd"
                                                                      d="M4.25 12a.75.75 0 0 1 .75-.75h14a.75.75 0 0 1 0 1.5H5a.75.75 0 0 1-.75-.75Z"
                                                                      clip-rule="evenodd"/>
                                                            </svg>
                                                        </div>
                                                        <div
                                                            class="px-4 pb-4"
                                                            x-cloak
                                                            x-show="openFaq == '{{ $loop->iteration }}'"
                                                            x-transition.opacity.scale.origin.top
                                                        >
                                                            <div class="grid gap-4">
                                                                {!! cms()->convertToHtml($faq['answer']) !!}
                                                            </div>
                                                        </div>
                                                    </div>
                                                @endforeach
                                            </div>
                                        </div>
                                    </div>
                                @endforeach
                            @endif

                            @foreach($productTabs as $key => $productTab)
                                <div class="bg-gray-100 rounded-lg">
                                    <div class="flex flex-wrap items-center justify-between cursor-pointer-not p-4"
                                         @click="openTab('tab-{{ $key }}')">
                                        <h3>{{ $productTab->name }}</h3>
                                        <svg x-show="activeTab == 'tab-{{ $key }}'"
                                             xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor"
                                             class="size-6">
                                            <path fill-rule="evenodd"
                                                  d="M4.25 12a.75.75 0 0 1 .75-.75h14a.75.75 0 0 1 0 1.5H5a.75.75 0 0 1-.75-.75Z"
                                                  clip-rule="evenodd"/>
                                        </svg>
                                        <svg x-cloak x-show="activeTab != 'tab-{{ $key }}'"
                                             xmlns="http://www.w3.org/2000/svg"
                                             viewBox="0 0 24 24" fill="currentColor" class="size-6">
                                            <path fill-rule="evenodd"
                                                  d="M12 3.75a.75.75 0 0 1 .75.75v6.75h6.75a.75.75 0 0 1 0 1.5h-6.75v6.75a.75.75 0 0 1-1.5 0v-6.75H4.5a.75.75 0 0 1 0-1.5h6.75V4.5a.75.75 0 0 1 .75-.75Z"
                                                  clip-rule="evenodd"/>
                                        </svg>
                                    </div>
                                    <div
                                        class="px-4 pb-4 prose"
                                        x-cloak
                                        x-show="activeTab == 'tab-{{ $key }}'"
                                        x-transition.opacity.scale.origin.top
                                    >
                                        {!! cms()->convertToHtml($productTab->content) !!}
                                    </div>
                                </div>
                            @endforeach
                        </div>
                    </div>
                </div>

                @if(count($suggestedProducts))
                    <section aria-labelledby="related-heading"
                             class="mt-10 border-t border-gray-200 px-4 py-16 sm:px-0">
                        <h2 id="related-heading"
                            class="text-xl md:text-3xl font-bold text-primary">{{Translation::get('suggested-products', 'product', 'Aanbevolen producten')}}</h2>

                        <div class="mt-8 grid grid-cols-1 gap-y-12 sm:grid-cols-2 sm:gap-x-6 lg:grid-cols-4 xl:gap-x-8">
                            @foreach($suggestedProducts as $suggestedProduct)
                                <x-product.product :product="$suggestedProduct"/>
                            @endforeach
                        </div>
                    </section>
                @else
                    <div class="py-12"></div>
                @endif

                @if($recentlyViewedProducts && $recentlyViewedProducts->count())
                    <section aria-labelledby="related-heading"
                             class="mt-10 border-t border-gray-200 px-4 py-16 sm:px-0">
                        <h2 id="related-heading"
                            class="text-xl md:text-3xl font-bold text-primary">{{Translation::get('recent-viewed', 'product', 'Recent bekeken')}}</h2>

                        <div class="mt-8 grid grid-cols-1 gap-y-12 sm:grid-cols-2 sm:gap-x-6 lg:grid-cols-4 xl:gap-x-8">
                            @foreach($recentlyViewedProducts as $product)
                                <x-product.product :product="$product"/>
                            @endforeach
                        </div>
                    </section>
                @endif
            </div>
        </x-container>

        <x-blocks :content="$content"></x-blocks>

        <div wire:ignore>
            <x-dashed-core::global-blocks name="product-page"/>
        </div>
    </div>
</div>
