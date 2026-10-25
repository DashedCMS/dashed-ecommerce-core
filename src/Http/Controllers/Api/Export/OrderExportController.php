<?php

declare(strict_types=1);

namespace Dashed\DashedEcommerceCore\Http\Controllers\Api\Export;

use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Collection;
use Illuminate\Database\Eloquent\Builder;
use Dashed\DashedEcommerceCore\Models\Order;
use Illuminate\Validation\ValidationException;
use Dashed\DashedEcommerceCore\Models\OrderProduct;

/**
 * Export-API voor externe partijen (marketingbureaus, BI): orders en
 * dagtotalen zonder persoonsgegevens. Geen naam, e-mail, telefoon of adres;
 * alleen de landcode en de attributie (utm, klik-ID's) van de order.
 *
 * Datums zijn kalenderdagen in Europe/Amsterdam, einddatum inclusief.
 * Zonder datums: vandaag.
 */
class OrderExportController
{
    public const TIMEZONE = 'Europe/Amsterdam';

    private const MAX_DAYS = 366;

    private const COST_SKUS = ['shipping_costs', 'payment_costs'];

    public function index(Request $request): JsonResponse
    {
        [$start, $end] = $this->dateRange($request);
        $perPage = (int) ($request->validate([
            'per_page' => ['nullable', 'integer', 'min:1', 'max:250'],
        ])['per_page'] ?? 100);

        $paginator = $this->query($request, $start, $end)
            ->with([
                'orderProducts' => fn ($query) => $query->withoutTrashed(),
                'orderProducts.product.productCategories',
                'orderPayments',
                'mainPaymentMethod',
                'discountCode',
            ])
            ->orderBy('created_at')
            ->orderBy('id')
            ->paginate($perPage)
            ->withQueryString();

        $orders = collect($paginator->items());
        $firstOrderIds = $this->firstOrderIdsByEmail($orders);

        return response()->json([
            'data' => $orders->map(fn (Order $order) => $this->transformOrder($order, $firstOrderIds))->values(),
            'meta' => [
                'start_date' => $start->toDateString(),
                'end_date' => $end->toDateString(),
                'timezone' => self::TIMEZONE,
                'currency' => 'EUR',
                'current_page' => $paginator->currentPage(),
                'last_page' => $paginator->lastPage(),
                'per_page' => $paginator->perPage(),
                'total' => $paginator->total(),
            ],
            'links' => [
                'next' => $paginator->nextPageUrl(),
                'prev' => $paginator->previousPageUrl(),
            ],
        ]);
    }

    public function daily(Request $request): JsonResponse
    {
        [$start, $end] = $this->dateRange($request);

        $days = [];
        for ($day = $start; $day->lte($end); $day = $day->addDay()) {
            $days[$day->toDateString()] = [
                'date' => $day->toDateString(),
                'orders' => 0,
                'revenue' => 0.0,
                'revenue_ex_tax' => 0.0,
                'tax' => 0.0,
                'discount' => 0.0,
                'shipping_costs' => 0.0,
                'payment_costs' => 0.0,
                'items_sold' => 0,
                'new_customers' => 0,
                'returns' => 0,
                'returns_total' => 0.0,
            ];
        }

        $this->query($request, $start, $end)
            ->with(['orderProducts' => fn ($query) => $query->withoutTrashed()])
            ->chunkById(500, function (Collection $orders) use (&$days): void {
                $firstOrderIds = $this->firstOrderIdsByEmail($orders);

                foreach ($orders as $order) {
                    $date = $this->localTime($order->created_at)->toDateString();
                    if (! isset($days[$date])) {
                        continue;
                    }
                    $row = &$days[$date];
                    $total = (float) $order->total;
                    $tax = (float) $order->btw;

                    $row['revenue'] += $total;
                    $row['revenue_ex_tax'] += $total - $tax;
                    $row['tax'] += $tax;

                    if ($order->credit_for_order_id) {
                        $row['returns']++;
                        $row['returns_total'] += $total;
                        unset($row);

                        continue;
                    }

                    $row['orders']++;
                    $row['discount'] += (float) $order->discount;
                    $row['shipping_costs'] += $this->costLines($order, 'shipping_costs');
                    $row['payment_costs'] += $this->costLines($order, 'payment_costs');
                    $row['items_sold'] += (int) $this->productLines($order)->sum('quantity');
                    $row['new_customers'] += $this->isNewCustomer($order, $firstOrderIds) ? 1 : 0;
                    unset($row);
                }
            });

        $data = collect($days)->map(function (array $row): array {
            foreach (['revenue', 'revenue_ex_tax', 'tax', 'discount', 'shipping_costs', 'payment_costs', 'returns_total'] as $key) {
                $row[$key] = round($row[$key], 2);
            }

            return $row;
        })->values();

        return response()->json([
            'data' => $data,
            'meta' => [
                'start_date' => $start->toDateString(),
                'end_date' => $end->toDateString(),
                'timezone' => self::TIMEZONE,
                'currency' => 'EUR',
            ],
        ]);
    }

