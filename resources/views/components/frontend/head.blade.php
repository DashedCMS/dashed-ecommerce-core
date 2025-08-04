<x-dashed-ecommerce-core::frontend.orders.schema :order="$order ?? false"/>

@if(env('APP_ENV') != 'local')
    <script>
        @if(Customsetting::get('trigger_tiktok_events'))
        async function sha256(input) {
            const encoder = new TextEncoder();
            const data = encoder.encode(input);
            const hashBuffer = await crypto.subtle.digest('SHA-256', data);
            return Array.from(new Uint8Array(hashBuffer))
                .map(b => b.toString(16).padStart(2, '0'))
                .join('');
        }

        @if(auth()->check())
        (async () => {
            const email = "{{ auth()->user()->email }}";
            const phone = "{{ auth()->user()->lastOrderFromAllOrders?->phone_number ?? '' }}";
            const externalId = "{{ auth()->user()->id }}";

            const hashedEmail = email ? await sha256(email.trim().toLowerCase()) : null;
            const hashedPhone = phone ? await sha256(phone.trim()) : null;
            const hashedId = externalId ? await sha256(externalId.toString()) : null;

            ttq.identify({
                email: hashedEmail,
                phone_number: hashedPhone,
                external_id: hashedId
            });
        })();
        @endif
        @endif
    </script>
@endif
