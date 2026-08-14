<tr><td align="center" style="padding:16px 24px;">
    <table role="presentation" cellpadding="0" cellspacing="0" style="border:2px dashed {{ $primaryColor }};border-radius:8px;">
        <tr><td align="center" style="padding:16px 32px;font-family:Arial,Helvetica,sans-serif;">
            <div style="font-size:24px;font-weight:bold;letter-spacing:2px;color:{{ $primaryColor }};">{{ $code }}</div>
            @if($description)
                <div style="margin-top:6px;font-size:13px;color:#52525b;">{{ $description }}</div>
            @endif
            @if($validUntil)
                <div style="margin-top:4px;font-size:12px;color:#9ca3af;">Geldig tot {{ $validUntil }}</div>
            @endif
        </td></tr>
    </table>
</td></tr>
