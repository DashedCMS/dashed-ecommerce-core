<x-dashed-ecommerce-core::frontend.orders.schema :order="$order ?? false"/>

@if(Customsetting::get('google_merchant_center_id'))
    <script src="https://apis.google.com/js/platform.js" async defer></script>
@endif

<script>
    @if(Customsetting::get('trigger_tiktok_events'))
    ttq.identify({
        email: "{{ \Dashed\DashedEcommerceCore\Classes\TikTokHelper::getHashedEmail() }}",
        phone_number: "{{ \Dashed\DashedEcommerceCore\Classes\TikTokHelper::getHashedPhone() }}",
        external_id: "{{ \Dashed\DashedEcommerceCore\Classes\TikTokHelper::getExternalId() }}"
    });
    @endif
    @if(Customsetting::get('google_merchant_center_id') && Customsetting::get('enable_google_merchant_center_review_badge'))
    var ratingBadgeContainer = document.createElement("div");
    document.body.appendChild(ratingBadgeContainer);
    window.gapi.load('ratingbadge', function () {
        window.gapi.ratingbadge.render(ratingBadgeContainer, {"merchant_id": {{ Customsetting::get('google_merchant_center_id') }}});
    });
    @endif
</script>
