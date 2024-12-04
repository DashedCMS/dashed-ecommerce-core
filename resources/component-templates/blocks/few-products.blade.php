<div class="{{ $data['backgroundColor'] ?? 'bg-white' }} py-24 sm:py-32">
    <x-container>
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
            @php($products = ($products ?? false) ? $products : (\Dashed\DashedEcommerceCore\Classes\Products::getAllV2('4', orderBy: 'latest', enableFilters: false)['products'] ?? []))
            @if(count($products))
                <div class="grid gap-8 items-start md:grid-cols-2 lg:grid-cols-4 mt-4">
                    @foreach($products as $product)
                        <div data-aos="fade-right" data-aos-delay="{{ 300 * $loop->iteration }}">
                            <x-product.product :product="$product" :backgroundColor="$data['backgroundColor'] ?? 'bg-white'" />
                        </div>
                    @endforeach
                </div>
            @endif
        </div>
    </x-container>
</div>
