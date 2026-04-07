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

                {{-- Body --}}
                <tr>
                    <td style="padding: 40px 40px 20px;">
                        <h1 style="margin: 0 0 16px; font-size: 24px; color: #1a1a1a;">Je hebt iets achtergelaten</h1>
                        <p style="margin: 0 0 24px; font-size: 16px; color: #555555; line-height: 1.6;">
                            Je winkelwagen staat nog voor je klaar. Kom terug en rond je bestelling af!
                        </p>
                    </td>
                </tr>

                {{-- Products --}}
                @foreach($cart->items->take(3) as $item)
                    @php
                        $product = $item->product;
                        $imageId = $product?->firstImage ?? ($product?->productGroup?->firstImage ?? null);
                        $imageUrl = $imageId ? (mediaHelper()->getSingleMedia($imageId, ['fit' => [80, 80]])->url ?? null) : null;
                        $productName = $product?->name ?? 'Product';
                    @endphp
                    <tr>
                        <td style="padding: 0 40px 16px;">
                            <table width="100%" cellpadding="0" cellspacing="0" style="border: 1px solid #eeeeee; border-radius: 6px;">
                                <tr>
                                    @if($imageUrl)
                                        <td width="90" style="padding: 12px;">
                                            <img src="{{ $imageUrl }}" width="80" height="80" alt="{{ $productName }}" style="border-radius: 4px; object-fit: cover;">
                                        </td>
                                    @endif
                                    <td style="padding: 12px;">
                                        <p style="margin: 0 0 4px; font-size: 15px; font-weight: bold; color: #1a1a1a;">{{ $productName }}</p>
                                        <p style="margin: 0; font-size: 14px; color: #888888;">Aantal: {{ $item->quantity }}</p>
                                    </td>
                                    <td align="right" style="padding: 12px 16px; font-size: 15px; font-weight: bold; color: #1a1a1a; white-space: nowrap;">
                                        &euro; {{ number_format($item->unit_price * $item->quantity, 2, ',', '.') }}
                                    </td>
                                </tr>
                            </table>
                        </td>
                    </tr>
                @endforeach

                @if($cart->items->count() > 3)
                    <tr>
                        <td style="padding: 0 40px 16px; font-size: 14px; color: #888888;">
                            + {{ $cart->items->count() - 3 }} ander(e) product(en)
                        </td>
                    </tr>
                @endif

                {{-- CTA --}}
                <tr>
                    <td align="center" style="padding: 24px 40px 40px;">
                        <a href="{{ $checkoutUrl }}" class="btn">Ga verder met bestellen</a>
                    </td>
                </tr>

                {{-- Footer --}}
                <tr>
                    <td bgcolor="#f9f9f9" style="padding: 20px 40px; border-top: 1px solid #eeeeee;">
                        <p style="margin: 0; font-size: 13px; color: #aaaaaa; text-align: center;">
                            Je ontvangt deze email omdat je een winkelwagen hebt achtergelaten op {{ $siteName }}.
                        </p>
                    </td>
                </tr>

            </table>
        </td>
    </tr>
</table>
</body>
</html>
