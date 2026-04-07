<!DOCTYPE html>
<html lang="nl">
<head>
    <meta http-equiv="Content-Type" content="text/html; charset=UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $siteName }}</title>
    <style>
        body { margin: 0; padding: 0; background-color: #f4f4f4; font-family: Arial, sans-serif; }
        table { border-collapse: collapse; }
        img { border: 0; display: block; }
        .btn { display: inline-block; padding: 14px 32px; background-color: #1a1a1a; color: #ffffff !important; text-decoration: none; border-radius: 4px; font-size: 16px; font-weight: bold; }
        .stars { color: #f5a623; font-size: 20px; letter-spacing: 2px; }
        p { margin: 0 0 12px; }
    </style>
</head>
<body>
<table width="100%" bgcolor="#f4f4f4" cellpadding="0" cellspacing="0">
    <tr>
        <td align="center" style="padding: 30px 15px;">
            <table width="600" bgcolor="#ffffff" cellpadding="0" cellspacing="0" style="border-radius: 8px; overflow: hidden; max-width: 600px;">

                {{-- Header / logo --}}
                <tr>
                    <td align="center" bgcolor="#1a1a1a" style="padding: 24px;">
                        @if($logo)
                            @php($logoUrl = mediaHelper()->getSingleMedia($logo, ['fit' => [200, 60]])->url ?? '')
                            @if($logoUrl)
                                <img src="{{ $logoUrl }}" alt="{{ $siteName }}" height="50">
                            @else
                                <span style="color: #ffffff; font-size: 22px; font-weight: bold;">{{ $siteName }}</span>
                            @endif
                        @else
                            <span style="color: #ffffff; font-size: 22px; font-weight: bold;">{{ $siteName }}</span>
                        @endif
                    </td>
                </tr>

                {{-- Hero product image --}}
                @php
                    $firstItem = $cart->items->first();
                    $firstProduct = $firstItem?->product;
                    $heroImageId = $firstProduct?->firstImage ?? $firstProduct?->productGroup?->firstImage ?? null;
                    $heroImageUrl = $heroImageId ? (mediaHelper()->getSingleMedia($heroImageId, ['fit' => [560, 300]])->url ?? null) : null;
                @endphp
                @if($heroImageUrl)
                    <tr>
                        <td>
                            <img src="{{ $heroImageUrl }}" width="600" alt="{{ $firstProduct?->name ?? '' }}" style="width: 100%; max-width: 600px;">
                        </td>
                    </tr>
                @endif

                {{-- Intro text (stored HTML from flow step) --}}
                @if($introText)
                    <tr>
                        <td style="padding: 36px 40px 8px; font-size: 16px; color: #333333; line-height: 1.7;">
                            {!! $introText !!}
                        </td>
                    </tr>
                @endif

                {{-- Review --}}
                @if($review)
                    <tr>
                        <td style="padding: 8px 40px 24px;">
                            <table width="100%" cellpadding="0" cellspacing="0" bgcolor="#f9f9f9" style="border-left: 4px solid #1a1a1a; border-radius: 4px;">
                                <tr>
                                    <td style="padding: 18px 22px;">
                                        <div class="stars">
                                            @for($i = 1; $i <= 5; $i++){{ $i <= $review->stars ? '★' : '☆' }}@endfor
                                        </div>
                                        <p style="margin: 10px 0 8px; font-size: 15px; color: #333333; font-style: italic; line-height: 1.6;">
                                            "{{ $review->review }}"
                                        </p>
                                        <p style="margin: 0; font-size: 13px; color: #999999; font-weight: bold;">
                                            &mdash; {{ $review->name ?? 'Klant' }}
                                        </p>
                                    </td>
                                </tr>
                            </table>
                        </td>
                    </tr>
                @endif

                {{-- Discount code --}}
                @if($discountCode)
                    <tr>
                        <td align="center" style="padding: 8px 40px 24px;">
                            <table cellpadding="0" cellspacing="0" style="border: 2px dashed #1a1a1a; border-radius: 8px; width: 100%;">
                                <tr>
                                    <td style="padding: 20px 32px; text-align: center;">
                                        <p style="margin: 0 0 6px; font-size: 12px; color: #888888; text-transform: uppercase; letter-spacing: 1px;">Jouw kortingscode</p>
                                        <p style="margin: 0 0 8px; font-size: 28px; font-weight: bold; color: #1a1a1a; letter-spacing: 3px;">{{ $discountCode->code }}</p>
                                        <p style="margin: 0; font-size: 14px; color: #555555;">
                                            @if($discountCode->discount_amount > 0)
                                                &euro; {{ number_format($discountCode->discount_amount, 2, ',', '.') }} korting
                                            @elseif($discountCode->discount_percentage > 0)
                                                {{ $discountCode->discount_percentage }}% korting
                                            @endif
                                            &nbsp;&bull;&nbsp; Geldig t/m {{ $discountCode->end_date?->format('d-m-Y') }}
                                        </p>
                                    </td>
                                </tr>
                            </table>
                        </td>
                    </tr>
                @endif

                {{-- Product list --}}
                @if($step->show_products)
                    @foreach($cart->items->take(4) as $item)
                        @php
                            $product = $item->product;
                            $imgId = $product?->firstImage ?? $product?->productGroup?->firstImage ?? null;
                            $imgUrl = $imgId ? (mediaHelper()->getSingleMedia($imgId, ['fit' => [70, 70]])->url ?? null) : null;
                        @endphp
                        <tr>
                            <td style="padding: 0 40px 10px;">
                                <table width="100%" cellpadding="0" cellspacing="0" style="border: 1px solid #eeeeee; border-radius: 6px;">
                                    <tr>
                                        @if($imgUrl)
                                            <td width="80" style="padding: 10px;">
                                                <img src="{{ $imgUrl }}" width="70" height="70" alt="{{ $product?->name }}" style="border-radius: 4px; object-fit: cover;">
                                            </td>
                                        @endif
                                        <td style="padding: 10px 12px;">
                                            <p style="margin: 0 0 3px; font-size: 14px; font-weight: bold; color: #1a1a1a;">{{ $product?->name ?? 'Product' }}</p>
                                            <p style="margin: 0; font-size: 13px; color: #999999;">Aantal: {{ $item->quantity }}</p>
                                        </td>
                                        <td align="right" style="padding: 10px 16px; font-size: 14px; font-weight: bold; color: #1a1a1a; white-space: nowrap;">
                                            &euro; {{ number_format($item->unit_price * $item->quantity, 2, ',', '.') }}
                                        </td>
                                    </tr>
                                </table>
                            </td>
                        </tr>
                    @endforeach
                    @if($cart->items->count() > 4)
                        <tr>
                            <td style="padding: 0 40px 10px; font-size: 13px; color: #aaaaaa;">
                                + {{ $cart->items->count() - 4 }} ander(e) product(en)
                            </td>
                        </tr>
                    @endif
                @endif

                {{-- CTA button --}}
                <tr>
                    <td align="center" style="padding: 28px 40px 40px;">
                        <a href="{{ $checkoutUrl }}" class="btn">{{ $step->button_label }}</a>
                    </td>
                </tr>

                {{-- Footer --}}
                <tr>
                    <td bgcolor="#f9f9f9" style="padding: 18px 40px; border-top: 1px solid #eeeeee;">
                        <p style="margin: 0; font-size: 12px; color: #bbbbbb; text-align: center; line-height: 1.6;">
                            Je ontvangt deze email omdat je een winkelwagen hebt achtergelaten op {{ $siteName }}.
                            @if($discountCode) De kortingscode is eenmalig te gebruiken. @endif
                        </p>
                    </td>
                </tr>

            </table>
        </td>
    </tr>
</table>
</body>
</html>
