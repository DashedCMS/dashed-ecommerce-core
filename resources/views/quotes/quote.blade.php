@php
    use Dashed\DashedEcommerceCore\Classes\CurrencyHelper;
    use Dashed\DashedEcommerceCore\Services\Quotes\QuoteBranding;

    $brand = QuoteBranding::for($quote);
    $color = $brand->color();
    $logo = $brand->logoUrl();
    $exVat = (bool) $quote->prices_ex_vat;
    $date = $quote->sent_at ?? $quote->created_at;
    $pdfTitle = Translation::get('quote-for', 'quote', 'Offerte van :siteName:', 'text', [
        'siteName' => $brand->companyName(),
    ]);
@endphp
<!DOCTYPE html>
<html lang="{{ $quote->locale ?? 'nl' }}">
<head>
    <meta charset="UTF-8">
    <title>{{ $pdfTitle }}</title>
    <style>
        @page { margin: 44px 52px 84px 52px; }

        * { box-sizing: border-box; }

        body {
            font-family: Helvetica, Arial, sans-serif;
            font-size: 10pt;
            line-height: 1.45;
            color: #3f3f46;
            margin: 0;
        }

        p { margin: 0; }

        table { width: 100%; border-collapse: collapse; }

        .muted { color: #71717a; }

        .label {
            color: {{ $color }};
            font-size: 7.5pt;
            font-weight: bold;
            letter-spacing: 1px;
            text-transform: uppercase;
            margin-bottom: 4px;
        }

        .header td { vertical-align: middle; }
        .logo { max-height: 64px; max-width: 220px; }
        .brand-name { font-size: 20pt; font-weight: bold; color: #18181b; }
        .doc-title { text-align: right; }
        .doc-title h1 {
            font-size: 26pt;
            margin: 0;
            color: {{ $color }};
            line-height: 1;
        }
        .doc-title p { margin-top: 6px; font-size: 9pt; }

        .accent {
            height: 4px;
            background: {{ $color }};
            border-radius: 2px;
            margin: 22px 0 26px 0;
        }

        .parties { table-layout: fixed; }
        .parties td { vertical-align: top; padding-right: 16px; }
        .parties td:last-child { padding-right: 0; }
        .parties b { color: #18181b; }

        .title {
            font-size: 15pt;
            font-weight: bold;
            color: #18181b;
            margin: 30px 0 6px 0;
        }

        .intro { margin: 8px 0 0 0; }
        .intro p { margin-top: 6px; }

        .lines { margin-top: 20px; table-layout: fixed; }
        .lines th {
            background: {{ $color }};
            color: #ffffff;
            font-size: 7.5pt;
            letter-spacing: 1px;
            text-transform: uppercase;
            text-align: left;
            padding: 9px 12px;
        }
        .lines th.numeric { text-align: right; }
        .lines td {
            padding: 12px;
            vertical-align: top;
            border-bottom: 1px solid #e4e4e7;
        }
        .lines .name { font-weight: bold; color: #18181b; }
        .lines .desc { color: #52525b; margin-top: 3px; }
        .lines .dimmed td, .lines .dimmed .name { color: #a1a1aa; }

        .pill {
            display: inline-block;
            margin-top: 5px;
            padding: 1px 7px;
            border-radius: 8px;
            font-size: 7pt;
            font-weight: bold;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            border: 1px solid {{ $color }};
            color: {{ $color }};
        }

        .numeric { text-align: right; white-space: nowrap; }

        .totals { width: 46%; margin: 16px 0 0 54%; }
        .totals td { padding: 3px 12px; }
        .grand {
            margin: 10px 0 0 54%;
            background: {{ $color }};
            color: #ffffff;
            border-radius: 6px;
            padding: 12px;
        }
        .grand td { color: #ffffff; font-size: 12pt; font-weight: bold; padding: 0; }

        .section { margin-top: 30px; }
        .section h2 { page-break-after: avoid; }
        .section h2 {
            font-size: 13pt;
            color: #18181b;
            margin: 0 0 8px 0;
        }
        .section ul { margin: 6px 0 0 0; padding-left: 16px; }
        .section li { margin-bottom: 5px; }
        .section p { margin-top: 6px; }

        .signoff { page-break-inside: avoid; }
        .sign { margin-top: 28px; table-layout: fixed; }
        .sign td { vertical-align: bottom; padding-right: 24px; }
        .sign td:last-child { padding-right: 0; }
        .sign .line { border-bottom: 1px solid #a1a1aa; height: 56px; }
        .sign .value { font-size: 11pt; color: #18181b; padding-bottom: 4px; }
        .sign .caption { color: {{ $color }}; font-size: 8pt; margin-top: 4px; }
        .signature { max-height: 52px; max-width: 200px; }

        .evidence { margin-top: 10px; font-size: 8pt; }

        footer {
            position: fixed;
            left: -52px;
            right: -52px;
            bottom: -84px;
            height: 44px;
            background: {{ $color }};
            color: #ffffff;
            font-size: 8pt;
            text-align: center;
            padding-top: 15px;
        }
    </style>
</head>
<body>
<footer>{{ $brand->footerLine() }}</footer>

<table class="header">
    <tr>
        <td>
            @if ($logo)
                <img src="{{ $logo }}" class="logo" alt="">
            @else
                <span class="brand-name">{{ $brand->companyName() }}</span>
            @endif
        </td>
        <td class="doc-title">
            <h1>{{ Translation::get('quote', 'quote', 'Offerte') }}</h1>
            <p class="muted">{{ Translation::get('number-short', 'quote', 'Nr.') }} {{ $quote->displayNumber() }}</p>
        </td>
    </tr>
</table>

<div class="accent"></div>

<table class="parties">
    <tr>
        <td>
            <p class="label">{{ Translation::get('to', 'quote', 'Aan') }}</p>
            @if ($quote->company_name)
                <p><b>{{ $quote->company_name }}</b></p>
            @endif
            @if ($quote->fullName())
                <p>{{ $quote->company_name ? Translation::get('attn', 'quote', 't.a.v.').' ' : '' }}{{ $quote->fullName() }}</p>
            @endif
            @if ($quote->invoice_street)
                <p>{{ $quote->invoice_street }} {{ $quote->invoice_house_nr }}</p>
            @endif
            @if (trim($quote->invoice_zip_code.' '.$quote->invoice_city))
                <p>{{ trim($quote->invoice_zip_code.' '.$quote->invoice_city) }}</p>
            @endif
            @if ($quote->invoice_country)
                <p>{{ $quote->invoice_country }}</p>
            @endif
            @if ($quote->email)
                <p>{{ $quote->email }}</p>
            @endif
            @if ($quote->btw_id)
                <p>{{ Translation::get('tax-id', 'quote', 'BTW') }} {{ $quote->btw_id }}</p>
            @endif
        </td>
        <td>
            <p class="label">{{ Translation::get('from', 'quote', 'Van') }}</p>
            <p><b>{{ $brand->companyName() }}</b></p>
            @foreach ($brand->senderLines() as $line)
                <p>{{ $line }}</p>
            @endforeach
        </td>
        <td>
            <p class="label">{{ Translation::get('details', 'quote', 'Details') }}</p>
            <p><b>{{ $date->translatedFormat('j F Y') }}</b></p>
            @if ($quote->valid_until)
                <p>{{ Translation::get('valid-until', 'quote', 'Geldig t/m') }} {{ $quote->valid_until->translatedFormat('j M Y') }}</p>
            @endif
            @if ($quote->reference)
                <p>{{ Translation::get('reference-short', 'quote', 'Ref:') }} {{ $quote->reference }}</p>
            @endif
        </td>
    </tr>
</table>

@if ($quote->title)
    <p class="title">{{ $quote->title }}</p>
@endif

@if ($quote->intro)
    <div class="intro">
        @include('dashed-ecommerce-core::quotes.partials.text', ['text' => $quote->intro])
    </div>
@endif

<table class="lines">
    <thead>
    <tr>
        <th style="width: 62%">{{ Translation::get('description', 'quote', 'Omschrijving') }}</th>
        <th class="numeric" style="width: 12%">{{ Translation::get('quantity', 'quote', 'Aantal') }}</th>
        <th class="numeric" style="width: 26%">
            {{ $exVat
                ? Translation::get('amount-ex-vat', 'quote', 'Bedrag ex btw')
                : Translation::get('amount-incl-vat', 'quote', 'Bedrag incl. btw') }}
        </th>
    </tr>
    </thead>
    <tbody>
    @foreach ($quote->lines as $line)
        @php($notChosen = $line->is_optional && ! $line->is_selected)
        <tr class="{{ $notChosen ? 'dimmed' : '' }}">
            <td>
                <p class="name">{{ $line->name }}</p>
                @if ($line->description)
                    <p class="desc">{!! nl2br(e($line->description)) !!}</p>
                @endif
                @if ($line->is_optional)
                    <span class="pill">
                        @if ($accepted)
                            {{ $line->is_selected
                                ? Translation::get('chosen', 'quote', 'Gekozen')
                                : Translation::get('not-chosen', 'quote', 'Niet gekozen') }}
                        @else
                            {{ $line->choice_group
                                ? Translation::get('choice-option', 'quote', 'Keuze')
                                : Translation::get('optional', 'quote', 'Optioneel') }}{{ $notChosen ? ', '.Translation::get('not-in-total', 'quote', 'niet in totaal') : '' }}
                        @endif
                    </span>
                @endif
            </td>
            <td class="numeric">{{ $line->quantity }}</td>
            <td class="numeric">
                {{ CurrencyHelper::formatPriceForPDF($exVat ? $line->lineTotalExVat() : $line->lineTotal()) }}
            </td>
        </tr>
    @endforeach
    </tbody>
</table>

<table class="totals">
    <tr>
        <td class="numeric muted">{{ Translation::get('subtotal-ex-vat', 'quote', 'Subtotaal ex btw') }}</td>
        <td class="numeric">{{ CurrencyHelper::formatPriceForPDF($totals->totalExVat()) }}</td>
    </tr>
    @foreach ($totals->vatPerRate as $rate => $amount)
        <tr>
            <td class="numeric muted">{{ Translation::get('vat-percentage', 'quote', 'BTW :percentage:%', 'text', ['percentage' => $rate]) }}</td>
            <td class="numeric">{{ CurrencyHelper::formatPriceForPDF($amount) }}</td>
        </tr>
    @endforeach
</table>

<div class="grand">
    <table>
        <tr>
            <td>{{ Translation::get('total-incl-vat', 'quote', 'Totaal incl. btw') }}</td>
            <td class="numeric">{{ CurrencyHelper::formatPriceForPDF($totals->total) }}</td>
        </tr>
    </table>
</div>

@if ($quote->terms)
    <div class="section">
        <h2>{{ Translation::get('terms', 'quote', 'Planning en voorwaarden') }}</h2>
        @include('dashed-ecommerce-core::quotes.partials.text', ['text' => $quote->terms])
    </div>
@endif

<div class="section">
    <h2>{{ Translation::get('agreement', 'quote', 'Akkoord') }}</h2>

    @if ($quote->acceptance_text)
        @include('dashed-ecommerce-core::quotes.partials.text', ['text' => $quote->acceptance_text])
    @endif

    <div class="signoff">
    <table class="sign">
        <tr>
            <td>
                <div class="line">
                    @if ($accepted)
                        <div class="value" style="padding-top: 34px">{{ $quote->accepted_name }}</div>
                    @endif
                </div>
                <p class="caption">{{ Translation::get('name', 'quote', 'Naam') }}</p>
            </td>
            <td>
                <div class="line">
                    @if ($accepted)
                        <div class="value" style="padding-top: 34px">{{ $quote->accepted_at?->format('d-m-Y H:i') }}</div>
                    @endif
                </div>
                <p class="caption">{{ Translation::get('date', 'quote', 'Datum') }}</p>
            </td>
            <td>
                <div class="line">
                    @if ($accepted && $quote->accepted_signature)
                        <img src="{{ $quote->accepted_signature }}" class="signature" alt="">
                    @endif
                </div>
                <p class="caption">{{ Translation::get('signature', 'quote', 'Handtekening') }}</p>
            </td>
        </tr>
    </table>

    @if ($accepted)
        <p class="evidence muted">
            {{ Translation::get('accepted-by', 'quote', 'Akkoord gegeven door :naam: op :datum:', 'text', [
                'naam' => $quote->accepted_name,
                'datum' => $quote->accepted_at?->format('d-m-Y H:i'),
            ]) }}
            &middot; {{ Translation::get('accepted-ip', 'quote', 'IP-adres') }}: {{ $quote->accepted_ip }}
        </p>
    @endif
    </div>
</div>
</body>
</html>
