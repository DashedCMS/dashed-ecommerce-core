<x-dashed-ecommerce-core::frontend.orders.schema :order="$order ?? false"/>

<script>
    @if(Customsetting::get('trigger_tiktok_events'))
    ttq.identify({
        email: "{{ \Dashed\DashedEcommerceCore\Classes\TikTokHelper::getHashedEmail() }}",
        phone_number: "{{ \Dashed\DashedEcommerceCore\Classes\TikTokHelper::getHashedPhone() }}",
        external_id: "{{ \Dashed\DashedEcommerceCore\Classes\TikTokHelper::getExternalId() }}"
    });
    @endif
</script>

@if(Customsetting::get('google_merchant_center_id'))
    <script src="https://apis.google.com/js/platform.js" async defer></script>
@endif
