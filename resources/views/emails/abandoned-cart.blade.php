@php
    use Dashed\DashedTranslations\Models\Translation;
    $primaryColor = Translation::get('primary-color-code', 'emails', '#A0131C');
@endphp
<!DOCTYPE html>
<html lang="nl">
<head>
    <meta http-equiv="Content-Type" content="text/html; charset=UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ $siteName }}</title>
    <!--[if mso]>
    <noscript>
        <xml>
            <o:OfficeDocumentSettings>
                <o:PixelsPerInch>96</o:PixelsPerInch>
            </o:OfficeDocumentSettings>
        </xml>
    </noscript>
    <![endif]-->
    <style>
        body { margin: 0; padding: 0; -webkit-text-size-adjust: 100%; -ms-text-size-adjust: 100%; }
        table { border-collapse: collapse; mso-table-lspace: 0pt; mso-table-rspace: 0pt; }
        img { border: 0; display: block; outline: none; text-decoration: none; -ms-interpolation-mode: bicubic; }
        p { margin: 0; padding: 0; }
        a { color: {{ $primaryColor }}; }
        @media only screen and (max-width: 620px) {
            .container { width: 100% !important; }
            .mobile-padding { padding-left: 24px !important; padding-right: 24px !important; }
            .product-img { width: 56px !important; height: 56px !important; }
        }
    </style>
</head>
<body style="margin: 0; padding: 0; background-color: #f8fafc; font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Helvetica, Arial, sans-serif;">

