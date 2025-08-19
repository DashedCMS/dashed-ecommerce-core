<x-dashed-ecommerce-core::invoices.master :title="Translation::get('invoice-for', 'invoice', 'Factuur voor :siteName:', 'text', [
            'siteName' => Customsetting::get('site_name')
        ])">
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
        </tr>

        <tr>
            <td>{{ $order->invoice_id }}</td>
            <td>{{ $order->created_at->format('d-m-Y') }}</td>
            <td>{{ $order->paymentMethod ?: Translation::get('payment-method-not-chosen', 'invoice', 'niet gekozen') }}</td>
            <td>{{ $order->shippingMethod->name ?? Translation::get('shipping-method-not-chosen', 'invoice', 'niet gekozen') }}</td>
        </tr>
    </table>

    @if(count($order->customOrderFields()))
        <table class="table-dates">
            <tr>
                @foreach ($order->customOrderFields() as $label => $value)
                    <th>{{ $label }}</th>
                @endforeach
            </tr>

            <tr>
                @foreach ($order->customOrderFields() as $label => $value)
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
                    </td>

                    <td class="numeric">
                        {{$orderProduct->quantity}}x
                    </td>
                </tr>
            @endforeach
        </table>
    </div>

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
