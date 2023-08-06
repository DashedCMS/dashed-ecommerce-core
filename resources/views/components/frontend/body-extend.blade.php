@livewireScripts
@if(isset($product))
    <x-dashed-ecommerce-core::frontend.products.schema :product="$product"></x-dashed-ecommerce-core::frontend.products.schema>
@endif
@if(isset($products))
    @foreach($products as $product)
        <x-dashed-ecommerce-core::frontend.products.schema :product="$product"></x-dashed-ecommerce-core::frontend.products.schema>
    @endforeach
@endif

@if(env('APP_ENV') != 'local')
    @if(isset($order) && $order->isPaidFor() && (Customsetting::get('facebook_pixel_conversion_id') || Customsetting::get('facebook_pixel_store_id')))
        <script>
            fbq('track', 'Purchase', {currency: "EUR", value: {{number_format($order->total, 2, '.', '')}}});
        </script>
    @endif
@endif
