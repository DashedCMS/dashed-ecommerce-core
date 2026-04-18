<?php

namespace Dashed\DashedEcommerceCore\Classes;

use Carbon\Carbon;
use Dashed\DashedEcommerceCore\Models\Order;
use Dashed\DashedEcommerceCore\Models\PaymentMethod;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

class CustomerHistory
{
    public function __construct(public readonly Order $anchor) {}

    public function matchKey(): ?string
    {
        if (! $this->anchor->user_id && ! $this->anchor->email
            && ! ($this->anchor->first_name && $this->anchor->last_name)) {
            return null;
        }

        return 'order:'.$this->anchor->id;
    }

    public function totalCount(): int
    {
        if ($this->matchKey() === null) {
            return 0;
        }

        return $this->baseQuery()->count();
    }

    public function otherCount(): int
    {
        if ($this->matchKey() === null) {
            return 0;
        }

        return $this->baseQuery()->where('id', '!=', $this->anchor->id)->count();
    }

    public function paidCount(): int
    {
        if ($this->matchKey() === null) {
            return 0;
        }

        return $this->baseQuery()->isPaid()->count();
    }

    public function lifetimeSpent(): float
    {
        if ($this->matchKey() === null) {
            return 0.0;
        }

        return (float) $this->baseQuery()->isPaid()->sum('total');
    }

    public function averageOrderValue(): float
    {
        $paid = $this->paidCount();

        return $paid > 0 ? $this->lifetimeSpent() / $paid : 0.0;
    }

    public function firstOrderAt(): ?Carbon
    {
        if ($this->matchKey() === null) {
            return null;
        }

        $value = $this->baseQuery()->min('created_at');

        return $value ? Carbon::parse($value) : null;
    }

    public function lastOrderAt(): ?Carbon
    {
        if ($this->matchKey() === null) {
            return null;
        }

        $value = $this->baseQuery()->max('created_at');

        return $value ? Carbon::parse($value) : null;
    }

    public function daysSinceLastOrder(): ?int
    {
        $last = $this->lastOrderAt();

        return $last ? (int) $last->diffInDays(now()) : null;
    }

    public function favoritePaymentMethod(): ?string
    {
        if ($this->matchKey() === null) {
            return null;
        }

        $row = $this->baseQuery()
            ->isPaid()
            ->whereNotNull('payment_method_id')
            ->selectRaw('payment_method_id, COUNT(*) AS c')
            ->groupBy('payment_method_id')
            ->orderByDesc('c')
            ->limit(1)
            ->first();

        if (! $row || ! $row->payment_method_id) {
            return null;
        }

        return PaymentMethod::find($row->payment_method_id)?->name;
    }

    public function customerType(): string
    {
        $count = $this->totalCount();

        return match (true) {
            $count <= 1 => 'Nieuwe klant',
            $count <= 4 => 'Terugkerende klant',
            default => 'Trouwe klant',
        };
    }

    /** @return Collection<int, Order> */
    public function recentOrders(int $limit = 10): Collection
    {
        if ($this->matchKey() === null) {
            return collect();
        }

        return $this->baseQuery()
            ->orderByDesc('created_at')
            ->limit($limit)
            ->get();
    }

    private function baseQuery(): Builder
    {
        return Order::query()->forCustomerOf($this->anchor);
    }
}
