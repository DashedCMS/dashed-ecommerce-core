<?php

declare(strict_types=1);

namespace Dashed\DashedEcommerceCore\Services\CustomerMatch;

use Generator;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Database\Eloquent\Builder;
use Dashed\DashedEcommerceCore\Models\Order;
use Illuminate\Database\Query\Builder as QueryBuilder;
use Dashed\DashedEcommerceCore\Models\CustomerMatchEndpoint;

class CustomerMatchExporter
{
    public function __construct(
        private readonly CustomerMatchHasher $hasher = new CustomerMatchHasher(),
    ) {
    }

    /**
     * @return list<string>
     */
    public function header(): array
    {
        return $this->hasher->csvHeader();
    }

    public function count(CustomerMatchEndpoint $endpoint): int
    {
        return DB::query()
            ->fromSub($this->matchingIdsQuery($endpoint), 'matches')
            ->count();
    }

    /**
     * @return Generator<int, array<string, string>>
     */
    public function rows(CustomerMatchEndpoint $endpoint, ?int $limit = null): Generator
    {
        $query = Order::query()
            ->whereIn('id', $this->matchingIdsQuery($endpoint))
            ->orderByDesc('created_at');

        if ($limit !== null) {
            $query->limit($limit);
        }

        $emitted = 0;

        foreach ($query->cursor() as $order) {
            $firstName = $order->invoice_first_name ?: $order->first_name;
            $lastName = $order->invoice_last_name ?: $order->last_name;
            $country = $order->invoice_country ?: $order->country;
            $zip = $order->invoice_zip_code ?: $order->zip_code;

            yield $this->hasher->formatRow([
                'email' => $order->email,
                'phone' => $order->phone_number,
                'first_name' => $firstName,
                'last_name' => $lastName,
                'country' => $country,
                'zip' => $zip,
            ]);

            $emitted++;

            if ($limit !== null && $emitted >= $limit) {
                return;
            }
        }
    }

    /**
     * Build a subquery that returns one `id` per unique email — the most recent
     * paid order for that customer. GROUP BY only contains `email`; the SELECT
     * uses MAX() aggregates so it satisfies MySQL's `only_full_group_by`.
     */
    private function matchingIdsQuery(CustomerMatchEndpoint $endpoint): QueryBuilder
    {
        $minOrders = max(1, (int) ($endpoint->customer_filter['min_orders'] ?? 1));

        $base = $this->buildBaseQuery($endpoint)->getQuery();

        $base
            ->select([DB::raw('MAX(id) as id')])
            ->groupBy('email');

        if ($minOrders > 1) {
            $base->havingRaw('COUNT(*) >= ?', [$minOrders]);
        }

        return $base;
    }

    private function buildBaseQuery(CustomerMatchEndpoint $endpoint): Builder
    {
        $filter = $endpoint->customer_filter ?? [];

        $query = Order::query()
            ->isPaid()
            ->whereNotNull('email')
            ->where('email', '!=', '');

        if (! empty($filter['since'])) {
            $query->where('created_at', '>=', Carbon::parse($filter['since'])->startOfDay());
        }

        if (! empty($filter['until'])) {
            $query->where('created_at', '<=', Carbon::parse($filter['until'])->endOfDay());
        }

        if (! empty($filter['countries']) && is_array($filter['countries'])) {
            $countries = array_map('strtoupper', $filter['countries']);
            $query->where(function (Builder $q) use ($countries) {
                $q->whereIn('country', $countries)
                    ->orWhereIn('invoice_country', $countries);
            });
        }

        return $query;
    }
}
