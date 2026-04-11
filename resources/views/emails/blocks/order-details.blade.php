<tr><td style="padding:16px 24px; font-family: Arial, sans-serif; font-size:14px; color:#374151;">
    <table role="presentation" width="100%" cellpadding="0" cellspacing="0">
        <tr>
            <td style="padding:4px 0;"><strong style="color:#6b7280;">Factuurnummer:</strong> {{ $order->invoice_id }}</td>
        </tr>
        <tr>
            <td style="padding:4px 0;"><strong style="color:#6b7280;">Datum:</strong> {{ $order->created_at->format('d-m-Y H:i') }}</td>
        </tr>
        @if($order->phone_number)
            <tr>
                <td style="padding:4px 0;"><strong style="color:#6b7280;">Telefoon:</strong> {{ $order->phone_number }}</td>
            </tr>
        @endif
        <tr>
            <td style="padding:4px 0;"><strong style="color:#6b7280;">E-mail:</strong> {{ $order->email }}</td>
        </tr>
    </table>
</td></tr>
