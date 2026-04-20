@php
    $isConcept = $order->isConcept();
    $pdfTitle = $isConcept
        ? Translation::get('order-confirmation-for', 'invoice', 'Orderbevestiging voor :siteName:', 'text', [
            'siteName' => Customsetting::get('site_name'),
        ])
        : Translation::get('invoice-for', 'invoice', 'Factuur voor :siteName:', 'text', [
            'siteName' => Customsetting::get('site_name'),
        ]);
@endphp

<x-dashed-ecommerce-core::invoices.master :title="$pdfTitle">
    @php
        $remainderQr = $isConcept ? null : \Dashed\DashedEcommerceCore\Classes\InvoiceQrCodeGenerator::for($order);
        $remainderOutstanding = $isConcept ? 0 : $order->outstandingAmount();
    @endphp

    @if ($isConcept)
        <h1>{{ Translation::get('order-confirmation', 'invoice', 'Orderbevestiging') }}</h1>
    @elseif ($order->status == 'return')
        <h1>{{ Translation::get('credit-invoice', 'invoice', 'Creditfactuur') }}</h1>
    @else
        <h1>{{ Translation::get('invoice', 'invoice', 'Factuur') }}</h1>
    @endif

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
                    <p>{{ $order->invoice_street }} {{ $order->invoice_house_nr }}</p>

                    <p>{{ $order->invoice_zip_code . ' ' . $order->invoice_city }}</p>
                    <p>{{ $order->invoice_country }}</p>
                @else
                    <p>{{ $order->street }} {{ $order->house_nr }}</p>

                    <p>{{ $order->zip_code . ' ' . $order->city }}</p>
                    <p>{{ $order->country }}</p>
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
                        src="{{ mediaHelper()->getSingleMedia($logo, 'original')->url ?? '' }}"
                        class="logo"
                        alt=""
                    >
                @endif
            </td>
        </tr>
    </table>

    <hr class="divider">

    <table class="table-dates">
        <tr>
            <th>
                @if ($isConcept)
                    {{ Translation::get('order-number', 'invoice', 'Ordernummer') }}
                @else
                    {{ Translation::get('invoice-number', 'invoice', 'Factuur nummer') }}
                @endif
            </th>
            <th>
                @if ($isConcept)
                    {{ Translation::get('order-date', 'invoice', 'Orderdatum') }}
                @else
                    {{ Translation::get('invoice-date', 'invoice', 'Factuur datum') }}
                @endif
            </th>
            <th>{{ Translation::get('payment-method', 'invoice', 'Betaal methode') }}</th>
            <th>{{ Translation::get('shipping-method', 'invoice', 'Verzend methode') }}</th>
        </tr>

        <tr>
            <td>{{ $order->invoice_id ?: ($isConcept ? ('#' . $order->id) : '') }}</td>
            <td>{{ $order->created_at->format('d-m-Y') }}</td>
            <td>{{ $order->paymentMethod ?: Translation::get('payment-method-not-chosen', 'invoice', 'niet gekozen') }}</td>
            <td>{{ $order->shippingMethod->name ?? Translation::get('shipping-method-not-chosen', 'invoice', 'niet gekozen') }}</td>
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

    @if($order->status == 'partially_paid')
        <table class="table-dates">
            <tr>
                <th>{{ Translation::get('amount-paid', 'invoice', 'Al betaald') }}</th>
                <th>{{ Translation::get('amount-to-pay', 'invoice', 'Nog te betalen') }}</th>
            </tr>

            <tr>
                <td>{{ CurrencyHelper::formatPriceForPDF($order->paidAmount) }}</td>
                <td>{{ CurrencyHelper::formatPriceForPDF($order->openAmount) }}</td>
            </tr>
        </table>
    @endif

    <div class="order">
        <h3>{{ Translation::get('order', 'invoice', 'Bestelling') }}</h3>

        <table>
            <tr>
                <th colspan="2">{{ Translation::get('product-name', 'invoice', 'Omschrijving') }}</th>
                <th class="numeric">{{ Translation::get('quantity', 'invoice', 'Aantal') }}</th>
                <th class="numeric">{{ Translation::get('total', 'invoice', 'Totaal') }}</th>
            </tr>

            @foreach ($order->orderProducts as $orderProduct)
                <tr>
                    <td colspan="2">
                        {{ $orderProduct->name }}
                        @if($orderProduct->product_extras)
                            @foreach($orderProduct->product_extras as $option)
                                <br>
                                <small>{{ $option['name'] }}: {{ $option['value'] }}</small>
                            @endforeach
                        @endif
                    </td>

                    <td class="numeric">
                        {{ $orderProduct->quantity }}x
                    </td>

                    <td class="numeric">
                        {{ CurrencyHelper::formatPriceForPDF($orderProduct->price, 'EUR', true) }}
                    </td>
                </tr>
            @endforeach
        </table>
    </div>

    <div class="order">
        <h2>{{ Translation::get('totals', 'invoice', 'Totalen') }}</h2>

        @if($order->vat_reverse_charge)
            <p class="total">
                {{ Translation::get('vat-shifted', 'invoice', 'BTW verlegd') . ': ' . CurrencyHelper::formatPriceForPDF(0, 'EUR', true) }}
            </p>

