<x-dashed-ecommerce-core::invoices.master :title="Translation::get('combined-invoice-for', 'invoice', 'Verzamel factuur voor :siteName:', 'text', [
            'siteName' => Customsetting::get('site_name')
        ])">
    <h1>{{ Translation::get('combined-invoice', 'invoice', 'Verzamelfactuur') }}</h1>

    <table class="table-details">
        <tr>
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

    <hr class="divider">

    <table class="table-dates">
        <tr>
            <th>{{ Translation::get('start-date', 'invoice', 'Start datum') }}</th>
            <th>{{ Translation::get('end-date', 'invoice', 'Eind datum') }}</th>
        </tr>

        <tr>
            <td>{{ $startDate->format('d-m-Y') }}</td>
            <td>{{ $endDate->format('d-m-Y') }}</td>
        </tr>
    </table>

    <div class="order">
        <h3>{{ Translation::get('ordered-items', 'invoice', 'Bestelde items') }}</h3>

        <table>
            <tr>
                <th colspan="2">{{ Translation::get('product-name', 'invoice', 'Omschrijving') }}</th>
                <th class="numeric">{{ Translation::get('quantity', 'invoice', 'Aantal') }}</th>
                <th class="numeric">{{ Translation::get('total', 'invoice', 'Totaal') }}</th>
            </tr>

            @foreach($productSales as $productSale)
                @if($productSale['quantity'] > 0)
                    <tr>
                        <td colspan="2">
                            {{ $productSale['name'] }}
                        </td>

                        <td class="numeric">
                            {{$productSale['quantity']}}x
                        </td>

                        <td class="numeric">
                            {{CurrencyHelper::formatPrice($productSale['totalPrice'], 'EUR', true)}}
                        </td>
                    </tr>
                @endif
            @endforeach
        </table>
    </div>

    <div class="order">
        <h2>{{ Translation::get('totals', 'invoice', 'Totalen') }}</h2>

        @if(count($vatPercentages ?? []) > 1)
            <table>
                <tr>
                    <th colspan="2"></th>
                    <th class="numeric">{{ Translation::get('vat', 'invoice', 'BTW') }}</th>
                    <th class="numeric">{{ Translation::get('subtotal', 'invoice', 'Subtotaal') }}</th>
                </tr>

                @foreach($vatPercentages as $vatPercentage => $vatAmount)
                    <tr>
                        <td colspan="2"></td>
                        <td class="numeric">{{ Translation::get('btw-percentage', 'invoice', 'BTW :percentage:%', 'text', [
                                    'percentage' => number_format($vatPercentage, 0),
                                ]) }}</td>
                        <td class="numeric">{{ CurrencyHelper::formatPrice($vatAmount, 'EUR', true) }}</td>
                    </tr>
                @endforeach
            </table>
        @endif

        <p class="total">{{ Translation::get('vat', 'invoice', 'BTW') . (count($vatPercentages ?? []) == 1 ? ' ' . array_key_first($vatPercentages) . '%' : '') .  ': ' . CurrencyHelper::formatPrice($btw, 'EUR', true) }}</p>

        <p class="total">{{ Translation::get('subtotal', 'invoice', 'Subtotal') . ': ' . CurrencyHelper::formatPrice($subTotal, 'EUR', true) }}</p>

        @if($discount != 0.00)
            <p class="total">{{ Translation::get('discount', 'invoice', 'Korting') . ': ' . CurrencyHelper::formatPrice($discount, 'EUR', true) }}</p>
        @endif

        <p class="total">{{ Translation::get('total', 'invoice', 'Totaal') . ': ' . CurrencyHelper::formatPrice($total, 'EUR', true) }}</p>
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
