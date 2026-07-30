@php use Dashed\DashedEcommerceCore\Classes\CurrencyHelper; @endphp

<p>Beste {{ $order->first_name ?: 'klant' }},</p>

<p>Je bestelling is aangepast. Hieronder staat de nieuwe inhoud.</p>

@if($note)
    <p>{{ $note }}</p>
@endif

<table cellpadding="6" cellspacing="0" border="0">
    @foreach($order->orderProducts as $orderProduct)
        <tr>
            <td>{{ $orderProduct->quantity }}x {{ $orderProduct->name }}</td>
            <td>{{ CurrencyHelper::formatPrice($orderProduct->price) }}</td>
        </tr>
    @endforeach
    <tr>
        <td><strong>Totaal</strong></td>
        <td><strong>{{ CurrencyHelper::formatPrice($order->total) }}</strong></td>
    </tr>
</table>

@if($outstandingAmount > 0)
    <p>Er staat nog {{ CurrencyHelper::formatPrice($outstandingAmount) }} open. Je kunt dat bedrag hier voldoen:</p>
    <p><a href="{{ $paymentUrl }}">Betaal {{ CurrencyHelper::formatPrice($outstandingAmount) }}</a></p>
@elseif($overpaidAmount > 0)
    <p>Je hebt {{ CurrencyHelper::formatPrice($overpaidAmount) }} te veel betaald. Dat bedrag storten wij aan je terug.</p>
@else
    <p>Er hoeft niets bijbetaald te worden.</p>
@endif

<p>Met vriendelijke groet</p>
