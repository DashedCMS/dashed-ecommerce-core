<!doctype html>
<html>
<head>
    <meta http-equiv="Content-Type" content="text/html; charset=utf-8"/>
    <title>{{Translation::get('financial-report', 'financial-report', 'Financieel rapport van :startDate: tot :endDate:', 'text', [
    'startDate' => $startDate->format('d-m-Y'),
    'endDate' => $endDate->format('d-m-Y'),
])}} {{Customsetting::get('site_name')}}</title>

    {{--    <link rel="preconnect" href="https://fonts.googleapis.com">--}}
    {{--    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>--}}
    {{--    <link href="https://fonts.googleapis.com/css2?family=Roboto:ital,wght@0,100;0,300;0,400;0,500;0,700;0,900;1,100;1,300;1,400;1,500;1,700;1,900&display=swap"--}}
    {{--          rel="stylesheet">--}}

    <style>
        body {
            font-family: 'Helvetica', sans-serif;
            font-size: 14px;
        }

        .item-title {
            /*display: grid;*/
            /*grid-template-columns: repeat(2, minmax(0, 1fr));*/
            /*justify-content: space-between;*/
            font-weight: 500;
            font-size: 16px;
            width: 100%;
        }

        .item-title td {
            width: 50%;
        }

        .item-title td:nth-of-type(2) {
            text-align: right;
        }

        .subtitle {
            font-size: 10px;
        }

        .mt {
            margin-top: 15px;
        }

        .mt-2 {
            margin-top: 30px;
        }

        .mb {
            margin-bottom: 15px;
        }

        .title {
            font-weight: 600;
            font-size: 20px;
        }

        table {
            width: 100%;
        }
    </style>
</head>
<body>
<div style="width: 500px; margin-left: auto; margin-right: auto;">
    <div>
        <div>
            <p style="font-size: 25px; font-weight: 600;">
                {{Translation::get('financial-report', 'financial-report', 'Financieel rapport van :siteName:', 'text', [
    'siteName'=>Customsetting::get('site_name')
    ])}}
            </p>
        </div>
        <div style="display: flex; justify-content: space-between;">
            <div style="display: grid; gap: 5px;">
                <span style="font-weight: bold;">Datum vanaf</span>
                <span>{{$startDate->format('d-m-Y')}}</span>
            </div>
            <div style="display: grid; gap: 5px;">
                <span style="font-weight: bold;">Datum tot</span>
                <span>{{$endDate->format('d-m-Y')}}</span>
            </div>
        </div>
        <div class="mt-2">
            <div>
                <span class="title">Verkopen</span>
            </div>
            <hr/>
            <table>
                <tr class="item-title">
                    <td>Bruto verkopen</td>
                    <td>{{'€ ' . number_format($grossRevenue, 2, ',', '')}}</td>
                </tr>
                <tr>
                    <td colspan="2" class="subtitle">
                        productprijs + aantal (excl. belastingen, korting en retouren)
                    </td>
                </tr>
            </table>
            <table class="mt">
                <tr class="item-title">
                    <td>Kortingen</td>
                    <td>{{'€ ' . number_format($discounts, 2, ',', '')}}</td>
                </tr>
                <tr>
                    <td colspan="2" class="subtitle">korting op orderregel + korting op gehele verkoop</td>
                </tr>
            </table>
            <table class="mt">
                <tr class="item-title">
                    <td>Retouren</td>
                    <td>{{'€ ' . number_format($returns, 2, ',', '')}}</td>
                </tr>
            </table>
            <hr class="mb mt"/>
            <table>
                <tr class="item-title">
                    <td>Netto verkopen</td>
                    <td>{{'€ ' . number_format($netRevenue, 2, ',', '')}}</td>
                </tr>
                <tr>
                    <td colspan="2" class="subtitle">bruto verkopen - kortingen - retouren</td>
                </tr>
            </table>
            <table class="mt">
                <tr class="item-title">
                    <td>Belastingen</td>
                    <td>{{'€ ' . number_format($taxes, 2, ',', '')}}</td>
                </tr>
            </table>
            <hr class="mb mt"/>
            <table>
                <tr class="item-title">
                    <td>Totaal verkopen</td>
                    <td>{{'€ ' . number_format($totalRevenue, 2, ',', '')}}</td>
                </tr>
                <td class="subtitle">bruto verkopen - kortingen - retouren+ belastingen</td>
            </table>
        </div>
        <div class="mt-2">
            <div>
                <span class="title">Belastingen</span>
            </div>
            <hr/>
            @foreach($vatPercentages as $vatPercentage => $amount)
                <table class="@if(!$loop->first) mt @endif">
                    <tr class="item-title">
                        <td>BTW {{ $vatPercentage }}%</td>
                        <td>{{'€ ' . number_format($amount, 2, ',', '')}}</td>
                    </tr>
                    <tr>
                        <td colspan="2" class="subtitle">
                            over {{ '€ ' . number_format($amount / $vatPercentage * 100, 2, ',', '') }}</td>
                    </tr>
                </table>
            @endforeach
            <hr class="mb mt"/>
            <table>
                <tr class="item-title">
                    <td>Totaal belastingen</td>
                    <td>{{'€ ' . number_format($taxes, 2, ',', '')}}</td>
                </tr>
            </table>
        </div>
        <div class="mt-2">
            <div>
                <span class="title">Transacties</span>
            </div>
            <hr/>
            @foreach($transactions as $transaction)
                <table class="@if(!$loop->first) mt @endif">
                    <tr class="item-title">
                        <td>{{ $transaction['name'] }}</td>
                        <td>{{'€ ' . number_format($transaction['amount'], 2, ',', '')}}</td>
                    </tr>
                    <tr>
                        <td class="subtitle">{{ $transaction['transactions'] }} transacties</td>
                    </tr>
                </table>
            @endforeach
            <hr class="mb mt"/>
            <table>
                <tr class="item-title">
                    <td>Totaal transacties</td>
                    <td>{{'€ ' . number_format(collect($transactions)->sum('amount'), 2, ',', '')}}</td>
                </tr>
            </table>
        </div>
    </div>
</div>
</body>
</html>
