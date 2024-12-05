<div class="{{ $data['backgroundColor'] ?? 'bg-white' }} @if($data['top_margin'] ?? true) pt-24 sm:pt-36 @endif @if($data['bottom_margin'] ?? true) pb-24 sm:pb-36 @endif">
    <x-container :show="$data['in_container'] ?? true">
        <div class="mx-auto max-w-2xl lg:max-w-none">
            <div class="text-center"
                 data-aos="fade-up">
                @if($data['title'] ?? false)
                    <h2 class="text-balance text-4xl font-semibold tracking-tight text-gray-900 sm:text-5xl">{{ $data['title'] }}</h2>
                @endif
                @if($data['subtitle'] ?? false)
                    <p class="mt-4 text-lg/8 text-gray-600">{{ $data['subtitle'] }}</p>
                @endif
            </div>
            @if($data['products'] ?? false)
                @php($products = \Dashed\DashedEcommerceCore\Models\Product::publicShowable()->whereIn('id', $data['products'])->get())
            @elseif($products ?? false)
                @php($products = $products)
            @elseif($data['useCartRelatedItems'] ?? false)
                @php($products = ShoppingCart::getCrossSellAndSuggestedProducts($data['amount_of_products'] ?? 4))
            @else
                @php($products = Products::getAllV2($data['amount_of_products'] ?? 4, orderBy: 'latest', enableFilters: false)['products'] ?? [])
            @endif
            @if(count($products))
                <div class="grid gap-8 md:grid-cols-2 lg:grid-cols-4 mt-4">
                    @foreach($products as $product)
                        <div data-aos="fade-right" data-aos-delay="{{ 300 * $loop->iteration }}">
                            <x-product.product :product="$product"
                                               :backgroundColor="$data['backgroundColor'] ?? 'bg-white'"/>
                        </div>
                    @endforeach
                </div>
            @endif
        </div>
    </x-container>
</div>
