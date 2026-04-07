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
        .btn { display: inline-block; padding: 14px 28px; background-color: #1a1a1a; color: #ffffff !important; text-decoration: none; border-radius: 4px; font-size: 16px; font-weight: bold; }
    </style>
</head>
<body>
<table width="100%" bgcolor="#f4f4f4" cellpadding="0" cellspacing="0">
    <tr>
        <td align="center" style="padding: 30px 15px;">
            <table width="600" bgcolor="#ffffff" cellpadding="0" cellspacing="0" style="border-radius: 8px; overflow: hidden; max-width: 600px;">

                {{-- Header --}}
                <tr>
                    <td align="center" bgcolor="#1a1a1a" style="padding: 24px;">
                        @if($logo)
                            <img src="{{ mediaHelper()->getSingleMedia($logo, ['fit' => [200, 60]])->url ?? '' }}" alt="{{ $siteName }}" height="50">
                        @else
                            <span style="color: #ffffff; font-size: 22px; font-weight: bold;">{{ $siteName }}</span>
                        @endif
                    </td>
                </tr>

                {{-- Hero product --}}
                @php
                    $firstItem = $cart->items->first();
                    $product = $firstItem?->product;
                    $imageId = $product?->firstImage ?? ($product?->productGroup?->firstImage ?? null);
                    $imageUrl = $imageId ? (mediaHelper()->getSingleMedia($imageId, ['fit' => [560, 300]])->url ?? null) : null;
                @endphp

                @if($imageUrl)
                    <tr>
                        <td>
                            <img src="{{ $imageUrl }}" width="600" alt="{{ $product?->name ?? '' }}" style="width: 100%; max-width: 600px;">
                        </td>
                    </tr>
                @endif

                {{-- Body --}}
                <tr>
                    <td style="padding: 40px 40px 20px;">
                        <h1 style="margin: 0 0 16px; font-size: 24px; color: #1a1a1a;">Speciaal voor jou</h1>
                        <p style="margin: 0 0 24px; font-size: 16px; color: #555555; line-height: 1.6;">
                            We willen je graag een handje helpen. Gebruik de code hieronder bij je bestelling:
                        </p>
                    </td>
                </tr>

                {{-- Discount code --}}
                @if($discountCode)
                    <tr>
                        <td align="center" style="padding: 0 40px 32px;">
                            <table cellpadding="0" cellspacing="0" style="border: 2px dashed #1a1a1a; border-radius: 8px;">
                                <tr>
                                    <td style="padding: 20px 40px; text-align: center;">
                                        <p style="margin: 0 0 8px; font-size: 13px; color: #888888; text-transform: uppercase; letter-spacing: 1px;">Jouw kortingscode</p>
                                        <p style="margin: 0 0 8px; font-size: 28px; font-weight: bold; color: #1a1a1a; letter-spacing: 3px;">{{ $discountCode->code }}</p>
                                        <p style="margin: 0; font-size: 14px; color: #555555;">
                                            @if($discountCode->discount_amount > 0)
                                                &euro; {{ number_format($discountCode->discount_amount, 2, ',', '.') }} korting
                                            @elseif($discountCode->discount_percentage > 0)
                                                {{ $discountCode->discount_percentage }}% korting
                                            @endif
                                            &nbsp;&bull;&nbsp;
                                            Geldig t/m {{ $discountCode->end_date?->format('d-m-Y') }}
                                        </p>
                                    </td>
                                </tr>
                            </table>
                        </td>
                    </tr>
                @endif

                {{-- Cart summary --}}
                @foreach($cart->items->take(3) as $item)
                    @php
                        $itemProduct = $item->product;
                        $itemImageId = $itemProduct?->firstImage ?? null;
                        $itemImageUrl = $itemImageId ? (mediaHelper()->getSingleMedia($itemImageId, ['fit' => [60, 60]])->url ?? null) : null;
                    @endphp
                    <tr>
                        <td style="padding: 0 40px 10px;">
                            <table width="100%" cellpadding="0" cellspacing="0" style="border: 1px solid #eeeeee; border-radius: 6px;">
                                <tr>
                                    @if($itemImageUrl)
                                        <td width="70" style="padding: 10px;">
                                            <img src="{{ $itemImageUrl }}" width="60" height="60" alt="{{ $itemProduct?->name }}" style="border-radius: 3px; object-fit: cover;">
                                        </td>
                                    @endif
                                    <td style="padding: 10px;">
                                        <p style="margin: 0; font-size: 14px; font-weight: bold; color: #1a1a1a;">{{ $itemProduct?->name ?? 'Product' }}</p>
                                        <p style="margin: 2px 0 0; font-size: 13px; color: #888888;">Aantal: {{ $item->quantity }}</p>
                                    </td>
                                    <td align="right" style="padding: 10px 14px; font-size: 14px; color: #1a1a1a; white-space: nowrap;">
                                        &euro; {{ number_format($item->unit_price * $item->quantity, 2, ',', '.') }}
                                    </td>
                                </tr>
                            </table>
                        </td>
                    </tr>
                @endforeach

                {{-- CTA --}}
                <tr>
                    <td align="center" style="padding: 24px 40px 40px;">
                        <a href="{{ $checkoutUrl }}" class="btn">
                            @if($discountCode)
                                Bestel met korting
                            @else
                                Bestel nu
                            @endif
                        </a>
                    </td>
                </tr>

                {{-- Footer --}}
                <tr>
                    <td bgcolor="#f9f9f9" style="padding: 20px 40px; border-top: 1px solid #eeeeee;">
                        <p style="margin: 0; font-size: 13px; color: #aaaaaa; text-align: center;">
                            Je ontvangt deze email omdat je een winkelwagen hebt achtergelaten op {{ $siteName }}.
                            De kortingscode is eenmalig te gebruiken.
                        </p>
                    </td>
                </tr>

            </table>
        </td>
    </tr>
</table>
</body>
</html>
