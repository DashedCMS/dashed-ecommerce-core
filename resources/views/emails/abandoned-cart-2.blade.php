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
        .stars { color: #f5a623; font-size: 20px; }
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
                            <img src="{{ $imageUrl }}" width="600" alt="{{ $productName }}" style="width: 100%; max-width: 600px;">
                        </td>
                    </tr>
                @endif

                {{-- Body --}}
                <tr>
                    <td style="padding: 40px 40px 20px;">
                        <h1 style="margin: 0 0 16px; font-size: 24px; color: #1a1a1a;">
                            Je {{ $productName }} wacht nog op je
                        </h1>
                        <p style="margin: 0 0 24px; font-size: 16px; color: #555555; line-height: 1.6;">
                            Je bent bijna klaar! Je winkelwagen staat nog voor je klaar. Andere klanten gingen je al voor — dit is wat zij ervan vinden:
                        </p>
                    </td>
                </tr>

                {{-- Review --}}
                @if($review)
                    <tr>
                        <td style="padding: 0 40px 24px;">
                            <table width="100%" cellpadding="0" cellspacing="0" bgcolor="#f9f9f9" style="border-left: 4px solid #1a1a1a; border-radius: 4px;">
                                <tr>
                                    <td style="padding: 20px 24px;">
                                        <div class="stars">
                                            @for($i = 1; $i <= 5; $i++)
                                                {{ $i <= $review->stars ? '★' : '☆' }}
                                            @endfor
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

                {{-- CTA --}}
                <tr>
                    <td align="center" style="padding: 8px 40px 40px;">
                        <a href="{{ $checkoutUrl }}" class="btn">Bestel nu</a>
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