<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background-color: #f8fafc;">
    <tr>
        <td align="center" style="padding: 40px 16px;">

            {{-- Main container --}}
            <table role="presentation" class="container" width="560" cellpadding="0" cellspacing="0" style="max-width: 560px; width: 100%;">

                {{-- Logo --}}
                <tr>
                    <td align="center" style="padding-bottom: 32px;">
                        @if($logo)
                            @if($logoUrl = mediaHelper()->getSingleMedia($logo)->url ?? '')
                                <img src="{{ $logoUrl }}" alt="{{ $siteName }}" style="max-width: 100px; max-height: 100px; width: auto; height: auto;">
                            @else
                                <span style="font-size: 24px; font-weight: 700; color: #0f172a; letter-spacing: -0.5px;">{{ $siteName }}</span>
                            @endif
                        @else
                            <span style="font-size: 24px; font-weight: 700; color: #0f172a; letter-spacing: -0.5px;">{{ $siteName }}</span>
                        @endif
                    </td>
                </tr>

                {{-- Card --}}
                <tr>
                    <td>
                        <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background-color: #ffffff; border-radius: 16px; overflow: hidden; box-shadow: 0 1px 3px rgba(0,0,0,0.08), 0 1px 2px rgba(0,0,0,0.06);">

                            {{-- Hero product image --}}
                            @php
                                $firstItem = $items->first();
                                $heroImageId = $firstItem['image_id'] ?? null;
                                $heroImageUrl = $heroImageId ? (mediaHelper()->getSingleMedia($heroImageId, ['fit' => [560, 320]])->url ?? null) : null;
                            @endphp
                            @if($heroImageUrl)
                                <tr>
                                    <td>
                                        <a href="{{ $productUrl }}" style="display: block;">
                                            <img src="{{ $heroImageUrl }}" width="560" alt="{{ $firstItem['name'] ?? '' }}" style="width: 100%; max-width: 560px; height: auto; object-fit: cover;">
                                        </a>
                                    </td>
                                </tr>
                            @endif

                            {{-- Blocks --}}
                            @foreach($blocks as $block)

                                {{-- Text block --}}
                                @if($block['type'] === 'text' && !empty($block['data']['content']))
                                    <tr>
                                        <td class="mobile-padding" style="padding: 32px 40px 16px; font-size: 16px; line-height: 1.75; color: #334155;">
                                            {!! $block['data']['content'] !!}
                                        </td>
                                    </tr>
                                @endif

                                {{-- Single product block --}}
                                @if($block['type'] === 'product')
                                    @php
                                        $prodItem = $items->first();
                                        $prodImgId = $prodItem['image_id'] ?? null;
                                        $prodImgUrl = $prodImgId ? (mediaHelper()->getSingleMedia($prodImgId, ['fit' => [80, 80]])->url ?? null) : null;
                                    @endphp
                                    @if($prodItem)
                                        <tr>
                                            <td class="mobile-padding" style="padding: 12px 40px;">
                                                <a href="{{ $productUrl }}" style="text-decoration: none; color: inherit; display: block;">
                                                    <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background-color: #f8fafc; border-radius: 12px;">
                                                        <tr>
                                                            @if($prodImgUrl)
                                                                <td width="72" style="padding: 16px;">
                                                                    <img src="{{ $prodImgUrl }}" class="product-img" width="64" height="64" alt="{{ $prodItem['name'] }}" style="border-radius: 8px; object-fit: cover; width: 64px; height: 64px;">
                                                                </td>
                                                            @endif
                                                            <td style="padding: 16px 16px 16px {{ $prodImgUrl ? '0' : '16px' }};">
                                                                <p style="margin: 0 0 4px; font-size: 15px; font-weight: 600; color: #0f172a;">{{ $prodItem['name'] }}</p>
                                                                <p style="margin: 0; font-size: 13px; color: #64748b;">Aantal: {{ $prodItem['quantity'] }}</p>
                                                            </td>
                                                            <td align="right" style="padding: 16px; font-size: 15px; font-weight: 700; color: #0f172a; white-space: nowrap;">
                                                                &euro; {{ number_format(($prodItem['price'] * $prodItem['quantity']) / 100, 2, ',', '.') }}
                                                            </td>
                                                        </tr>
                                                    </table>
                                                </a>
                                            </td>
                                        </tr>
                                    @endif
                                @endif

                                {{-- All products block --}}
                                @if($block['type'] === 'products')
                                    @foreach($items->take(4) as $item)
                                        @php
                                            $imgId = $item['image_id'] ?? null;
                                            $imgUrl = $imgId ? (mediaHelper()->getSingleMedia($imgId, ['fit' => [80, 80]])->url ?? null) : null;
                                        @endphp
                                        <tr>
                                            <td class="mobile-padding" style="padding: 6px 40px;">
                                                <a href="{{ $productUrl }}" style="text-decoration: none; color: inherit; display: block;">
                                                    <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background-color: #f8fafc; border-radius: 12px;">
                                                        <tr>
                                                            @if($imgUrl)
                                                                <td width="72" style="padding: 12px;">
                                                                    <img src="{{ $imgUrl }}" class="product-img" width="56" height="56" alt="{{ $item['name'] }}" style="border-radius: 8px; object-fit: cover; width: 56px; height: 56px;">
                                                                </td>
                                                            @endif
                                                            <td style="padding: 12px 12px 12px {{ $imgUrl ? '0' : '12px' }};">
                                                                <p style="margin: 0 0 2px; font-size: 14px; font-weight: 600; color: #0f172a;">{{ $item['name'] ?: 'Product' }}</p>
                                                                <p style="margin: 0; font-size: 13px; color: #64748b;">Aantal: {{ $item['quantity'] }}</p>
                                                            </td>
                                                            <td align="right" style="padding: 12px 16px; font-size: 14px; font-weight: 700; color: #0f172a; white-space: nowrap;">
                                                                &euro; {{ number_format(($item['price'] * $item['quantity']) / 100, 2, ',', '.') }}
                                                            </td>
                                                        </tr>
                                                    </table>
                                                </a>
                                            </td>
                                        </tr>
                                    @endforeach
                                    @if($items->count() > 4)
                                        <tr>
                                            <td class="mobile-padding" style="padding: 4px 40px 8px; font-size: 13px; color: #94a3b8; text-align: center;">
                                                + {{ $items->count() - 4 }} ander(e) product(en)
                                            </td>
                                        </tr>
                                    @endif
                                @endif

                                {{-- Review block --}}
                                @if($block['type'] === 'review' && $review)
                                    <tr>
                                        <td class="mobile-padding" style="padding: 12px 40px;">
                                            <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="border-left: 3px solid {{ $primaryColor }}; background-color: #fafafa; border-radius: 0 12px 12px 0;">
                                                <tr>
                                                    <td style="padding: 20px 24px;">
                                                        <p style="margin: 0 0 8px; font-size: 18px; letter-spacing: 2px; color: #f59e0b;">
                                                            @for($i = 1; $i <= 5; $i++){{ $i <= $review->stars ? '★' : '☆' }}@endfor
                                                        </p>
                                                        <p style="margin: 0 0 10px; font-size: 15px; color: #334155; font-style: italic; line-height: 1.6;">
                                                            &ldquo;{{ $review->review }}&rdquo;
                                                        </p>
                                                        <p style="margin: 0; font-size: 13px; color: #94a3b8; font-weight: 600;">
                                                            &mdash; {{ $review->name ?? 'Klant' }}
                                                        </p>
                                                    </td>
                                                </tr>
                                            </table>
                                        </td>
                                    </tr>
                                @endif

                                {{-- Discount block --}}
                                @if($block['type'] === 'discount' && $discountCode)
                                    <tr>
                                        <td class="mobile-padding" style="padding: 12px 40px;">
                                            <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="border: 2px dashed {{ $primaryColor }}; border-radius: 12px;">
                                                <tr>
                                                    <td style="padding: 24px; text-align: center;">
                                                        <p style="margin: 0 0 8px; font-size: 11px; font-weight: 700; color: #94a3b8; text-transform: uppercase; letter-spacing: 2px;">Jouw kortingscode</p>
                                                        <p style="margin: 0 0 12px; font-size: 28px; font-weight: 800; color: {{ $primaryColor }}; letter-spacing: 3px; font-family: monospace, monospace;">{{ $discountCode->code }}</p>
                                                        <p style="margin: 0; font-size: 14px; color: #64748b;">
                                                            @if($discountCode->discount_amount > 0)
                                                                <strong>&euro; {{ number_format($discountCode->discount_amount, 2, ',', '.') }} korting</strong>
                                                            @elseif($discountCode->discount_percentage > 0)
                                                                <strong>{{ $discountCode->discount_percentage }}% korting</strong>
                                                            @endif
                                                            &nbsp;&middot;&nbsp; Geldig t/m {{ $discountCode->end_date?->format('d-m-Y') }}
                                                        </p>
                                                    </td>
                                                </tr>
                                            </table>
                                        </td>
                                    </tr>
                                @endif

                                {{-- Divider block --}}
                                @if($block['type'] === 'divider')
                                    <tr>
                                        <td class="mobile-padding" style="padding: 16px 40px;">
                                            <table role="presentation" width="100%" cellpadding="0" cellspacing="0">
                                                <tr>
                                                    <td style="border-top: 1px solid #e2e8f0; font-size: 0; line-height: 0;">&nbsp;</td>
                                                </tr>
                                            </table>
                                        </td>
                                    </tr>
                                @endif

                                {{-- USP block --}}
                                @if($block['type'] === 'usp' && !empty($block['data']['items']))
                                    <tr>
                                        <td class="mobile-padding" style="padding: 12px 40px;">
                                            <table role="presentation" width="100%" cellpadding="0" cellspacing="0">
                                                @foreach(explode("\n", $block['data']['items']) as $usp)
                                                    @if(trim($usp))
                                                        <tr>
                                                            <td width="28" valign="top" style="padding: 6px 0; font-size: 16px; color: {{ $primaryColor }};">&#10003;</td>
                                                            <td style="padding: 6px 0; font-size: 15px; color: #334155; line-height: 1.5;">{{ trim($usp) }}</td>
                                                        </tr>
                                                    @endif
                                                @endforeach
                                            </table>
                                        </td>
                                    </tr>
                                @endif

                                {{-- Button block --}}
                                @if($block['type'] === 'button')
                                    <tr>
                                        <td align="center" class="mobile-padding" style="padding: 28px 40px 36px;">
                                            <table role="presentation" cellpadding="0" cellspacing="0">
                                                <tr>
                                                    <td align="center" bgcolor="{{ $primaryColor }}" style="border-radius: 8px;">
                                                        <!--[if mso]>
                                                        <v:roundrect xmlns:v="urn:schemas-microsoft-com:vml" xmlns:w="urn:schemas-microsoft-com:office:word" href="{{ $checkoutUrl }}" style="height:52px;v-text-anchor:middle;width:240px;" arcsize="15%" strokecolor="{{ $primaryColor }}" fillcolor="{{ $primaryColor }}">
                                                        <w:anchorlock/>
                                                        <center style="color:#ffffff;font-family:sans-serif;font-size:16px;font-weight:bold;">{{ $block['data']['label'] ?? 'Bestel nu' }}</center>
                                                        </v:roundrect>
                                                        <![endif]-->
                                                        <!--[if !mso]><!-->
                                                        <a href="{{ $checkoutUrl }}" style="display: inline-block; padding: 14px 40px; background-color: {{ $primaryColor }}; color: #ffffff !important; text-decoration: none; border-radius: 8px; font-size: 16px; font-weight: 700; font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Helvetica, Arial, sans-serif; text-align: center; mso-padding-alt: 0;">{{ $block['data']['label'] ?? 'Bestel nu' }}</a>
                                                        <!--<![endif]-->
                                                    </td>
                                                </tr>
                                            </table>
                                        </td>
                                    </tr>
                                @endif

                            @endforeach

                        </table>
                    </td>
                </tr>

                {{-- Footer --}}
                <tr>
                    <td style="padding: 32px 24px; text-align: center;">
                        <p style="margin: 0; font-size: 13px; color: #94a3b8; line-height: 1.6;">
                            Je ontvangt deze email omdat je een winkelwagen hebt achtergelaten op {{ $siteName }}.
                            @if($discountCode) De kortingscode is eenmalig te gebruiken.@endif
                        </p>
                        @if(! empty($unsubscribeUrl))
                            <p style="margin: 12px 0 0 0; font-size: 12px; color: #94a3b8;">
                                <a href="{{ $unsubscribeUrl }}" style="color:#94a3b8; text-decoration: underline;">{{ $unsubscribeLabel ?? 'Afmelden' }}</a>
                            </p>
                        @endif
                    </td>
                </tr>

            </table>

        </td>
    </tr>
</table>

</body>
</html>
