<?php

namespace Dashed\DashedEcommerceCore\Services\AbandonedCart;

use Dashed\DashedEcommerceCore\Models\Cart;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Crypt;

class CartAbandonedSource implements AbandonedCartSource
{
    public function __construct(private readonly Cart $cart)
    {
    }

    public function email(): ?string
    {
        return $this->cart->abandoned_email;
    }

    public function siteId(): ?int
    {
        return null;
    }

    public function locale(): ?string
    {
        return $this->cart->locale;
    }

    public function items(): Collection
    {
        return $this->cart->items->map(fn ($item) => [
            'name' => $item->name ?? $item->product?->name ?? '',
            'quantity' => (int) $item->quantity,
            'price' => (int) round(((float) ($item->unit_price ?? 0)) * 100),
            'image' => $item->product?->firstImage()?->url ?? null,
            'product_url' => $item->product?->getUrl(),
        ]);
    }

    public function total(): int
    {
        return (int) round(((float) ($this->cart->total ?? 0)) * 100);
    }

    public function currency(): string
    {
        return $this->cart->currency ?? 'EUR';
    }

    public function resumeUrl(): string
    {
        $token = urlencode(Crypt::encryptString($this->cart->token));

        return url('/restore-cart') . '?cart=' . $token;
    }

    public function variables(): array
    {
        return [];
    }
}