    /**
     * Betaalde orders (ook wachtend op bevestiging en deels betaald) en
     * creditorders van retouren. Pending en geannuleerd vallen weg.
     */
    private function query(Request $request, CarbonImmutable $start, CarbonImmutable $end): Builder
    {
        $siteId = $request->validate([
            'site_id' => ['nullable', 'string', 'max:100'],
        ])['site_id'] ?? null;

        return Order::query()
            ->isPaidOrReturn()
            // created_at staat in de app-tijdzone; de grenzen zijn Amsterdamse dagen.
            ->whereBetween('created_at', [
                $start->startOfDay()->setTimezone(config('app.timezone')),
                $end->endOfDay()->setTimezone(config('app.timezone')),
            ])
            ->when($siteId, fn (Builder $query) => $query->where('site_id', $siteId));
    }

    /** @return array{0: CarbonImmutable, 1: CarbonImmutable} */
    private function dateRange(Request $request): array
    {
        $data = $request->validate([
            'start_date' => ['nullable', 'date_format:Y-m-d'],
            'end_date' => ['nullable', 'date_format:Y-m-d'],
        ]);

        $today = CarbonImmutable::now(self::TIMEZONE)->startOfDay();
        $start = isset($data['start_date']) ? CarbonImmutable::createFromFormat('!Y-m-d', $data['start_date'], self::TIMEZONE) : null;
        $end = isset($data['end_date']) ? CarbonImmutable::createFromFormat('!Y-m-d', $data['end_date'], self::TIMEZONE) : null;

        // Alleen een begindatum: tot en met vandaag. Alleen een einddatum: die ene dag.
        $start ??= $end ?? $today;
        $end ??= isset($data['start_date']) ? $today : $start;

        if ($end->lt($start)) {
            throw ValidationException::withMessages(['end_date' => 'De einddatum ligt voor de begindatum.']);
        }

        if ($start->diffInDays($end) + 1 > self::MAX_DAYS) {
            throw ValidationException::withMessages(['end_date' => 'Maximaal ' . self::MAX_DAYS . ' dagen per verzoek.']);
        }

        return [$start, $end];
    }

