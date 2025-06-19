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
                            @php($logo = Customsetting::get('site_logo', Sites::getActive(), ''))
                            @if($logo)
                                <img
                                    src="{{mediaHelper()->getSingleMedia($logo)->url ?? ''}}"
                                    class="logo">
                            @endif
                        </td>
                    </tr>
                    <tr>
                        <td class="">

                        </td>
                        <td class="">
                            @if(Customsetting::get('company_kvk'))
                                {{Translation::get('kvk', 'invoice', 'KVK')}}: {{Customsetting::get('company_kvk')}}
                                <br>
                            @endif
                            @if(Customsetting::get('company_btw'))
                                {{Translation::get('btw', 'invoice', 'BTW')}}: {{Customsetting::get('company_btw')}}
                                <br>
                            @endif
                            {{Customsetting::get('company_name')}}<br>
                            {{Customsetting::get('company_street')}} {{Customsetting::get('company_street_number')}}<br>
                            {{Customsetting::get('company_postal_code')}} {{Customsetting::get('company_city')}}<br>
                            {{Customsetting::get('company_country')}}
                        </td>
                    </tr>
                </table>
            </td>
        </tr>
        <tr>
            <td><b>{{Translation::get('invoice', 'invoice', 'Invoice')}}:</b><br><br>
            </td>
        </tr>
        <tr class="information" style="">
            <div class='left' style="width:100%; display:block;margin-left:5px;">

                <div style="width:25%; display:inline-block; position:relative;top:0;">
                    <p>
                        {{--                        {{Translation::get('invoice-number', 'invoice', 'Invoice number')}}: <br>--}}
                        {{Translation::get('invoice-date-range', 'invoice', 'Invoice date range')}}: <br>
                    </p>
                </div>
                <div style="width:31%;display:inline-block; position:relative;top:0;">
                    <p>
                        {{--                        <span style="position:absolute; right:0;">{{$order->invoice_id}}</span>--}}
                        {{--                        <br>--}}
                        <span style="position:absolute; right:0;">{{ $startDate->format('d-m-Y') }} - {{ $endDate->format('d-m-Y') }}</span><br>
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
            @foreach($productSales as $productSale)
                @if($productSale['quantity'] > 0)
                    <tr>
                        <td>
                            {{$productSale['name']}} {{$productSale['quantity']}}x
                        </td>
                        <td>
                            {{CurrencyHelper::formatPrice($productSale['totalPrice'], 'EUR', true)}}
                        </td>
                    </tr>
                @endif
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
                    {{CurrencyHelper::formatPrice($subTotal, 'EUR', true)}}
                </td>
            </tr>
            @foreach($vatPercentages as $vatPercentage => $vatAmount)
                <tr>
                    <td>
                        {{Translation::get('btw-percentage', 'invoice', 'BTW :percentage:%', 'text', [
                            'percentage' => $vatPercentage,
                        ]) . ':'}}
                    </td>
                    <td>
                        {{CurrencyHelper::formatPrice($vatAmount, 'EUR', true)}}
                    </td>
                </tr>
            @endforeach
            @if(count($vatPercentages) > 1)
                <tr>
                    <td>
                        {{Translation::get('btw', 'invoice', 'BTW')}}
                    </td>
                    <td>
                        {{CurrencyHelper::formatPrice($btw, 'EUR', true)}}
                    </td>
                </tr>
            @endif
            {{--            <tr>--}}
            {{--                <td>--}}
            {{--                    {{Translation::get('shipping-costs', 'invoice', 'Shipping costs')}}:--}}
            {{--                </td>--}}
            {{--                <td>--}}
            {{--                    @if($shippingCosts != 0.00)--}}
            {{--                        {{CurrencyHelper::formatPrice($shippingCosts, 'EUR', true)}} @else {{Translation::get('free-shipping', 'invoice', 'Free')}} @endif--}}
            {{--                </td>--}}
            {{--            </tr>--}}
            {{--            @if($paymentCosts != 0.00)--}}
            {{--                <tr>--}}
            {{--                    <td>--}}
            {{--                        {{Translation::get('payment-costs', 'invoice', 'Betalingsmethode kosten')}}:--}}
            {{--                    </td>--}}
            {{--                    <td>--}}
            {{--                        {{CurrencyHelper::formatPrice($paymentCosts, 'EUR', true)}}--}}
            {{--                    </td>--}}
            {{--                </tr>--}}
            {{--            @endif--}}
            @if($discount != 0.00)
                <tr>
                    <td>
                        {{Translation::get('discount', 'invoice', 'Discount')}}:
                    </td>
                    <td>
                        {{CurrencyHelper::formatPrice($discount, 'EUR', true)}}
                    </td>
                </tr>
            @endif
            <tr>
                <td>
                    {{Translation::get('total', 'invoice', 'Total')}}:
                </td>
                <td>
                    {{CurrencyHelper::formatPrice($total, 'EUR', true)}}
                </td>
            </tr>
        </table>
    </div>
    <div style="height:100px">
    </div>
    <div>
        <hr>
        <div style="width:30%;display:inline-block;">
            {{Customsetting::get('company_name')}}
        </div>
        <div
            style="float:right;display:inline-block;"><a
                href="mailto:{{Customsetting::get('site_to_email')}}">{{Customsetting::get('site_to_email')}}</a>
        </div>
    </div>
</div>
</body>
</html>
