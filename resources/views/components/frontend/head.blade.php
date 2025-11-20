<x-dashed-ecommerce-core::frontend.orders.schema :order="$order ?? false" />

@php
    $tracking = $trackingSettings ?? [];

    $gmcId = $tracking['google_merchant_center_id'] ?? null;
    $gmcReviewBadgeEnabled = $tracking['enable_google_merchant_center_review_badge'] ?? false;
    $triggerTikTok = $tracking['trigger_tiktok_events'] ?? false;
@endphp

@if($gmcId)
    <script src="https://apis.google.com/js/platform.js" async defer></script>
@endif

<script>
    document.addEventListener('DOMContentLoaded', function () {
        const tracking = {
            tiktok: @json($triggerTikTok),
            gmcId: @json($gmcId),
            gmcReviewBadge: @json($gmcReviewBadgeEnabled),
        };

        if (tracking.tiktok && typeof ttq !== 'undefined') {
            ttq.identify({
                email: "{{ \Dashed\DashedEcommerceCore\Classes\TikTokHelper::getHashedEmail() }}",
                phone_number: "{{ \Dashed\DashedEcommerceCore\Classes\TikTokHelper::getHashedPhone() }}",
                external_id: "{{ \Dashed\DashedEcommerceCore\Classes\TikTokHelper::getExternalId() }}",
            });
        }

        if (tracking.gmcId && tracking.gmcReviewBadge && typeof window.gapi !== 'undefined') {
            const ratingBadgeContainer = document.createElement('div');
            document.body.appendChild(ratingBadgeContainer);

            window.gapi.load('ratingbadge', function () {
                window.gapi.ratingbadge.render(ratingBadgeContainer, {
                    merchant_id: tracking.gmcId,
                });
            });
        }
    });
</script>
