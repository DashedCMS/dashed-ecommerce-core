@php $perRij = $columns; @endphp
<tr><td style="padding:16px 24px;">
    <table role="presentation" width="100%" cellpadding="0" cellspacing="0">
        @foreach(array_chunk($products, $perRij) as $rij)
            <tr>
                @foreach($rij as $product)
                    <td width="{{ (int) (100 / $perRij) }}%" valign="top" style="padding:8px;font-family:Arial,Helvetica,sans-serif;">
                        @if($product['image'])
                            <a href="{{ $product['url'] }}"><img src="{{ $product['image'] }}" alt="{{ $product['name'] }}" style="width:100%;display:block;border:0;border-radius:6px;"></a>
                        @endif
                        <a href="{{ $product['url'] }}" style="display:block;margin-top:8px;font-size:14px;color:#18181b;text-decoration:none;">{{ $product['name'] }}</a>
                        <div style="font-size:14px;font-weight:bold;color:#18181b;margin-top:4px;">&euro; {{ number_format((float) $product['price'], 2, ',', '.') }}</div>
                        <a href="{{ $product['url'] }}" style="display:inline-block;margin-top:8px;padding:8px 14px;background:{{ $primaryColor }};color:{{ $textColor }};text-decoration:none;border-radius:6px;font-size:13px;">Bekijken</a>
                    </td>
                @endforeach
                @for($i = count($rij); $i < $perRij; $i++)
                    <td width="{{ (int) (100 / $perRij) }}%">&nbsp;</td>
                @endfor
            </tr>
        @endforeach
    </table>
</td></tr>
