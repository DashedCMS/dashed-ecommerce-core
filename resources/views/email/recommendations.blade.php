@props([
    'products' => collect(),
    'placement' => 'email_order_handled',
    'heading' => 'Misschien vind je dit ook leuk',
])

@php
    $items = collect($products);
@endphp

@if($items->isNotEmpty())
<table role="presentation" cellpadding="0" cellspacing="0" border="0" width="100%" style="margin-top:24px;">
    <tr>
        <td style="padding:0 16px 8px 16px;font-family:Helvetica,Arial,sans-serif;font-size:16px;font-weight:bold;color:#111;">
            {{ $heading }}
        </td>
    </tr>
    <tr>
        <td style="padding:0 8px;">
            <table role="presentation" cellpadding="0" cellspacing="0" border="0" width="100%">
                <tr>
                    @foreach($items as $product)
                        <td valign="top" width="{{ floor(100 / max(1, $items->count())) }}%" style="padding:8px;font-family:Helvetica,Arial,sans-serif;">
                            <a href="{{ method_exists($product, 'getUrl') ? $product->getUrl() : '#' }}" style="text-decoration:none;color:inherit;">
                                @if(method_exists($product, 'firstImageUrl') && $product->firstImageUrl())
                                    <img src="{{ $product->firstImageUrl() }}" alt="{{ $product->name }}" width="100%" style="display:block;border:0;border-radius:6px;margin-bottom:8px;" />
                                @endif
                                <div style="font-size:14px;line-height:1.4;color:#111;">{{ $product->name }}</div>
                                @if(isset($product->current_price))
                                    <div style="font-size:13px;color:#555;margin-top:4px;">€ {{ number_format((float) $product->current_price, 2, ',', '.') }}</div>
                                @endif
                            </a>
                        </td>
                    @endforeach
                </tr>
            </table>
        </td>
    </tr>
</table>
@endif
