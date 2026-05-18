<tr><td style="padding:16px 24px; font-family: Arial, sans-serif; font-size:14px; color:#374151;">
    <div style="font-size:15px; font-weight:bold; color:#111827; margin-bottom:8px;">Track &amp; trace</div>
    <table cellpadding="0" cellspacing="0" border="0" width="100%" style="border-collapse:collapse;">
        @foreach ($entries as $entry)
            <tr>
                <td style="padding:8px 0; border-top:1px solid #e5e7eb; vertical-align:top;">
                    @if ($entry['supplier'])
                        <div style="font-weight:bold; color:#111827;">{{ $entry['supplier'] }}</div>
                    @endif
                    @if ($entry['code'])
                        <div style="color:#6b7280; font-size:13px;">Code: {{ $entry['code'] }}</div>
                    @endif
                    @if ($entry['expected'])
                        <div style="color:#6b7280; font-size:13px;">Verwacht: {{ $entry['expected'] }}</div>
                    @endif
                </td>
                <td align="right" style="padding:8px 0; border-top:1px solid #e5e7eb; vertical-align:top;">
                    @if ($entry['url'])
                        <a href="{{ $entry['url'] }}" style="color:#2563eb; text-decoration:underline;">Volg pakket</a>
                    @endif
                </td>
            </tr>
        @endforeach
    </table>
</td></tr>
