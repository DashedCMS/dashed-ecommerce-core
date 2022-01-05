@if(env('APP_ENV') != 'local')
    @if(isset($order) && $order->isPaidFor() && ((Customsetting::get('google_analytics_id') || Customsetting::get('google_tagmanager_id'))))
        @php($productsPurchasedLoopCount = 0)
        <script>
            window.dataLayer = window.dataLayer || [];
            dataLayer.push({
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
                        'item_id': '{{$orderProduct->product->id}}',
                        'price': {{number_format($orderProduct->price, 2, '.', '')}},
                        'quantity': {{$orderProduct->quantity}},
                    }
                    @php($productsPurchasedLoopCount++)
                    @endforeach
                ]
            });
        </script>
    @endif
@endif
