@if(env('APP_ENV') != 'local')
    @if(isset($product))
        <x-dashed-ecommerce-core::frontend.products.schema
            :product="$product"></x-dashed-ecommerce-core::frontend.products.schema>
    @endif
    @if(isset($products))
        @foreach($products as $product)
            <x-dashed-ecommerce-core::frontend.products.schema
                :product="$product"></x-dashed-ecommerce-core::frontend.products.schema>
        @endforeach
    @endif

    @if(isset($order) && $order->isPaidFor())
        @if(Customsetting::get('facebook_pixel_conversion_id') || Customsetting::get('facebook_pixel_store_id'))
            <script>
                fbq('track', 'Purchase', {currency: "EUR", value: {{number_format($order->total, 2, '.', '')}}});
            </script>
        @endif
    @endif

    <script>
        document.addEventListener('livewire:init', () => {
            Livewire.on('productAddedToCart', (event) => {
                dataLayer.push({
                    'event': 'addToCart',
                    'ecommerce': {
                        'currencyCode': 'EUR',
                        'add': {
                            'products': [{
                                'name': event[0].productName,
                                'id': event[0].product.id,
                                'price': event[0].price,
                                'quantity': event[0].quantity,
                            }]
                        }
                    }
                });
            });
        });
    </script>
@endif
