@if(app()->isProduction() && isset($order) && $order && $order->isPaidFor() && ((Customsetting::get('google_analytics_id') || Customsetting::get('google_tagmanager_id'))))
    @php($productsPurchasedLoopCount = 0)
    <script>
        window.dataLayer = window.dataLayer || [];
        dataLayer.push({
            'event': 'purchase',
            'transactionId': '{{$order->invoice_id}}',
            'transactionAffiliation': '{{Customsetting::get('store_name')}}',
            'transactionTotal': {{ number_format($order->total, 2, '.', '') }},
            'transactionTax': {{ number_format($order->btw, 2, '.', '') }},
            'transactionShipping': {{ number_format(0, 2, '.', '') }},
            'transactionCurrency': 'EUR',
            'transactionCoupon': '{{ $order->discountCode ? $order->discountCode->code : '' }}',
            'transactionProducts': [
                @foreach($order->orderProducts as $orderProduct)
                @if($productsPurchasedLoopCount > 0)
                ,
                    @endif
                {
                    'sku': '{{$orderProduct->sku}}',
                    'name': '{{$orderProduct->name}}',
                    'item_id': '{{$orderProduct->product ? $orderProduct->product->id : ''}}',
                    'price': {{number_format($orderProduct->price, 2, '.', '')}},
                    'quantity': {{$orderProduct->quantity}},
                }
                @php($productsPurchasedLoopCount++)
                @endforeach
            ]
        });
        dataLayer.push({
            'event': 'enhancedConversion',
            'enhancedConversionData': {
                'email': '{{ $order->email }}',
                'address': {
                    'firstName': '{{ $order->first_name }}',
                    'lastName': '{{ $order->last_name }}',
                    'postalCode': '{{ $order->zip_code }}',
                    'country': '{{ $order->country }}',
                    'street': '{{ $order->street }} {{ $order->house_nr }}',
                    'city': '{{ $order->city }}',
                },
                'phone': '{{ $order->phone_number }}',
            }
        });
    </script>
@endif
