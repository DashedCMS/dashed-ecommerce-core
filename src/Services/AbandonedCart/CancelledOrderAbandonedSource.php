<?php

namespace Dashed\DashedEcommerceCore\Services\AbandonedCart;

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\URL;
use Dashed\DashedEcommerceCore\Models\Order;

class CancelledOrderAbandonedSource implements AbandonedCartSource
{
    public function __construct(private readonly Order $order)
    {
    }

    public function email(): ?string
    {
        return $this->order->email;
    }

    public function siteId(): ?int
    {
        return $this->order->site_id ?? null;
    }

    public function locale(): ?string
    {
        return $this->order->locale ?? null;
    }

    public function items(): Collection
    {
        return $this->order->orderProducts->map(fn ($op) => [
            'name' => $op->name,
            'quantity' => (int) $op->quantity,
            'price' => (int) round(((float) ($op->price ?? 0)) * 100),
            'image_id' => $op->product?->firstImage ?? $op->product?->productGroup?->firstImage ?? null,
            'product_url' => $op->product?->getUrl(),
        ])->values();
    }

    public function total(): int
    {
        return (int) round(((float) ($this->order->total ?? 0)) * 100);
    }

    public function currency(): string
    {
        return $this->order->currency ?? 'EUR';
    }

    public function resumeUrl(): string
    {
        return URL::temporarySignedRoute(
            'dashed.frontend.recover-order',
            now()->addDays(30),
            ['order' => $this->order->hash],
        );
    }

    public function variables(): array
    {
        return [
            ':orderId:' => (string) ($this->order->invoice_id ?? $this->order->id),
            ':orderDate:' => $this->order->created_at?->format('j F Y') ?? '',
        ];
    }
}
