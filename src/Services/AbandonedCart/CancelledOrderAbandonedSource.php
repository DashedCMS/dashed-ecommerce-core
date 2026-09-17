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
        return $this->order->orderProducts
            ->filter(fn ($op) => ! empty($op->product_id) && $op->product)
            ->map(fn ($op) => [
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

    public function isValid(): bool
    {
        $orderWasPaid = $this->order->orderPayments()->where('status', 'paid')->exists();

        return ! $orderWasPaid && $this->order->status === 'cancelled' && ! $this->customerPaidAnotherOrder();
    }

    /**
     * Een klant die zijn betaling afbreekt en opnieuw begint, krijgt een
     * tweede order en rekent die af. De eerste poging verloopt pas een half
     * uur tot een uur later bij de PSP, dus na de betaling: op dat moment
     * valt er voor OrderMarkedAsPaidEvent nog niets af te blazen. Zonder deze
     * controle krijgt een klant die netjes betaald heeft de mail dat zijn
     * betaling niet is afgerond.
     */
    public function customerPaidAnotherOrder(): bool
    {
        if (blank($this->order->email) || ! $this->order->created_at) {
            return false;
        }

        return Order::query()
            ->where('email', $this->order->email)
            ->where('id', '!=', $this->order->id)
            ->isPaid()
            ->where('created_at', '>=', $this->order->created_at)
            ->exists();
    }
}
