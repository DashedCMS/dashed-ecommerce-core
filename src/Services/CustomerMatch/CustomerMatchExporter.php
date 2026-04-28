<?php

declare(strict_types=1);

namespace Dashed\DashedEcommerceCore\Services\CustomerMatch;

use Dashed\DashedEcommerceCore\Models\CustomerMatchEndpoint;
use Dashed\DashedEcommerceCore\Models\Order;
use Generator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;

class CustomerMatchExporter
{
    public function __construct(
        private readonly CustomerMatchHasher $hasher = new CustomerMatchHasher(),
    ) {}

    /**
     * @return list<string>
     */
    public function header(): array
    {
        return $this->hasher->csvHeader();
    }

    public function count(CustomerMatchEndpoint $endpoint): int
    {
        return $this->buildBaseQuery($endpoint)
            ->distinct()
            ->count('email');
    }

    /**
     * @return Generator<int, array<string, string>>
     */
    public function rows(CustomerMatchEndpoint $endpoint, ?int $limit = null): Generator
    {
        $minOrders = (int) ($endpoint->customer_filter['min_orders'] ?? 1);
        $minOrders = max(1, $minOrders);

        $aggregateQuery = $this->buildBaseQuery($endpoint)
            ->select([
                'email',
                'first_name',
                'last_name',
                'phone_number',
                'country',
                'invoice_country',
                'invoice_zip_code',
                'zip_code',
                'invoice_first_name',
                'invoice_last_name',
            ])
            ->selectRaw('MAX(created_at) as last_order_at')
            ->selectRaw('COUNT(*) as orders_count')
            ->groupBy('email')
            ->orderByDesc('last_order_at');

        if ($minOrders > 1) {
            $aggregateQuery->havingRaw('COUNT(*) >= ?', [$minOrders]);
        }

        if ($limit !== null) {
            $aggregateQuery->limit($limit);
        }

        $emitted = 0;

        foreach ($aggregateQuery->cursor() as $row) {
            $firstName = $row->invoice_first_name ?: $row->first_name;
            $lastName = $row->invoice_last_name ?: $row->last_name;
            $country = $row->invoice_country ?: $row->country;
            $zip = $row->invoice_zip_code ?: $row->zip_code;

            yield $this->hasher->formatRow([
                'email' => $row->email,
                'phone' => $row->phone_number,
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
