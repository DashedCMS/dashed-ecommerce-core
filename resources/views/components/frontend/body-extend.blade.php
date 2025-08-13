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
@if(Customsetting::get('trigger_tiktok_events'))
    @php($shoppingCartTotal = \Dashed\DashedEcommerceCore\Classes\ShoppingCart::total(false))
@endif

<script>
    document.addEventListener('livewire:init', () => {
        Livewire.on('productAddedToCart', (event) => {
            @if(Customsetting::get('google_tagmanager_id'))
            dataLayer.push({
                'event': 'add_to_cart',
                'ecommerce': {
                    'currency': 'EUR',
                    'cartTotal': event[0].cartTotal,
                    'items': {
                        'products': [{
                            'name': event[0].productName,
                            'id': event[0].product.id,
                            'price': event[0].price,
                            'quantity': event[0].quantity,
                            'item_category': event[0].category,
                        }]
                    }
                }
            });
            @endif
            @if(Customsetting::get('trigger_tiktok_events'))
            ttq.track('AddToCart', event[0].tiktokItems);
            @endif
            @if(Customsetting::get('facebook_pixel_conversion_id') || Customsetting::get('facebook_pixel_site_id') || Customsetting::get('trigger_facebook_events'))
            fbq('track', 'AddToCart');
            @endif
        });

        Livewire.on('productRemovedFromCart', (event) => {
            @if(Customsetting::get('google_tagmanager_id'))
            dataLayer.push({
                'event': 'remove_from_cart',
                'ecommerce': {
                    'currency': 'EUR',
                    'cartTotal': event[0].cartTotal,
                    'items': {
                        'products': [{
                            'name': event[0].productName,
                            'id': event[0].product.id,
                            'price': event[0].price,
                            'item_category': event[0].category,
                        }]
                    }
                }
            });
            @endif
        });

        Livewire.on('checkoutInitiated', (event) => {
            setTimeout(function () {
                @if(Customsetting::get('facebook_pixel_conversion_id') || Customsetting::get('facebook_pixel_site_id') || Customsetting::get('trigger_facebook_events'))
                fbq('track', 'InitiateCheckout');
                @endif
                @if(Customsetting::get('google_tagmanager_id'))
                dataLayer.push({
                    'event': 'begin_checkout',
                    'ecommerce': {
                        'currency': 'EUR',
                        'value': event[0].cartTotal,
                        'items': event[0].items
                    }
                });
                @endif
                @if(Customsetting::get('trigger_tiktok_events'))
                ttq.track('InitiateCheckout', event[0].tiktokItems);
                @endif
            }, 1000);
        });

        Livewire.on('cartInitiated', (event) => {
            @if(Customsetting::get('google_tagmanager_id'))
            dataLayer.push({
                'event': 'view_cart',
                'ecommerce': {
                    'currency': 'EUR',
                    'value': event[0].cartTotal,
                    'items': event[0].items
                }
            });
            @endif
        });

        Livewire.on('viewProduct', (event) => {
            setTimeout(function () {
                @if(Customsetting::get('facebook_pixel_conversion_id') || Customsetting::get('facebook_pixel_site_id') || Customsetting::get('trigger_facebook_events'))
                fbq('track', 'ViewContent');
                @endif
                @if(Customsetting::get('google_tagmanager_id'))
                dataLayer.push({
                    'event': 'view_item',
                    'ecommerce': {
                        'currency': 'EUR',
                        'value': event[0].cartTotal,
                        'items': {
                            'products': [{
                                'name': event[0].productName,
                                'id': event[0].product.id,
                                'price': event[0].price,
                                'item_category': event[0].category,
                            }]
                        }
                    }
                });
                @endif
                @if(Customsetting::get('trigger_tiktok_events'))
                ttq.track('ViewContent', event[0].tiktokItems);
                @endif
            }, 1000);
        });

        Livewire.on('checkoutSubmitted', (event) => {
            @if(Customsetting::get('facebook_pixel_conversion_id') || Customsetting::get('facebook_pixel_site_id') || Customsetting::get('trigger_facebook_events'))
            fbq('track', 'AddPaymentInfo');
            @endif
            @if(Customsetting::get('trigger_tiktok_events'))
            ttq.track('PlaceAnOrder', event[0].tiktokItems);
            @endif
        });

        Livewire.on('orderPaid', (event) => {
            setTimeout(function () {
                @if(Customsetting::get('facebook_pixel_conversion_id') || Customsetting::get('facebook_pixel_site_id') || Customsetting::get('trigger_facebook_events'))
                fbq('track', 'Purchase', {currency: "EUR", value: event.total});
                @endif
                @if(Customsetting::get('google_tagmanager_id'))
                dataLayer.push({
                    'event': 'purchase',
                    'ecommerce': {
                        'currency': 'EUR',
                        'value': event[0].total,
                        'transaction_id': event[0].orderId,
                        'items': event[0].items,
                        'coupon': event[0].discountCode,
                        'tax': event[0].tax,
                        'new_customer': event[0].newCustomer,
                        'email': event[0].email,
                        'phone_number': event[0].phoneNumber
                    }
                });
                @endif
                @if(Customsetting::get('trigger_tiktok_events'))
                ttq.track('Purchase', event[0].tiktokItems);
                @endif
            }, 1000);
            @if(Customsetting::get('google_merchant_center_id') && Customsetting::get('enable_google_merchant_center_review_survey'))
            window.gapi.load('surveyoptin', function () {
                window.gapi.surveyoptin.render(
                    {
                        "merchant_id": {{ Customsetting::get('google_merchant_center_id') }},
                        "order_id": event[0].orderId,
                        "email": event[0].email,
                        "delivery_country": event[0].countryCode,
                        "estimated_delivery_date": event[0].estimatedDeliveryDate,
                        "products": event[0].items.map((item) => {
                            return {
                                "gtin": item.ean,
                            };
                        }),
                    });
            });
            @endif
        });
    });
</script>
