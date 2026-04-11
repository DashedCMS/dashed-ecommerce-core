@php
    $isInvoice = $type === 'invoice';
    $label = $isInvoice ? 'Factuuradres' : 'Verzendadres';
    $street = $isInvoice ? $order->invoice_street : $order->street;
    $houseNr = $isInvoice ? $order->invoice_house_nr : $order->house_nr;
    $city = $isInvoice ? $order->invoice_city : $order->city;
    $zipCode = $isInvoice ? $order->invoice_zip_code : $order->zip_code;
    $country = $isInvoice ? $order->invoice_country : $order->country;
@endphp
<tr><td style="padding:16px 24px; font-family: Arial, sans-serif; font-size:14px; color:#374151;">
    <div style="font-size:15px; font-weight:bold; color:#111827; margin-bottom:8px;">{{ $label }}</div>
    <div style="line-height:1.6;">
        @if($order->company_name)
            {{ $order->company_name }}<br>
        @endif
        {{ $order->first_name }} {{ $order->last_name }}<br>
        @if($order->btw_id)
            {{ $order->btw_id }}<br>
        @endif
        {{ $street }} {{ $houseNr }}<br>
        {{ $zipCode }} {{ $city }}<br>
        {{ $country }}
    </div>
</td></tr>
