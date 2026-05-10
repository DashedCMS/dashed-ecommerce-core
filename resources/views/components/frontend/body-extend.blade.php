@if(isset($product))
    <x-dashed-ecommerce-core::frontend.products.schema :product="$product" />
@endif

@if(isset($products))
    @foreach($products as $product)
        <x-dashed-ecommerce-core::frontend.products.schema :product="$product" />
    @endforeach
@endif

@if(isset($productCategory) && $productCategory)
    <x-dashed-ecommerce-core::frontend.product-categories.schema :productCategory="$productCategory" />
@endif

@if(isset($productCategories))
    @foreach($productCategories as $productCategory)
        <x-dashed-ecommerce-core::frontend.product-categories.schema :productCategory="$productCategory" />
    @endforeach
@endif

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
    (function () {
        const register = () => {
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
                const productId = payload.product && payload.product.id ? String(payload.product.id) : null;
                const unitPrice = parseFloat(payload.price) || 0;
                const qty = parseInt(payload.quantity) || 1;
                fbq('track', 'AddToCart', {
                    content_type: 'product',
                    content_ids: productId ? [productId] : [],
                    content_name: payload.productName,
                    content_category: payload.category,
                    contents: productId ? [{id: productId, quantity: qty, item_price: unitPrice}] : [],
                    value: unitPrice * qty,
                    currency: 'EUR',
                });
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
                    const items = Array.isArray(payload.items) ? payload.items : [];
                    const fbContents = items.map(i => ({
                        id: String(i.item_id ?? i.id ?? ''),
                        quantity: parseInt(i.quantity) || 1,
                        item_price: parseFloat(i.price) || 0,
                    })).filter(i => i.id);
                    fbq('track', 'InitiateCheckout', {
                        content_type: 'product',
                        content_ids: fbContents.map(i => i.id),
                        contents: fbContents,
                        num_items: fbContents.reduce((n, i) => n + i.quantity, 0),
                        value: parseFloat(payload.cartTotal) || 0,
                        currency: 'EUR',
                    });
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
                    const productId = payload.product && payload.product.id ? String(payload.product.id) : null;
                    const unitPrice = parseFloat(payload.price) || 0;
                    fbq('track', 'ViewContent', {
                        content_type: 'product',
                        content_ids: productId ? [productId] : [],
                        content_name: payload.productName,
                        content_category: payload.category,
                        contents: productId ? [{id: productId, quantity: 1, item_price: unitPrice}] : [],
                        value: unitPrice,
                        currency: 'EUR',
                    });
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
                const items = payload && Array.isArray(payload.items) ? payload.items : [];
                const fbContents = items.map(i => ({
                    id: String(i.item_id ?? i.id ?? ''),
                    quantity: parseInt(i.quantity) || 1,
                    item_price: parseFloat(i.price) || 0,
                })).filter(i => i.id);
                fbq('track', 'AddPaymentInfo', {
                    content_type: 'product',
                    content_ids: fbContents.map(i => i.id),
                    contents: fbContents,
                    value: payload && payload.cartTotal ? parseFloat(payload.cartTotal) : 0,
                    currency: 'EUR',
                });
            }

            if (tracking.tiktok && typeof ttq !== 'undefined') {
                ttq.track('PlaceAnOrder', payload.tiktokItems);
            }
        });

        Livewire.on('orderPaid', (event) => {
            const payload = event[0];

            setTimeout(() => {
                if (tracking.facebook && typeof fbq !== 'undefined') {
                    const items = Array.isArray(payload.items) ? payload.items : [];
                    const fbContents = items.map(i => ({
                        id: String(i.item_id ?? i.id ?? ''),
                        quantity: parseInt(i.quantity) || 1,
                        item_price: parseFloat(i.price) || 0,
                    })).filter(i => i.id);
                    fbq('track', 'Purchase', {
                        content_type: 'product',
                        content_ids: fbContents.map(i => i.id),
                        contents: fbContents,
                        num_items: fbContents.reduce((n, i) => n + i.quantity, 0),
                        value: parseFloat(payload.total) || 0,
                        currency: 'EUR',
                    });
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
        };

        if (window.Livewire) {
            register();
        } else {
            document.addEventListener('livewire:init', register);
        }
    })();
</script>
