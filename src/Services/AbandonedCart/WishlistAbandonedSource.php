<?php

namespace Dashed\DashedEcommerceCore\Services\AbandonedCart;

use Illuminate\Support\Collection;
use Dashed\DashedEcommerceCore\Models\Wishlist;
use Dashed\DashedEcommerceCore\Models\WishlistItem;
use Dashed\DashedEcommerceCore\Controllers\Frontend\WishlistController;

class WishlistAbandonedSource implements AbandonedCartSource
{
    private ?Collection $items = null;

    public function __construct(private readonly Wishlist $wishlist)
    {
    }

    public function email(): ?string
    {
        return $this->wishlist->email;
    }

    public function siteId(): ?int
    {
        return null;
    }

    public function locale(): ?string
    {
        return $this->wishlist->locale;
    }

    public function items(): Collection
    {
        return $this->items ??= $this->wishlist->publicItems()->map(fn (WishlistItem $item) => [
            'name' => $item->product->name,
            'quantity' => 1,
            'price' => (int) round(((float) ($item->product->currentPrice ?? 0)) * 100),
            'image_id' => $item->product->firstImage ?? $item->product->productGroup?->firstImage ?? null,
            'product_url' => $item->product->getUrl(),
        ])->values();
    }

    public function total(): int
    {
        return (int) $this->items()->sum('price');
    }

    public function currency(): string
    {
        return 'EUR';
    }

    public function resumeUrl(): string
    {
        return WishlistController::restoreUrl($this->wishlist);
    }

    public function variables(): array
    {
        return [':wishlistCount:' => (string) $this->items()->count()];
    }

    public function isValid(): bool
    {
        return (bool) $this->wishlist->email && $this->items()->isNotEmpty();
    }
}
