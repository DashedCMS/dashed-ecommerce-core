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

    // ViewContent via de Conversions API: de browser meldt de productweergave aan dit
    // adres. Een relatief pad zonder token of ID, zodat het in gecachete HTML mag staan.
    $metaViewContentUrl = null;
    if ($facebookEnabled) {
        try {
            $metaCapiSettings = \Dashed\DashedEcommerceCore\Services\Meta\MetaCapiSettings::cached(null);
            if ($metaCapiSettings->enabled() && $metaCapiSettings->readyFor(\Dashed\DashedEcommerceCore\Services\Meta\MetaCapiSettings::EVENT_VIEW_CONTENT)) {
                $metaViewContentUrl = route('dashed.frontend.meta.view-content', [], false);
            }
        } catch (\Throwable $e) {
            report($e);
        }
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
            metaViewContentUrl: @json($metaViewContentUrl),
        };

        // ViewContent voor de Conversions API. Het event-ID ontstaat hier in de browser en
        // niet op de server: de productpagina kan uit de paginacache komen, en een ID in die
        // HTML zou voor elke bezoeker hetzelfde zijn. Hetzelfde ID gaat naar de pixel en naar
        // de server, zodat Meta de twee ontdubbelt.
        const metaViewContentIds = {};
        const metaViewContentId = (productId) => {
            if (!tracking.metaViewContentUrl || !productId) {
                return null;
            }
            if (!metaViewContentIds[productId]) {
                const random = (window.crypto && typeof window.crypto.randomUUID === 'function')
                    ? window.crypto.randomUUID()
                    : (Date.now().toString(36) + Math.random().toString(36).slice(2) + Math.random().toString(36).slice(2)).slice(0, 40).padEnd(24, '0');
                metaViewContentIds[productId] = 'vc_' + random;
                reportMetaViewContent(productId, metaViewContentIds[productId]);
            }
            return metaViewContentIds[productId];
        };
        const reportMetaViewContent = (productId, eventId) => {
            let sent = false;
            const send = (leaving) => {
                if (sent) {
                    return;
                }
                sent = true;
                try {
                    const body = new URLSearchParams({product_id: productId, event_id: eventId});
                    if (leaving && navigator.sendBeacon) {
                        navigator.sendBeacon(tracking.metaViewContentUrl, body);
                        return;
                    }
                    fetch(tracking.metaViewContentUrl, {method: 'POST', body: body, credentials: 'same-origin', keepalive: true}).catch(() => {});
                } catch (e) {
                }
            };
            // De pixel laadt uitgesteld en zet dan pas zijn _fbp-cookie. Even wachten geeft
            // de server dezelfde bezoeker-ID mee; wie eerder vertrekt, wordt bij het
            // verlaten van de pagina gemeld.
            let attempts = 0;
            const wait = () => {
                if (sent) {
                    return;
                }
                if (document.cookie.indexOf('_fbp=') !== -1 || ++attempts > 10) {
                    send(false);
                    return;
                }
                setTimeout(wait, 500);
            };
            setTimeout(wait, 1000);
            window.addEventListener('pagehide', () => send(true), {once: true});
        };

        Livewire.on('productAddedToCart', (event) => {
            const payload = event[0];
            if (!payload || !payload.product) {
                return;
            }

            if (tracking.gtm && typeof dataLayer !== 'undefined') {
                const unitPrice = parseFloat(payload.price) || 0;
                const qty = parseInt(payload.quantity) || 1;
                dataLayer.push({
                    event: 'add_to_cart',
                    ecommerce: {
                        currency: 'EUR',
                        value: parseFloat((unitPrice * qty).toFixed(2)),
                        items: [{
                            item_id: payload.product.id,
                            item_name: payload.productName,
                            index: 0,
                            item_category: payload.category,
                            price: unitPrice,
                            quantity: qty,
                        }],
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
                // Hetzelfde event-ID en dezelfde waarde als het server-event (Conversions API),
                // zodat Meta de twee ontdubbelt.
                const atcOptions = payload.metaEventId ? {eventID: payload.metaEventId} : undefined;
                fbq('track', 'AddToCart', {
                    content_type: 'product',
                    content_ids: productId ? [productId] : [],
                    content_name: payload.productName,
                    content_category: payload.category,
                    contents: productId ? [{id: productId, quantity: qty, item_price: unitPrice}] : [],
                    value: payload.metaValue !== undefined ? (parseFloat(payload.metaValue) || 0) : unitPrice * qty,
                    currency: 'EUR',
                }, atcOptions);
            }
        });

        Livewire.on('productRemovedFromCart', (event) => {
            const payload = event[0];

            if (tracking.gtm && typeof dataLayer !== 'undefined') {
                const unitPrice = parseFloat(payload.price) || 0;
                const qty = parseInt(payload.quantity) || 1;
                dataLayer.push({
                    event: 'remove_from_cart',
                    ecommerce: {
                        currency: 'EUR',
                        value: parseFloat((unitPrice * qty).toFixed(2)),
                        items: [{
                            item_id: payload.product.id,
                            item_name: payload.productName,
                            index: 0,
                            item_category: payload.category,
                            price: unitPrice,
                            quantity: qty,
                        }],
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
                    const checkoutOptions = payload.metaEventId ? {eventID: payload.metaEventId} : undefined;
                    fbq('track', 'InitiateCheckout', {
                        content_type: 'product',
                        content_ids: fbContents.map(i => i.id),
                        contents: fbContents,
                        num_items: fbContents.reduce((n, i) => n + i.quantity, 0),
                        value: parseFloat(payload.metaValue ?? payload.cartTotal) || 0,
                        currency: 'EUR',
                    }, checkoutOptions);
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

            // Buiten de vertraging: de melding aan de server start meteen, ook als de
            // bezoeker binnen een seconde weer weg is.
            // Een fout hier mag de pixel, GTM en TikTok hieronder niet tegenhouden.
            let viewContentEventId = null;
            try {
                viewContentEventId = tracking.facebook
                    ? metaViewContentId(payload.product && payload.product.id ? String(payload.product.id) : null)
                    : null;
            } catch (e) {
            }

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
                        value: payload.metaValue !== undefined ? (parseFloat(payload.metaValue) || 0) : unitPrice,
                        currency: 'EUR',
                    }, viewContentEventId ? {eventID: viewContentEventId} : undefined);
                }

                if (tracking.gtm && typeof dataLayer !== 'undefined') {
                    const unitPrice = parseFloat(payload.price) || 0;
                    const qty = parseInt(payload.quantity) || 1;
                    dataLayer.push({
                        event: 'view_item',
                        ecommerce: {
                            currency: 'EUR',
                            value: unitPrice,
                            items: [{
                                item_id: payload.product.id,
                                item_name: payload.productName,
                                index: 0,
                                item_category: payload.category,
                                price: unitPrice,
                                quantity: qty,
                            }],
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
                    // Dezelfde inhoud, waarde en event-ID als het server-side event
                    // (Conversions API), zodat Meta de twee ontdubbelt.
                    const fbContents = Array.isArray(payload.metaContents)
                        ? payload.metaContents
                        : items.map(i => ({
                            id: String(i.item_id ?? i.id ?? ''),
                            quantity: parseInt(i.quantity) || 1,
                            item_price: parseFloat(i.price) || 0,
                        })).filter(i => i.id);
                    const fbOptions = payload.metaEventId ? {eventID: payload.metaEventId} : undefined;
                    fbq('track', 'Purchase', {
                        content_type: 'product',
                        content_ids: fbContents.map(i => i.id),
                        contents: fbContents,
                        num_items: fbContents.reduce((n, i) => n + i.quantity, 0),
                        value: parseFloat(payload.metaValue ?? payload.total) || 0,
                        currency: 'EUR',
                    }, fbOptions);
                }

                if (tracking.gtm && typeof dataLayer !== 'undefined') {
                    dataLayer.push({
                        event: 'purchase',
                        user_data: payload.userData,
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
