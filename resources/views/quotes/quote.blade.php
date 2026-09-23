@php
    use Dashed\DashedEcommerceCore\Classes\CurrencyHelper;
    use Dashed\DashedCore\Models\Customsetting;

    $exVat = (bool) $quote->prices_ex_vat;
    $pdfTitle = Translation::get('quote-for', 'quote', 'Offerte van :siteName:', 'text', [
        'siteName' => Customsetting::get('site_name'),
    ]);
@endphp

<x-dashed-ecommerce-core::invoices.master :title="$pdfTitle">
    <h1>{{ Translation::get('quote', 'quote', 'Offerte') }}</h1>

    <table class="table-details">
        <tr>
            <td class="receiver">
                <h2>{{ Translation::get('to', 'quote', 'Aan') }}</h2>

                @if ($quote->company_name)
                    <p><b>{{ $quote->company_name }}</b></p>
                @endif
                @if ($quote->btw_id)
                    <p>{{ Translation::get('tax-id', 'quote', 'BTW') }} {{ $quote->btw_id }}</p>
                @endif
                @if ($quote->fullName())
                    <p>{{ Translation::get('attn', 'quote', 't.a.v.') }} {{ $quote->fullName() }}</p>
                @endif
                @if ($quote->invoice_street)
                    <p>{{ $quote->invoice_street }} {{ $quote->invoice_house_nr }}</p>
                    <p>{{ trim($quote->invoice_zip_code . ' ' . $quote->invoice_city) }}</p>
                    <p>{{ $quote->invoice_country }}</p>
                @endif
                @if ($quote->email)
                    <p>{{ $quote->email }}</p>
                @endif
            </td>

            <td class="sender">
                <h2>{{ Translation::get('from', 'quote', 'Van') }}</h2>
                <p><b>{{ Customsetting::get('site_name') }}</b></p>
                @if (Customsetting::get('company_street'))
                    <p>{{ Customsetting::get('company_street') }} {{ Customsetting::get('company_street_number') }}</p>
                    <p>{{ Customsetting::get('company_postal_code') }} {{ Customsetting::get('company_city') }}</p>
                @endif
                @if (Customsetting::get('site_from_email'))
                    <p>{{ Customsetting::get('site_from_email') }}</p>
                @endif
                @if (Customsetting::get('company_btw'))
                    <p>{{ Translation::get('tax-id', 'quote', 'BTW') }} {{ Customsetting::get('company_btw') }}</p>
                @endif
            </td>
        </tr>
    </table>

    <table class="table-dates">
        <tr>
            <td>
                <p><b>{{ Translation::get('quote-number', 'quote', 'Offertenummer') }}</b></p>
                <p>{{ $quote->displayNumber() }}</p>
            </td>
            <td>
                <p><b>{{ Translation::get('quote-date', 'quote', 'Datum') }}</b></p>
                <p>{{ ($quote->sent_at ?? $quote->created_at)->format('d-m-Y') }}</p>
            </td>
            <td>
                <p><b>{{ Translation::get('valid-until', 'quote', 'Geldig t/m') }}</b></p>
                <p>{{ $quote->valid_until?->format('d-m-Y') }}</p>
            </td>
            @if ($quote->reference)
                <td>
                    <p><b>{{ Translation::get('reference', 'quote', 'Referentie') }}</b></p>
                    <p>{{ $quote->reference }}</p>
                </td>
            @endif
        </tr>
    </table>

    @if ($quote->title)
        <h2>{{ $quote->title }}</h2>
    @endif

    @if ($quote->intro)
        <p>{!! nl2br(e($quote->intro)) !!}</p>
    @endif

    <div class="order">
        <table>
            <tr>
                <th colspan="2">{{ Translation::get('description', 'quote', 'Omschrijving') }}</th>
                <th class="numeric">{{ Translation::get('quantity', 'quote', 'Aantal') }}</th>
                <th class="numeric">
                    {{ $exVat
                        ? Translation::get('amount-ex-vat', 'quote', 'Bedrag ex btw')
                        : Translation::get('amount-incl-vat', 'quote', 'Bedrag incl. btw') }}
                </th>
            </tr>

            @foreach ($quote->lines as $line)
                <tr>
                    <td colspan="2">
                        {{ $line->name }}
                        @if ($line->description)
                            <br><small>{!! nl2br(e($line->description)) !!}</small>
                        @endif
                        @if ($line->is_optional)
                            <br><small><b>{{ $line->choice_group
                                ? Translation::get('choice-option', 'quote', 'Keuze')
                                : Translation::get('optional', 'quote', 'Optioneel') }}</b></small>
                        @endif
                    </td>
                    <td class="numeric">{{ $line->quantity }}x</td>
                    <td class="numeric">
                        {{ CurrencyHelper::formatPriceForPDF($exVat ? $line->lineTotalExVat() : $line->lineTotal(), 'EUR', true) }}
                    </td>
                </tr>
            @endforeach
        </table>
    </div>

    <div class="order">
        <h2>{{ Translation::get('totals', 'quote', 'Totalen') }}</h2>

        <p class="total">
            {{ Translation::get('subtotal-ex-vat', 'quote', 'Subtotaal ex btw') . ': ' . CurrencyHelper::formatPriceForPDF($totals->totalExVat(), 'EUR', true) }}
        </p>

        @foreach ($totals->vatPerRate as $rate => $amount)
            <p class="total">
                {{ Translation::get('vat-percentage', 'quote', 'BTW :percentage:%', 'text', ['percentage' => $rate]) . ': ' . CurrencyHelper::formatPriceForPDF($amount, 'EUR', true) }}
            </p>
        @endforeach

        <p class="total">
            <b>{{ Translation::get('total-incl-vat', 'quote', 'Totaal incl. btw') . ': ' . CurrencyHelper::formatPriceForPDF($totals->total, 'EUR', true) }}</b>
        </p>
    </div>

    @if ($quote->terms)
        <div class="order">
            <h2>{{ Translation::get('terms', 'quote', 'Planning en voorwaarden') }}</h2>
            <p>{!! nl2br(e($quote->terms)) !!}</p>
        </div>
    @endif

    <div class="order">
        <h2>{{ Translation::get('agreement', 'quote', 'Akkoord') }}</h2>

        @if ($quote->acceptance_text)
            <p>{!! nl2br(e($quote->acceptance_text)) !!}</p>
        @endif

        @if ($accepted)
            <p>
                {{ Translation::get('accepted-by', 'quote', 'Akkoord gegeven door :naam: op :datum:', 'text', [
                    'naam' => $quote->accepted_name,
                    'datum' => $quote->accepted_at?->format('d-m-Y H:i'),
                ]) }}
            </p>
            <p><small>{{ Translation::get('accepted-ip', 'quote', 'IP-adres') }}: {{ $quote->accepted_ip }}</small></p>
        @else
            <table class="table-dates">
                <tr>
                    <td><p>{{ Translation::get('name', 'quote', 'Naam') }}</p><p>_______________________</p></td>
                    <td><p>{{ Translation::get('date', 'quote', 'Datum') }}</p><p>_______________________</p></td>
                    <td><p>{{ Translation::get('signature', 'quote', 'Handtekening') }}</p><p>_______________________</p></td>
                </tr>
            </table>
        @endif
    </div>
</x-dashed-ecommerce-core::invoices.master>
