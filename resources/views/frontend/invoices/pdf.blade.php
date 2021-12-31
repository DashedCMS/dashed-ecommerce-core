<!doctype html>
<html>
<head>
    <meta http-equiv="Content-Type" content="text/html; charset=utf-8"/>
    <title>{{Translation::get('invoice', 'invoice', 'Invoice')}} {{Customsetting::get('company_name')}}</title>
    <style>
        .invoice-box {
            max-width: 800px;
            margin: auto;
            padding: 30px;
            border: 1px solid #eee;
            box-shadow: 0 0 10px rgba(0, 0, 0, .15);
            font-size: 16px;
            line-height: 24px;
            font-family: 'Helvetica Neue', 'Helvetica', Helvetica, Arial, sans-serif;
            color: #555;
        }

        .invoice-box table {
            width: 100%;
            text-align: left;
        }

        .invoice-box table td {
            padding: 5px;
            vertical-align: top;
        }

        .invoice-box table tr td:nth-child(2) {
            text-align: right;
        }

        .invoice-box table tr.top table td {
            padding-bottom: 20px;
        }

        .invoice-box table tr.top table td.title {
            font-size: 45px;
            line-height: 45px;
            color: #333;
        }

        .invoice-box table tr.information table td {
            padding-bottom: 40px;
        }

        .invoice-box table tr.heading td {
            background: #eee;
            border-bottom: 1px solid #ddd;
            font-weight: bold;
            height: 10px;
        }

        .invoice-box table tr.details td {
            padding-bottom: 20px;
        }

        .invoice-box table tr.item td {
            border-bottom: 1px solid #eee;
        }

        .invoice-box table tr.item.last td {
            border-bottom: none;
        }

        .invoice-box table tr.total td:nth-child(2) {
            border-top: 2px solid #eee;
            font-weight: bold;
        }

        @media only screen and (max-width: 600px) {
            .invoice-box table tr.top table td {
                width: 100%;
                display: block;
                text-align: center;
            }

            .invoice-box table tr.information table td {
                width: 100%;
                display: block;
                text-align: center;
            }
        }

        .logo-parent {
            text-align: left !important;
        }

        .logo {
            width: 125px !important;
            height: auto !important;
        }

        .left {
        }

        .h1-top {
            color: #33B679;
            display: inline-block;

            text-transform: uppercase;
            font-size: 20px;

            position: absolute;
            left: 200px;
            width: 125%;
        }

        .contact-table {
            position: relative;
            float: right;
            bottom: 25px;
        }

        .border-product {
            border: 1px solid black;
            padding: 20px;
        }
    </style>
