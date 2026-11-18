<x-dashed-ecommerce-core::invoices.master :title="Translation::get('invoice-for', 'invoice', 'Factuur voor :siteName:', 'text', [
            'siteName' => Customsetting::get('site_name')
        ])">
    @unless($order->credit_for_order_id)
        @php($parcelType = \Dashed\DashedEcommerceCore\Classes\ParcelType::forOrder($order))
        <div style="border: 4px solid #000; padding: 10px 14px; margin: 0 0 16px; font-size: 24px; font-weight: bold; text-align: center; letter-spacing: 1px; color: #000;">
            VERZENDVORM: {{ $parcelType['label'] }}
        </div>
    @endunless

    @php($addOnNotice = \Dashed\DashedEcommerceCore\Classes\OrderAddOns\PackingSlip::notice($order))
    @if($addOnNotice)
        <div class="add-on-notice" style="border: 4px dashed #000; padding: 10px 14px; margin: 0 0 16px; font-size: 18px; font-weight: bold; text-align: center; color: #000;">
            {{ $addOnNotice }}
        </div>
    @endif

    <h1>{{ Translation::get('packing-slip', 'invoice', 'Pakbon') }}</h1>

    <table class="table-details">
        <tr>
            <td class="receiver">
                <h2>{{ Translation::get('customer-information', 'invoice', 'Uw gegevens') }}</h2>

                @if ($order->company_name)
                    <p>
                        <b>{{ $order->company_name }}</b>
                    </p>
                @endif

                @if($order->btw_id)
                    <p>{{ Translation::get('tax-id', 'invoice', 'BTW') }} {{ $order->btw_id }}</p>
                @endif

                @if ($order->name)
                    <p>
                        <b>{{ $order->name }}</b>
                    </p>
                @endif

                @if($order->invoice_street)
                    <p>{{ $order->invoice_street }} {{ $order->invoice_house_nr . ', ' . $order->invoice_zip_code }}</p>

                    <p>{{ $order->invoice_city . ', ' . $order->invoice_country }}</p>
                @else
                    <p>{{ $order->street }} {{ $order->house_nr . ', ' . $order->zip_code }}</p>

                    <p>{{ $order->city . ', ' . $order->country }}</p>
                @endif

                @if($order->email)
                    <p>{{ $order->email }}</p>
                @endif

                @if($order->phone_number)
                    <p>{{ $order->phone_number }}</p>
                @endif
            </td>

            <td></td>

            <td>
                @php($logo = Translation::get('invoice-logo', 'invoice', '', 'image'))
                @if(!$logo)
                    @php($logo = Customsetting::get('site_logo', Sites::getActive(), ''))
                @endif
                @if($logo)
                    <img
                        src="{{mediaHelper()->getSingleMedia($logo)->url ?? ''}}"
                        class="logo"
                        alt=""
                    >
                @endif
            </td>
        </tr>
    </table>

    @if ($order->note)
        <table class="table-note">
            <tr>
                <th>{{ Translation::get('note', 'invoice', 'Opmerking') }}</th>
            </tr>

            <tr>
                <td>{{ $order->note }}</td>
            </tr>
        </table>
    @endif

    <hr class="divider">

    <table class="table-dates">
        <tr>
            <th>{{ Translation::get('invoice-number', 'invoice', 'Factuur nummer') }}</th>
            <th>{{ Translation::get('invoice-date', 'invoice', 'Factuur datum') }}</th>
            <th>{{ Translation::get('payment-method', 'invoice', 'Betaal methode') }}</th>
            <th>{{ Translation::get('shipping-method', 'invoice', 'Verzend methode') }}</th>
            @if($order->deadlineLabel())
                <th>{{ Translation::get('deadline', 'invoice', 'Deadline') }}</th>
            @endif
        </tr>

        <tr>
            <td>{{ $order->invoice_id }}</td>
            <td>{{ $order->created_at->format('d-m-Y') }}</td>
            <td>{{ $order->paymentMethod ?: Translation::get('payment-method-not-chosen', 'invoice', 'niet gekozen') }}</td>
            <td>{{ $order->shippingMethod->name ?? Translation::get('shipping-method-not-chosen', 'invoice', 'niet gekozen') }}</td>
            @if($order->deadlineLabel())
                <td>{{ $order->deadlineLabel() }}</td>
            @endif
        </tr>
    </table>

    @if(count($order->customOrderFields(true)))
        <table class="table-dates">
            <tr>
                @foreach ($order->customOrderFields(true) as $label => $value)
                    <th>{{ $label }}</th>
                @endforeach
            </tr>

            <tr>
                @foreach ($order->customOrderFields(true) as $label => $value)
                    <td>{{ $value }}</td>
                @endforeach
            </tr>
        </table>
    @endif

    <div class="order">
        <h3>{{ Translation::get('order', 'invoice', 'Bestelling') }}</h3>

        <table>
            <tr>
                <th colspan="2">{{ Translation::get('product-name', 'invoice', 'Omschrijving') }}</th>
                <th class="numeric">{{ Translation::get('quantity', 'invoice', 'Aantal') }}</th>
            </tr>

            @foreach ($order->orderProducts as $orderProduct)
                <tr>
                    <td colspan="2">
                        {{ $orderProduct->name }}
                        @if($orderProduct->product_extras)
                            @foreach($orderProduct->product_extras as $option)
                                <br>
                                <small>{{$option['name']}}: {{$option['value']}}</small>
                            @endforeach
                        @endif
                        @if($orderProduct->hidden_options && is_array($orderProduct->hidden_options))
                            @foreach($orderProduct->hidden_options as $key => $value)
                                @if(! str($value)->contains('base64'))
                                <br>
                                <small>{{ $key }}: {{ $value }}</small>
                                @endif
                            @endforeach
                        @endif
                        @if($orderProduct->is_pre_order)
                            <br>
                            <small>{{ Translation::get('pre-order', 'invoice', 'Pre-order') }}</small>
                        @endif
                    </td>

                    <td class="numeric">
                        {{$orderProduct->quantity}}x
                    </td>
                </tr>
            @endforeach

            @foreach (\Dashed\DashedEcommerceCore\Classes\OrderAddOns\PackingSlip::addOnLines($order) as $addOnLine)
                <tr class="add-on-line">
                    <td colspan="2">
                        <b>{{ $addOnLine['prefix'] }}:</b> {{ $addOnLine['line']->name }}
                        @if($addOnLine['line']->product_extras)
                            @foreach($addOnLine['line']->product_extras as $option)
                                <br>
                                <small>{{ $option['name'] ?? '' }}: {{ $option['value'] ?? '' }}</small>
                            @endforeach
                        @endif
                        @if($addOnLine['line']->sku)
                            <br>
                            <small>SKU: {{ $addOnLine['line']->sku }}</small>
                        @endif
                    </td>

                    <td class="numeric">
                        {{ $addOnLine['line']->quantity }}x
                    </td>
                </tr>
            @endforeach
        </table>
    </div>

    @if(Customsetting::get('pos_enabled', null, false))
        <div style="text-align: center; width: 100%; margin-top: 20px;">
            {!! \Milon\Barcode\Facades\DNS1DFacade::getBarcodeHTML('order-'.  $order->id, 'C128', 2, 80) !!}
        </div>
    @endif

    <table class="table-details">
        <tr>
            <td class="sender" colspan="3">
                <h2>{{ Translation::get('our-information', 'invoice', 'Onze gegevens') }}</h2>
            </td>
        </tr>

        <tr>
            <td class="sender">
                <p>
                    <b>{{ Customsetting::get('site_name') }}</b>
                </p>

                <p>{{ Customsetting::get('company_street') . ' ' . Customsetting::get('company_street_number') . ', ' . Customsetting::get('company_postal_code') }}</p>

                <p>{{ Customsetting::get('company_city') . ', ' . Customsetting::get('company_country') }}</p>
            </td>

            <td class="sender">
                <p>
                    <b>{{ Translation::get('company-info', 'invoice', 'Bedrijfsgegevens') }}</b>
                </p>

                @if(Customsetting::get('company_kvk'))
                    <p>{{ Translation::get('kvk', 'invoice', 'KVK') }} {{ Customsetting::get('company_kvk') }}</p>
                @endif
                @if(Customsetting::get('company_btw'))
                    <p>{{ Translation::get('btw', 'invoice', 'BTW') }} {{ Customsetting::get('company_btw') }}</p>
                @endif
            </td>

            <td class="sender">
                <p>
                    <b>{{ Translation::get('company-contact', 'invoice', 'Contact') }}</b>
                </p>

                <p>{{ Customsetting::get('site_to_email') }}</p>

                <p>{{ Customsetting::get('company_phone_number') }}</p>

                <p>{{ url('/') }}</p>
            </td>
        </tr>
    </table>
</x-dashed-ecommerce-core::invoices.master>
