<tr><td style="padding:16px 24px; font-family: Arial, sans-serif; font-size:14px; color:#374151;">
    @if($showShipping)
        <div style="margin-bottom:12px;">
            <div style="font-size:15px; font-weight:bold; color:#111827; margin-bottom:4px;">Verzendmethode</div>
            <div>{{ $order->shippingMethod->name ?? 'Geen gekozen' }}</div>
        </div>
    @endif
    @if($showPayment)
        <div>
            <div style="font-size:15px; font-weight:bold; color:#111827; margin-bottom:4px;">Betaalmethode</div>
            <div>{{ $order->paymentMethod }}</div>
            @if($showInstructions && $order->paymentMethodInstructions)
                <div style="margin-top:6px; padding:10px; background:#f9fafb; border-radius:4px; font-size:13px;">
                    {!! nl2br(e($order->paymentMethodInstructions)) !!}
                </div>
            @endif
        </div>
    @endif
</td></tr>