</head>
<body>
<div class="invoice-box">
    <table cellpadding="0" cellspacing="0">
        <tr class="top">
            <td colspan="2">
                <table>
                    <tr>
                        <td class='logo-parent'>
                            <?php
                            $store = CustomSetting::where('name', 'site_name')->thisSite()->first();
                            if ($store) {
                                $logo = $store->getFirstMedia('logo');
                                $favicon = $store->getFirstMedia('favicon');
                            } else {
                                $logo = '';
                                $favicon = '';
                            }
                            ?>
                            @if($logo)
                                <img src="{{Thumbnail::src($logo->getUrl())->heighten(100)->url(true)}}"
                                     class="logo">
                            @endif
                        </td>
                    </tr>
                    <tr>
                        <td class="">
                            @if($order->company_name)
                                {{$order->company_name}} <br>
                            @endif
                            {{ $order->invoiceName }}<br>
                            @if($order->btw_id)
                                {{$order->btw_id}} <br>
                            @endif
                            @if($order->invoice_street)
                                {{ $order->invoice_street }} {{ $order->invoice_house_nr }} <br>
                                {{ $order->invoice_zip_code }} {{ $order->invoice_city }} <br>
                                {{ $order->invoice_country }}
                            @else
                                {{ $order->street }} {{ $order->house_nr }}<br>
                                {{ $order->zip_code }} {{ $order->city }}<br>
                                {{ $order->country }}
                            @endif
                        </td>
                        <td class="">
                            {{Customsetting::get('company_name')}}<br>
                            {{Customsetting::get('company_street')}} {{Customsetting::get('company_street_number')}}<br>
                            {{Customsetting::get('company_postal_code')}} {{Customsetting::get('company_city')}}<br>
                            {{Customsetting::get('company_country')}}
                            @if(Customsetting::get('company_kvk'))
                                <br>
                                {{Translation::get('kvk', 'invoice', 'KVK')}}: {{Customsetting::get('company_kvk')}}
                            @endif
                            @if(Customsetting::get('company_btw'))
                                <br>
                                {{Translation::get('btw', 'invoice', 'BTW')}}: {{Customsetting::get('company_btw')}}
                            @endif
                        </td>
                    </tr>
                </table>
            </td>
        </tr>
        <tr>
            <td><b>{{Translation::get('invoice', 'invoice', 'Invoice')}}:</b><br><br>
            </td>
        </tr>
        <tr>
            <div class='left' style="width:100%; display:block;margin-left:5px;">
                <div style="width:35%; display:inline-block; position:relative;top:0;">
                    <p>
                        {{Translation::get('invoice-number', 'invoice', 'Invoice number')}}: <br>
                        {{Translation::get('invoice-date', 'invoice', 'Invoice date')}}: <br>
                        {{Translation::get('payment-method', 'invoice', 'Payment method') . ':'}}<br>
                        {{Translation::get('shipping-method', 'invoice', 'Shipping method') . ':'}}
                        @if($order->status == 'partially_paid')
                            <br>
                            {{Translation::get('amount-paid', 'invoice', 'Amount paid') . ':'}}<br>
                            {{Translation::get('amount-to-pay', 'invoice', 'Amount to pay') . ':'}}
                        @endif
                    </p>
                </div>
                <div style="width:31%;display:inline-block; position:relative;top:0;">
                    <p>
                        <span>{{$order->invoice_id}}</span><br>
                        <span>{{$order->created_at->format('d-m-Y')}}</span><br>
                        <span>{{$order->paymentMethod}}</span><br>
                        <span>{{$order->shippingMethod->name}}</span><br>
                        @if($order->status == 'partially_paid')
                            <span>{{CurrencyHelper::formatPrice($order->paidAmount)}}</span><br>
                            <span>{{CurrencyHelper::formatPrice($order->openAmount)}}</span><br>
                        @endif
                    </p>
                </div>
            </div>
        </tr>
    </table>
    <table>
        <tr>
            <td>
                <b>{{Translation::get('note', 'invoice', 'Note')}}:</b>
            </td>
        </tr>
    </table>
    <div>
        <table class="border-product">
            @foreach($order->orderProducts as $orderProduct)
                <tr>
                    <td>
                        {{$orderProduct->name}} {{$orderProduct->quantity}}x
                        @if($orderProduct->product_extras)
                            @foreach($orderProduct->product_extras as $option)
                                <br>
                                <small>{{$option['name']}}: {{$option['value']}}</small>
                            @endforeach
                        @endif
                    </td>
                    <td>
                        {{CurrencyHelper::formatPrice($orderProduct->price, 'EUR', true)}}
                    </td>
                </tr>
            @endforeach
            <tr>
                <td>
                    <hr>
                </td>
            </tr>
            <tr>
                <td>
                    {{Translation::get('subtotal', 'invoice', 'Subtotal')}}
                </td>
                <td>
                    {{CurrencyHelper::formatPrice($order->subtotal, 'EUR', true)}}
                </td>
            </tr>
            @if(!$order->shippingMethod->shippingZone->hide_vat_on_invoice)
                <tr>
                    <td>
                        {{Translation::get('btw', 'invoice', 'BTW')}}
                    </td>
                    <td>
                        {{CurrencyHelper::formatPrice($order->btw, 'EUR', true)}}
                    </td>
                </tr>
            @endif
            {{--            <tr>--}}
            {{--                <td>--}}
            {{--                    {{Translation::get('shipping-costs', 'invoice', 'Shipping costs')}}:--}}
            {{--                </td>--}}
            {{--                <td>--}}
            {{--                    @if($order->shipping_costs != 0.00)--}}
            {{--                        {{CurrencyHelper::formatPrice($order->shipping_costs, 'EUR', true)}} @else {{Translation::get('free-shipping', 'invoice', 'Free')}} @endif--}}
            {{--                </td>--}}
            {{--            </tr>--}}
            {{--            @if($order->payment_costs != 0.00)--}}
            {{--                <tr>--}}
            {{--                    <td>--}}
            {{--                        {{Translation::get('payment-costs', 'invoice', 'Betalingsmethode kosten')}}:--}}
            {{--                    </td>--}}
            {{--                    <td>--}}
            {{--                        {{CurrencyHelper::formatPrice($order->payment_costs, 'EUR', true)}}--}}
            {{--                    </td>--}}
            {{--                </tr>--}}
            {{--            @endif--}}
            @if($order->discount != 0.00)
                <tr>
                    <td>
                        {{Translation::get('discount', 'invoice', 'Discount')}}:
                    </td>
                    <td>
                        {{CurrencyHelper::formatPrice($order->discount, 'EUR', true)}}
                    </td>
                </tr>
            @endif
            <tr>
                <td>
                    {{Translation::get('total', 'invoice', 'Total')}}:
                </td>
                <td>
                    {{CurrencyHelper::formatPrice($order->total, 'EUR', true)}}
                </td>
            </tr>
        </table>
    </div>
        <div style="height:50px">
        </div>
    <div>
        <hr>
        <div style="width:30%;display:inline-block;">
            {{Customsetting::get('site_name')}}
        </div>
        <div
            style="float:right;display:inline-block;"><a
                href="mailto:{{Customsetting::get('store_to_email')}}">{{Customsetting::get('store_to_email')}}</a>
        </div>
    </div>
</div>
</body>
</html>
