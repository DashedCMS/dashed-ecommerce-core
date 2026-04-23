@if(isset($product))
    <x-dashed-ecommerce-core::frontend.products.schema :product="$product" />
@endif

@if(isset($products))
    @foreach($products as $product)
        <x-dashed-ecommerce-core::frontend.products.schema :product="$product" />
    @endforeach
@endif

{{-- Product category schema (CollectionPage) --}}
@if(isset($productCategory) && $productCategory)
    @php
        $__pcName = is_array($productCategory->name) ? reset($productCategory->name) : (string) $productCategory->name;
        $__pcDescription = (string) ($productCategory->description ?? $productCategory->summary ?? '');
    @endphp
    <script type="application/ld+json">{!! json_encode(array_filter([
        '@context' => 'https://schema.org',
        '@type' => 'CollectionPage',
        'name' => $__pcName,
        'url' => $productCategory->getUrl(),
        'description' => $__pcDescription !== '' ? $__pcDescription : null,
    ]), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) !!}</script>
@endif

{{-- BreadcrumbList schema (product) --}}
@if(isset($product) && $product && method_exists($product, 'breadcrumbs'))
    @php $__crumbs = $product->breadcrumbs(); @endphp
    @if(! empty($__crumbs))
        <script type="application/ld+json">{!! json_encode([
            '@context' => 'https://schema.org',
            '@type' => 'BreadcrumbList',
            'itemListElement' => collect($__crumbs)->values()->map(fn ($c, $i) => [
                '@type' => 'ListItem',
                'position' => $i + 1,
                'name' => (string) ($c['name'] ?? ''),
                'item' => (string) ($c['url'] ?? ''),
            ])->toArray(),
        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) !!}</script>
    @endif
@endif

{{-- BreadcrumbList schema (productCategory) via parent chain --}}
@if(isset($productCategory) && $productCategory)
    @php
        $__pcCrumbs = [];
        $__pcCursor = $productCategory;
        $__pcGuard = 0;
        while ($__pcCursor && $__pcGuard < 20) {
            $__pcCrumbs[] = [
                'name' => is_array($__pcCursor->name) ? reset($__pcCursor->name) : (string) $__pcCursor->name,
                'url' => $__pcCursor->getUrl(),
            ];
            $__pcCursor = $__pcCursor->parent_id
                ? \Dashed\DashedEcommerceCore\Models\ProductCategory::find($__pcCursor->parent_id)
                : null;
            $__pcGuard++;
        }
        $__pcCrumbs = array_reverse($__pcCrumbs);
    @endphp
    @if(! empty($__pcCrumbs))
        <script type="application/ld+json">{!! json_encode([
            '@context' => 'https://schema.org',
            '@type' => 'BreadcrumbList',
            'itemListElement' => collect($__pcCrumbs)->values()->map(fn ($c, $i) => [
                '@type' => 'ListItem',
                'position' => $i + 1,
                'name' => $c['name'],
                'item' => $c['url'],
            ])->toArray(),
        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) !!}</script>
    @endif
@endif

@php
    // Tracking settings uit middleware-cache
    $tracking = $trackingSettings ?? [];

    $triggerTikTok = $tracking['trigger_tiktok_events'] ?? false;
    $googleTagmanagerId = $tracking['google_tagmanager_id'] ?? null;

    $facebookEnabled = !empty($tracking['facebook_pixel_conversion_id'] ?? null)
        || !empty($tracking['facebook_pixel_site_id'] ?? null)
        || !empty($tracking['trigger_facebook_events'] ?? false);

    $googleTagmanagerEnabled = !empty($googleTagmanagerId);

    $googleMerchantCenterId = $tracking['google_merchant_center_id'] ?? null;
    $enableGmcReviewSurvey = $tracking['enable_google_merchant_center_review_survey'] ?? false;
    $googleReviewSurveyEnabled = !empty($googleMerchantCenterId) && $enableGmcReviewSurvey;

    if ($triggerTikTok) {
        $shoppingCartTotal = cartHelper()->getTotal();
    }
@endphp

<script>
    document.addEventListener('livewire:init', () => {
        const tracking = {
            gtm: @json($googleTagmanagerEnabled),
            tiktok: @json($triggerTikTok),
            facebook: @json($facebookEnabled),
            gmcReviewSurvey: @json($googleReviewSurveyEnabled),
            gmcMerchantId: @json($googleMerchantCenterId),
        };

        Livewire.on('productAddedToCart', (event) => {
            const payload = event[0];

            if (tracking.gtm && typeof dataLayer !== 'undefined') {
                dataLayer.push({
                    event: 'add_to_cart',
                    ecommerce: {
                        currency: 'EUR',
                        cartTotal: payload.cartTotal,
                        items: {
                            products: [{
                                name: payload.productName,
                                id: payload.product.id,
                                price: payload.price,
                                quantity: payload.quantity,
                                item_category: payload.category,
                            }],
                        },
                    },
                });
            }

            if (tracking.tiktok && typeof ttq !== 'undefined') {
                ttq.track('AddToCart', payload.tiktokItems);
            }

            if (tracking.facebook && typeof fbq !== 'undefined') {
                fbq('track', 'AddToCart');
            }
        });

        Livewire.on('productRemovedFromCart', (event) => {
            const payload = event[0];

            if (tracking.gtm && typeof dataLayer !== 'undefined') {
                dataLayer.push({
                    event: 'remove_from_cart',
                    ecommerce: {
                        currency: 'EUR',
                        cartTotal: payload.cartTotal,
                        items: {
                            products: [{
                                name: payload.productName,
                                id: payload.product.id,
                                price: payload.price,
                                item_category: payload.category,
                            }],
                        },
                    },
                });
            }
        });

        Livewire.on('checkoutInitiated', (event) => {
            const payload = event[0];

            setTimeout(() => {
                if (tracking.facebook && typeof fbq !== 'undefined') {
                    fbq('track', 'InitiateCheckout');
                }

                if (tracking.gtm && typeof dataLayer !== 'undefined') {
                    dataLayer.push({
                        event: 'begin_checkout',
                        ecommerce: {
                            currency: 'EUR',
                            value: payload.cartTotal,
                            items: payload.items,
                        },
                    });
                }

                if (tracking.tiktok && typeof ttq !== 'undefined') {
                    ttq.track('InitiateCheckout', payload.tiktokItems);
                }
            }, 1000);
        });

        Livewire.on('cartInitiated', (event) => {
            const payload = event[0];

            if (tracking.gtm && typeof dataLayer !== 'undefined') {
                dataLayer.push({
                    event: 'view_cart',
                    ecommerce: {
                        currency: 'EUR',
                        value: payload.cartTotal,
                        items: payload.items,
                    },
                });
            }
        });

        Livewire.on('viewProduct', (event) => {
            const payload = event[0];

            setTimeout(() => {
                if (tracking.facebook && typeof fbq !== 'undefined') {
                    fbq('track', 'ViewContent');
                }

                if (tracking.gtm && typeof dataLayer !== 'undefined') {
                    dataLayer.push({
                        event: 'view_item',
                        ecommerce: {
                            currency: 'EUR',
                            value: payload.cartTotal,
                            items: {
                                products: [{
                                    name: payload.productName,
                                    id: payload.product.id,
                                    price: payload.price,
                                    item_category: payload.category,
                                }],
                            },
                        },
                    });
                }

                if (tracking.tiktok && typeof ttq !== 'undefined') {
                    ttq.track('ViewContent', payload.tiktokItems);
                }
            }, 1000);
        });

        Livewire.on('checkoutSubmitted', (event) => {
            const payload = event[0];

            if (tracking.facebook && typeof fbq !== 'undefined') {
                fbq('track', 'AddPaymentInfo');
            }

            if (tracking.tiktok && typeof ttq !== 'undefined') {
                ttq.track('PlaceAnOrder', payload.tiktokItems);
            }
        });

        Livewire.on('orderPaid', (event) => {
            const payload = event[0];

            setTimeout(() => {
                if (tracking.facebook && typeof fbq !== 'undefined') {
                    fbq('track', 'Purchase', {currency: 'EUR', value: payload.total});
                }

                if (tracking.gtm && typeof dataLayer !== 'undefined') {
                    dataLayer.push({
                        event: 'purchase',
                        ecommerce: {
                            currency: 'EUR',
                            value: payload.total,
                            transaction_id: payload.orderId,
                            items: payload.items,
                            coupon: payload.discountCode,
                            tax: payload.tax,
                            new_customer: payload.newCustomer,
                            email: payload.email,
                            phone_number: payload.phoneNumber,
                        },
                    });
                }

                if (tracking.tiktok && typeof ttq !== 'undefined') {
                    ttq.track('Purchase', payload.tiktokItems);
                }
            }, 1000);

            if (tracking.gmcReviewSurvey && typeof window.gapi !== 'undefined') {
                window.gapi.load('surveyoptin', function () {
                    window.gapi.surveyoptin.render({
                        merchant_id: tracking.gmcMerchantId,
                        order_id: payload.orderId,
                        email: payload.email,
                        delivery_country: payload.countryCode,
                        estimated_delivery_date: payload.estimatedDeliveryDate,
                        products: payload.items.map((item) => ({
                            gtin: item.ean,
                        })),
                    });
                });
            }
        });
    });
</script>