{{--            <p class="total">--}}
{{--                {{ Translation::get(--}}
{{--                    'vat-reverse-charge-note',--}}
{{--                    'invoice',--}}
{{--                    'BTW verlegd naar afnemer (artikel 44 en 196 EU btw-richtlijn)'--}}
{{--                ) }}--}}
{{--            </p>--}}
        @elseif((!$order->shippingMethod || !$order->shippingMethod->shippingZone->hide_vat_on_invoice))
            @if(count($order->vat_percentages ?? []) > 1)
                <table>
                    <tr>
                        <th colspan="2"></th>
                        <th class="numeric">{{ Translation::get('vat', 'invoice', 'BTW') }}</th>
                        <th class="numeric">{{ Translation::get('subtotal', 'invoice', 'Subtotaal') }}</th>
                    </tr>

                    @foreach($order->vat_percentages as $vatPercentage => $vatAmount)
                        <tr>
                            <td colspan="2"></td>
                            <td class="numeric">
                                {{ Translation::get('btw-percentage', 'invoice', 'BTW :percentage:%', 'text', [
                                    'percentage' => number_format($vatPercentage, 0),
                                ]) }}
                            </td>
                            <td class="numeric">{{ CurrencyHelper::formatPriceForPDF($vatAmount, 'EUR', true) }}</td>
                        </tr>
                    @endforeach
                </table>
            @endif

            <p class="total">
                {{ Translation::get('vat', 'invoice', 'BTW') . (count($order->vat_percentages ?? []) == 1 ? ' ' . array_key_first($order->vat_percentages) . '%' : '') . ': ' . CurrencyHelper::formatPriceForPDF($order->btw, 'EUR', true) }}
            </p>
        @endif

        <p class="total">{{ Translation::get('subtotal', 'invoice', 'Subtotal') . ': ' . CurrencyHelper::formatPriceForPDF($order->subtotal, 'EUR', true) }}</p>

        @if($order->discount != 0.00)
            <p class="total">{{ Translation::get('discount', 'invoice', 'Korting') . ': ' . CurrencyHelper::formatPriceForPDF($order->discount, 'EUR', true) }}</p>
        @endif

        <p class="total">{{ Translation::get('total', 'invoice', 'Totaal') . ': ' . CurrencyHelper::formatPriceForPDF($order->total, 'EUR', true) }}</p>
    </div>

    @if ($order->note || $order->vat_reverse_charge)
        <table class="table-note">
            <tr>
                <th>{{ Translation::get('note', 'invoice', 'Opmerking') }}</th>
            </tr>

            <tr>
                <td>
                    @if($order->note)
                        {{ $order->note }}
                    @endif

                    @if($order->note && $order->vat_reverse_charge)
                        <br><br>
                    @endif

                    @if($order->vat_reverse_charge)
                        {{ Translation::get(
                            'vat-reverse-charge-note',
                            'invoice',
                            'BTW verlegd naar afnemer (artikel 44 en 196 EU btw-richtlijn)'
                        ) }}
                    @endif
                </td>
            </tr>
        </table>
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

                <p>{{ Customsetting::get('company_street') . ' ' . Customsetting::get('company_street_number') }}</p>
                <p>{{ Customsetting::get('company_postal_code') . ' ' . Customsetting::get('company_city') }}</p>

                <p>{{ Customsetting::get('company_country') }}</p>
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
                @if(Customsetting::get('company_bank_number'))
                    <p>{{ Translation::get('bank-number', 'invoice', 'Bank') }} {{ Customsetting::get('company_bank_number') }}</p>
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

    @if ($remainderQr && $remainderOutstanding > 0)
        <div style="margin-top: 20px; text-align: center;">
            <img src="{{ $remainderQr }}" alt="QR" style="width: 140px; height: 140px;" />
            <p style="font-size: 11px;">
                {{ Translation::get('invoice-qr-remainder-hint', 'invoice', 'Openstaand bij verzending: € :amount: - scan de QR-code om het actuele openstaande bedrag te betalen.', 'text', ['amount' => number_format($remainderOutstanding, 2, ',', '.')]) }}
            </p>
        </div>
    @endif
</x-dashed-ecommerce-core::invoices.master>
