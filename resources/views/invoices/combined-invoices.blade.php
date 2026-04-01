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
                            {{CurrencyHelper::formatPriceForPDF($productSale['totalPrice'], 'EUR', true)}}
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
                        <td class="numeric">{{ CurrencyHelper::formatPriceForPDF($vatAmount, 'EUR', true) }}</td>
                    </tr>
                @endforeach
            </table>
        @endif

        @if($discount != 0.00)
            <p class="total">{{ Translation::get('discount', 'invoice', 'Korting') . ': ' . CurrencyHelper::formatPriceForPDF($discount, 'EUR', true) }}</p>
        @endif

        <p class="total">{{ Translation::get('subtotal', 'invoice', 'Subtotaal ex BTW') . ': ' . CurrencyHelper::formatPriceForPDF($subTotal, 'EUR', true) }}</p>

        <p class="total">{{ Translation::get('vat', 'invoice', 'BTW') . (count($vatPercentages ?? []) == 1 ? ' ' . array_key_first($vatPercentages) . '%' : '') .  ': ' . CurrencyHelper::formatPriceForPDF($btw, 'EUR', true) }}</p>

        <p class="total">{{ Translation::get('total', 'invoice', 'Totaal') . ': ' . CurrencyHelper::formatPriceForPDF($total, 'EUR', true) }}</p>
    </div>

    <div class="order">
        <h2>BTW uitsplitsing</h2>

        <table>
            <tr>
                <th colspan="4">Omzet normale verzendzones</th>
            </tr>
            <tr>
                <th>Verzendzone</th>
                <th class="numeric">Excl btw</th>
                <th class="numeric">BTW</th>
                <th class="numeric">Incl btw</th>
            </tr>

            @forelse($normalZoneTotals as $normalZoneTotal)
                <tr>
                    <td>{{ $normalZoneTotal['zone'] }}</td>
                    <td class="numeric">{{ CurrencyHelper::formatPriceForPDF($normalZoneTotal['ex_vat'], 'EUR', true) }}</td>
                    <td class="numeric">{{ CurrencyHelper::formatPriceForPDF($normalZoneTotal['vat'], 'EUR', true) }}</td>
                    <td class="numeric">{{ CurrencyHelper::formatPriceForPDF($normalZoneTotal['incl_vat'], 'EUR', true) }}</td>
                </tr>
            @empty
                <tr>
                    <td colspan="4">Geen omzet in normale verzendzones in deze periode</td>
                </tr>
            @endforelse
        </table>

        <br>

        <table>
            <tr>
                <th colspan="4">OSS - buitenlandse btw particulieren</th>
            </tr>
            <tr>
                <th>Verzendzone</th>
                <th class="numeric">Excl btw</th>
                <th class="numeric">BTW</th>
                <th class="numeric">Incl btw</th>
            </tr>

            @forelse($ossTotals as $ossTotal)
                <tr>
                    <td>{{ $ossTotal['zone'] }}</td>
                    <td class="numeric">{{ CurrencyHelper::formatPriceForPDF($ossTotal['ex_vat'], 'EUR', true) }}</td>
                    <td class="numeric">{{ CurrencyHelper::formatPriceForPDF($ossTotal['vat'], 'EUR', true) }}</td>
                    <td class="numeric">{{ CurrencyHelper::formatPriceForPDF($ossTotal['incl_vat'], 'EUR', true) }}</td>
                </tr>
            @empty
                <tr>
                    <td colspan="4">Geen OSS omzet in deze periode</td>
                </tr>
            @endforelse
        </table>

        <br>

        <table>
            <tr>
                <th colspan="3">ICP - verlegd</th>
            </tr>
            <tr>
                <th>Land</th>
                <th>BTW-nummer</th>
                <th class="numeric">Omzet</th>
            </tr>

            @forelse($icpTotals as $icpTotal)
                <tr>
                    <td>{{ $icpTotal['country'] }}</td>
                    <td>{{ $icpTotal['vat_number'] }}</td>
                    <td class="numeric">{{ CurrencyHelper::formatPriceForPDF($icpTotal['revenue'], 'EUR', true) }}</td>
                </tr>
            @empty
                <tr>
                    <td colspan="3">Geen ICP omzet in deze periode</td>
                </tr>
            @endforelse
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
