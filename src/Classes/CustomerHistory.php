<?php

namespace Dashed\DashedEcommerceCore\Classes;

use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Database\Eloquent\Builder;
use Dashed\DashedEcommerceCore\Models\Order;
use Dashed\DashedEcommerceCore\Models\PaymentMethod;

class CustomerHistory
{
    /** @var array<string, mixed> */
    private array $cache = [];

    public function __construct(public readonly Order $anchor)
    {
    }

    public function matchKey(): ?string
    {
        if (array_key_exists(__FUNCTION__, $this->cache)) {
            return $this->cache[__FUNCTION__];
        }

        if (! $this->anchor->user_id && ! $this->anchor->email
            && ! ($this->anchor->first_name && $this->anchor->last_name)) {
            return $this->cache[__FUNCTION__] = null;
        }

        return $this->cache[__FUNCTION__] = 'order:'.$this->anchor->id;
    }

    public function totalCount(): int
    {
        if (array_key_exists(__FUNCTION__, $this->cache)) {
            return $this->cache[__FUNCTION__];
        }

        return $this->cache[__FUNCTION__] = $this->matchKey() === null
            ? 0
            : $this->baseQuery()->count();
    }

    public function otherCount(): int
    {
        if (array_key_exists(__FUNCTION__, $this->cache)) {
            return $this->cache[__FUNCTION__];
        }

        return $this->cache[__FUNCTION__] = $this->matchKey() === null
            ? 0
            : $this->baseQuery()->where('id', '!=', $this->anchor->id)->count();
    }

    public function paidCount(): int
    {
        if (array_key_exists(__FUNCTION__, $this->cache)) {
            return $this->cache[__FUNCTION__];
        }

        return $this->cache[__FUNCTION__] = $this->matchKey() === null
            ? 0
            : $this->baseQuery()->isPaid()->count();
    }

    public function lifetimeSpent(): float
    {
        if (array_key_exists(__FUNCTION__, $this->cache)) {
            return $this->cache[__FUNCTION__];
        }

        return $this->cache[__FUNCTION__] = $this->matchKey() === null
            ? 0.0
            : (float) $this->baseQuery()->isPaid()->sum('total');
    }

    public function averageOrderValue(): float
    {
        $paid = $this->paidCount();

        return $paid > 0 ? $this->lifetimeSpent() / $paid : 0.0;
    }

    public function firstOrderAt(): ?Carbon
    {
        if (array_key_exists(__FUNCTION__, $this->cache)) {
            return $this->cache[__FUNCTION__];
        }

        if ($this->matchKey() === null) {
            return $this->cache[__FUNCTION__] = null;
        }

        $value = $this->baseQuery()->min('created_at');

        return $this->cache[__FUNCTION__] = $value ? Carbon::parse($value) : null;
    }

    public function lastOrderAt(): ?Carbon
    {
        if (array_key_exists(__FUNCTION__, $this->cache)) {
            return $this->cache[__FUNCTION__];
        }

        if ($this->matchKey() === null) {
            return $this->cache[__FUNCTION__] = null;
        }

        $value = $this->baseQuery()->max('created_at');

        return $this->cache[__FUNCTION__] = $value ? Carbon::parse($value) : null;
    }

    public function daysSinceLastOrder(): ?int
    {
        $last = $this->lastOrderAt();

        if (! $last) {
            return null;
        }

        return (int) max(0, $last->diffInDays(now(), absolute: true));
    }

    public function favoritePaymentMethod(): ?string
    {
        if (array_key_exists(__FUNCTION__, $this->cache)) {
            return $this->cache[__FUNCTION__];
        }

        if ($this->matchKey() === null) {
            return $this->cache[__FUNCTION__] = null;
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
            return $this->cache[__FUNCTION__] = null;
        }

        return $this->cache[__FUNCTION__] = PaymentMethod::find($row->payment_method_id)?->name;
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
        $cacheKey = __FUNCTION__.':'.$limit;
        if (array_key_exists($cacheKey, $this->cache)) {
            return $this->cache[$cacheKey];
        }

        if ($this->matchKey() === null) {
            return $this->cache[$cacheKey] = collect();
        }

        return $this->cache[$cacheKey] = $this->baseQuery()
            ->orderByDesc('created_at')
            ->limit($limit)
            ->get();
    }

    private function baseQuery(): Builder
    {
        return Order::query()->forCustomerOf($this->anchor);
    }
}
