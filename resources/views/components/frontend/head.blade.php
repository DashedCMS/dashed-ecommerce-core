<x-dashed-ecommerce-core::frontend.orders.schema :order="$order ?? false"/>

@if(env('APP_ENV') != 'local')
    <script>
        @if(Customsetting::get('trigger_tiktok_events'))
        ttq.identify({
            email: "{{ \Dashed\DashedEcommerceCore\Classes\TikTokHelper::getHashedEmail() }}",
            phone_number: "{{ \Dashed\DashedEcommerceCore\Classes\TikTokHelper::getHashedPhone() }}",
            external_id: "{{ \Dashed\DashedEcommerceCore\Classes\TikTokHelper::getExternalId() }}"
        });
        @endif
    </script>
@endif