    private function transformOrder(Order $order, array $firstOrderIds): array
    {
        $isCredit = (bool) $order->credit_for_order_id;
        $total = (float) $order->total;
        $tax = (float) $order->btw;

        return [
            'id' => $order->id,
            'invoice_id' => $order->invoice_id,
            'created_at' => $this->localTime($order->created_at)->toIso8601String(),
            'status' => $order->status,
            'is_credit' => $isCredit,
            'credit_for_order_id' => $order->credit_for_order_id,
            'site_id' => $order->site_id,
            'locale' => $order->locale,
            'order_origin' => $order->order_origin,
            'country_code' => $order->countryCode,
            'payment_method' => $order->payment_method,
            'shipping_method' => $order->orderProducts->firstWhere('sku', 'shipping_costs')?->name ?: null,
            'new_customer' => ! $isCredit && $this->isNewCustomer($order, $firstOrderIds),
            'discount_code' => $order->discountCode?->code,
            'currency' => 'EUR',
            'subtotal' => $this->money($order->subtotal),
            'discount' => $this->money($order->discount),
            'shipping_costs' => $this->money($this->costLines($order, 'shipping_costs')),
            'payment_costs' => $this->money($this->costLines($order, 'payment_costs')),
            'tax' => $this->money($tax),
            'total' => $this->money($total),
            'total_ex_tax' => $this->money($total - $tax),
            'attribution' => [
                'utm_source' => $order->utm_source,
                'utm_medium' => $order->utm_medium,
                'utm_campaign' => $order->utm_campaign,
                'utm_term' => $order->utm_term,
                'utm_content' => $order->utm_content,
                'gclid' => $order->gclid,
                'fbclid' => $order->fbclid,
                'msclkid' => $order->msclkid,
                'landing_page' => $order->landing_page,
                'referrer' => $order->landing_page_referrer,
                'first_touch_at' => $order->attribution_first_touch_at ? $this->localTime($order->attribution_first_touch_at)->toIso8601String() : null,
                'last_touch_at' => $order->attribution_last_touch_at ? $this->localTime($order->attribution_last_touch_at)->toIso8601String() : null,
            ],
            'items' => $this->productLines($order)
                ->map(fn (OrderProduct $line) => $this->transformLine($line))
                ->values(),
        ];
    }

    private function transformLine(OrderProduct $line): array
    {
        $quantity = (int) $line->quantity;
        $price = (float) $line->price;

        return [
            'product_id' => $line->product_id,
            'product_group_id' => $line->product?->product_group_id,
            'sku' => $line->sku,
            'ean' => $line->product?->ean,
            'name' => $line->name,
            'category' => $line->product?->productCategories->first()?->name,
            'quantity' => $quantity,
            'returned_quantity' => (int) $line->returned_quantity,
            // price op de orderregel is het regeltotaal incl. btw, na korting.
            'unit_price' => $this->money($quantity ? $price / $quantity : $price),
            'discount' => $this->money($line->discount),
            'tax' => $this->money($line->btw),
            'vat_rate' => $line->vat_rate !== null ? (float) $line->vat_rate : null,
            'total' => $this->money($price),
        ];
    }

    private function productLines(Order $order): Collection
    {
        return $order->orderProducts->reject(fn (OrderProduct $line) => in_array($line->sku, self::COST_SKUS, true));
    }

    private function costLines(Order $order, string $sku): float
    {
        return (float) $order->orderProducts->where('sku', $sku)->sum('price');
    }

    /**
     * Per e-mailadres het id van de eerste betaalde order, zodat
     * "nieuwe klant" één query per pagina kost. Het adres zelf verlaat de
     * server niet.
     *
     * @return array<string, int>
     */
    private function firstOrderIdsByEmail(Collection $orders): array
    {
        $emails = $orders->pluck('email')->filter()->map(fn ($email) => strtolower((string) $email))->unique()->values();

        if ($emails->isEmpty()) {
            return [];
        }

        return Order::query()
            ->isPaidOrReturn()
            ->whereNull('credit_for_order_id')
            ->whereIn('email', $emails)
            ->selectRaw('LOWER(email) as email_key, MIN(id) as first_id')
            ->groupBy('email_key')
            ->pluck('first_id', 'email_key')
            ->map(fn ($id) => (int) $id)
            ->all();
    }

    private function isNewCustomer(Order $order, array $firstOrderIds): bool
    {
        $email = strtolower((string) $order->email);

        return $email !== '' && ($firstOrderIds[$email] ?? null) === $order->id;
    }

    private function localTime($time): CarbonImmutable
    {
        $time = $time instanceof \DateTimeInterface ? CarbonImmutable::instance($time) : CarbonImmutable::parse($time, config('app.timezone'));

        return $time->setTimezone(self::TIMEZONE);
    }

    private function money($amount): float
    {
        return round((float) $amount, 2);
    }
}
