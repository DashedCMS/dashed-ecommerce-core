{{--
    Elk product in een eigen kaart. Zonder omhulling is een product alleen een
    cel met wat padding, en dan loopt de knop van het ene item visueel over in
    het beeld van het volgende: je ziet niet meer waar hij bij hoort.

    Een geneste tabel en geen div: Outlook rendert met Word en negeert daar de
    meeste opmaak op een div. De border-radius negeert hij ook, maar dat levert
    alleen rechte hoeken op en geen kapotte kaart.
--}}
@php $perRij = max(1, (int) $columns); @endphp
<tr><td style="padding:16px 24px;">
    <table role="presentation" width="100%" cellpadding="0" cellspacing="0">
        @foreach(array_chunk($products, $perRij) as $rij)
            <tr>
                @foreach($rij as $product)
                    <td width="{{ (int) (100 / $perRij) }}%" valign="top" style="padding:6px;">
                        <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="border:1px solid #e4e4e7;border-radius:8px;background:#ffffff;">
                            <tr>
                                <td style="padding:0;font-family:Arial,Helvetica,sans-serif;">
                                    @if($product['image'])
                                        <a href="{{ $product['url'] }}" style="display:block;">
                                            <img src="{{ $product['image'] }}" alt="{{ $product['name'] }}" style="width:100%;display:block;border:0;border-top-left-radius:8px;border-top-right-radius:8px;">
                                        </a>
                                    @endif
                                    <table role="presentation" width="100%" cellpadding="0" cellspacing="0">
                                        <tr><td style="padding:14px 14px 16px;font-family:Arial,Helvetica,sans-serif;">
                                            <a href="{{ $product['url'] }}" style="display:block;font-size:14px;line-height:1.35;color:#18181b;text-decoration:none;">{{ $product['name'] }}</a>
                                            <div style="font-size:15px;font-weight:bold;color:#18181b;margin-top:6px;">&euro; {{ number_format((float) $product['price'], 2, ',', '.') }}</div>
                                            <a href="{{ $product['url'] }}" style="display:inline-block;margin-top:12px;padding:9px 16px;background:{{ $primaryColor }};color:{{ $textColor }};text-decoration:none;border-radius:6px;font-size:13px;">Bekijken</a>
                                        </td></tr>
                                    </table>
                                </td>
                            </tr>
                        </table>
                    </td>
                @endforeach
                {{-- Een lege plek in de laatste rij blijft leeg: geen rand, dus
                     geen kaart zonder inhoud. --}}
                @for($i = count($rij); $i < $perRij; $i++)
                    <td width="{{ (int) (100 / $perRij) }}%">&nbsp;</td>
                @endfor
            </tr>
        @endforeach
    </table>
</td></tr>
