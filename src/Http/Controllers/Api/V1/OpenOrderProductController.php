<?php

declare(strict_types=1);

namespace Dashed\DashedEcommerceCore\Http\Controllers\Api\V1;

use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\DB;
use Dashed\DashedCore\Classes\Sites;
use Illuminate\Database\Query\Builder;
use Dashed\DashedEcommerceCore\Models\Product;
use Dashed\DashedEcommerceCore\Models\OrderProduct;
use Dashed\DashedEcommerceCore\Models\ProductGroup;
use Dashed\DashedEcommerceCore\Support\SmartSearch;

/**
 * Openstaande bestellingen op orderregel-niveau — gelijk aan de Filament-pagina
 * "Openstaande bestellingen". Drie weergaven: per orderregel, gegroepeerd per
 * product en gegroepeerd per productgroep. Bij de gegroepeerde weergaven hoort
 * geen klantnaam.
 */
class OpenOrderProductController extends Controller
{
    private const PAID_STATUSES = ['paid', 'waiting_for_confirmation', 'partially_paid'];
    private const COST_SKUS = ['shipping_costs', 'payment_costs'];
    private const OPEN_STATUSES = ['unhandled', 'in_treatment'];

    /**
     * Combinaties van voorraad bij bestellen en voorraad nu (filter `stock_combo`),
     * als [stock_status_at_order, now_in_stock]. De volgorde is ook de
     * sorteervolgorde van `sort=stock_combo`: eerst wat nu eindelijk leverbaar is.
     */
    private const STOCK_COMBOS = [
        'out_in' => ['out_of_stock', 1],
        'in_in' => ['in_stock', 1],
        'in_out' => ['in_stock', 0],
        'out_out' => ['out_of_stock', 0],
    ];

    public function index(Request $request): JsonResponse
    {
        $siteId = (string) Sites::getActive();
        $groupBy = (string) $request->query('group_by', 'none');
        $perPage = (int) config('dashed-mobile-api.default_page_size', 25);
        $page = max(1, (int) $request->query('page', 1));

        $base = $this->baseQuery($request, $siteId);

        return match ($groupBy) {
            'product' => $this->grouped($base, $request, $perPage, $page, 'product'),
            'product_group' => $this->grouped($base, $request, $perPage, $page, 'product_group'),
            default => $this->flat($base, $request, $perPage, $page),
        };
    }

    /**
     * Basis: orderregels van openstaande (onafgehandelde, betaalde) bestellingen
     * voor de actieve site, met de gevraagde filters. Kostenregels eruit.
     */
    private function baseQuery(Request $request, string $siteId): Builder
    {
        $query = $this->coreQuery($siteId)
            // Voorraad nu per regel (oudste bestelling eerst), voor de weergave,
            // de sortering en het filter op voorraad bij bestellen / nu.
            ->leftJoinSub($this->stockNowQuery($siteId), 'sn', 'sn.line_id', '=', 'op.id');

        // "Openstaand" = nog niet afgehandeld óf in behandeling. Zonder expliciete
        // status (of met de sentinel 'open') tonen we beide; een concrete status
        // filtert exact; een expliciet lege waarde = alles.
        if (! $request->has('fulfillment_status')) {
            $query->whereIn('o.fulfillment_status', self::OPEN_STATUSES);
        } else {
            $fulfillment = (string) $request->query('fulfillment_status');
            if ($fulfillment === 'open') {
                $query->whereIn('o.fulfillment_status', self::OPEN_STATUSES);
            } elseif ($fulfillment !== '') {
                $query->where('o.fulfillment_status', $fulfillment);
            }
        }

        if ($combos = $this->list($request->query('stock_combo'))) {
            $query->where(function ($q) use ($combos): void {
                foreach ($combos as $combo) {
                    if (! isset(self::STOCK_COMBOS[$combo])) {
                        continue;
                    }
                    [$atOrder, $now] = self::STOCK_COMBOS[$combo];
                    $q->orWhere(function ($w) use ($atOrder, $now): void {
                        $w->where('op.stock_status_at_order', $atOrder)->where('sn.now_in_stock', $now);
                    });
                }
            });
        }

        if ($origins = $this->list($request->query('order_origin'))) {
            $query->whereIn('o.order_origin', $origins);
        }
        if ($productIds = $this->list($request->query('product_id'))) {
            $query->whereIn('op.product_id', $productIds);
        }

        $groupIds = $this->list($request->query('product_group_id'));
        $categoryIds = $this->list($request->query('product_category_id'));
        if ($groupIds || $categoryIds) {
            $query->leftJoin('dashed__products as p', 'p.id', '=', 'op.product_id');
            if ($groupIds) {
                $query->whereIn('p.product_group_id', $groupIds);
            }
            if ($categoryIds) {
                $query->whereExists(function ($sub) use ($categoryIds): void {
                    $sub->from('dashed__product_category as pc')
                        ->whereColumn('pc.product_group_id', 'p.product_group_id')
                        ->whereIn('pc.product_category_id', $categoryIds);
                });
            }
        }

        // Ternary: product-opties (product_extras gevuld of niet).
        if ($request->has('has_product_extras')) {
            if ($request->boolean('has_product_extras')) {
                $query->whereNotNull('op.product_extras')
                    ->whereRaw('JSON_VALID(op.product_extras) = 1')
                    ->whereRaw('JSON_LENGTH(op.product_extras) > 0');
            } else {
                $query->where(function ($q): void {
                    $q->whereNull('op.product_extras')
                        ->orWhereRaw('JSON_VALID(op.product_extras) = 1 AND JSON_LENGTH(op.product_extras) = 0');
                });
            }
        }

        // Ternary: gekoppeld aan een catalogus-product (product_id) of niet.
        if ($request->has('has_product_id')) {
            if ($request->boolean('has_product_id')) {
                $query->whereNotNull('op.product_id')->where('op.product_id', '!=', 0);
            } else {
                $query->where(function ($q): void {
                    $q->whereNull('op.product_id')->orWhere('op.product_id', 0);
                });
            }
        }

        // Alleen prioriteit-orders (handmatige markering).
        if ($request->boolean('priority')) {
            $query->where('o.is_priority', true);
        }

        // On hold: `on_hold=1` alleen regels van on-hold-orders, `on_hold=0` zonder.
        if ($request->has('on_hold')) {
            $request->boolean('on_hold') ? $query->whereNotNull('o.on_hold_at') : $query->whereNull('o.on_hold_at');
        }

        SmartSearch::apply($query, $request->query('search'), ['op.name', 'o.invoice_id', DB::raw(OrderProduct::extrasSearchSql('op.product_extras'))]);

        return $query;
    }

    /**
     * Openstaande regels zonder statusfilter: actieve site, niet verwijderd,
     * geen kostenregels, betaald en met een echte factuur-id.
     */
    private function coreQuery(string $siteId): Builder
    {
        return DB::table('dashed__order_products as op')
            ->join('dashed__orders as o', 'o.id', '=', 'op.order_id')
            ->where('o.site_id', $siteId)
            ->whereNull('op.deleted_at')
            // Null-veilig: `sku NOT IN (...)` is in SQL onwaar voor NULL, dus zonder
            // deze OR-groep vielen regels zonder SKU (bv. losse kassa-/POS-items)
            // stil weg — gelijk aan de Filament-scope (OpenOrderProductResource).
            ->where(function ($q): void {
                $q->whereNull('op.sku')
                    ->orWhereNotIn('op.sku', self::COST_SKUS);
            })
            ->whereIn('o.status', self::PAID_STATUSES)
            // Alleen betaalde bestellingen met een echte factuur-id; nooit proforma/retour.
            ->whereNotNull('o.invoice_id')
            ->where('o.invoice_id', '!=', '')
            ->whereNotIn('o.invoice_id', ['PROFORMA', 'RETURN']);
    }

    /**
     * Per openstaande regel of het product nu op voorraad is (`now_in_stock` 1/0).
     *
     * Met voorraadbeheer is de voorraad van openstaande bestellingen al afgeboekt.
     * De fysieke voorraad (huidige voorraad + afgeboekte open aantallen) wordt
     * daarom in besteldatum-volgorde over de open regels van het product verdeeld:
     * een regel is op voorraad als hij daarin volledig gedekt is (oudste eerst).
     * Zonder voorraadbeheer telt de handmatige voorraadstatus. Altijd over álle
     * openstaande regels, los van de filters in het verzoek.
     */
    private function stockNowQuery(string $siteId): Builder
    {
        $lines = $this->coreQuery($siteId)
            ->whereIn('o.fulfillment_status', self::OPEN_STATUSES)
            ->whereNotNull('op.product_id')
            ->select([
                'op.id as line_id',
                'op.product_id',
                DB::raw('SUM(op.quantity) OVER (PARTITION BY op.product_id ORDER BY o.created_at, op.id) as cum_qty'),
                // skip_stock-regels zijn nooit afgeboekt, dus tellen niet mee in de fysieke voorraad.
                DB::raw('SUM(CASE WHEN COALESCE(op.skip_stock, 0) = 1 THEN 0 ELSE op.quantity END) OVER (PARTITION BY op.product_id) as deducted_qty'),
            ]);

        return DB::query()
            ->fromSub($lines, 'l')
            ->join('dashed__products as snp', 'snp.id', '=', 'l.product_id')
            ->select([
                'l.line_id',
                DB::raw("CASE WHEN snp.use_stock = 1 THEN (CASE WHEN COALESCE(snp.stock, 0) + l.deducted_qty >= l.cum_qty THEN 1 ELSE 0 END) WHEN snp.stock_status = 'in_stock' THEN 1 ELSE 0 END as now_in_stock"),
            ]);
    }

    /**
     * Rangorde voor `sort=stock_combo` (volgorde van STOCK_COMBOS); regels
     * zonder status bij bestellen of zonder product krijgen de hoogste rang.
     */
    private function stockComboRankSql(): string
    {
        $cases = '';
        $rank = 0;
        foreach (self::STOCK_COMBOS as [$atOrder, $now]) {
            $cases .= " WHEN op.stock_status_at_order = '{$atOrder}' AND sn.now_in_stock = {$now} THEN {$rank}";
            $rank++;
        }

        return "CASE{$cases} ELSE {$rank} END";
    }

    private function flat(Builder $base, Request $request, int $perPage, int $page): JsonResponse
    {
        [$sortColumn, $sortDir] = $this->sort($request, [
            'invoice_id' => 'o.invoice_id',
            'name' => 'op.name',
            'quantity' => 'op.quantity',
            'created_at' => 'o.created_at',
        ], 'o.id', 'desc');

        $total = (clone $base)->count();
        $totalQuantity = (int) (clone $base)->sum('op.quantity');
        $rowsQuery = (clone $base)
            ->select([
                'op.id', 'op.order_id', 'op.product_id', 'op.name', 'op.sku', 'op.quantity',
                'op.product_extras', 'op.stock_status_at_order', 'sn.now_in_stock',
                'o.invoice_id', 'o.order_origin', 'o.fulfillment_status', 'o.created_at', 'o.is_priority',
                'o.on_hold_at', 'o.on_hold_reason',
                'o.first_name', 'o.last_name', 'o.email',
            ]);

        // Prioriteit-orders (o.a. bol) altijd bovenaan, ongeacht de gekozen
        // sortering (de app sorteert client-side identiek). Behalve bij het
        // filter op voorraad bij bestellen / nu: dan telt alleen die selectie.
        if (! $this->list($request->query('stock_combo'))) {
            $rowsQuery->orderByDesc('o.is_priority');
        }

        if ((string) $request->query('sort') === 'deadline') {
            // Eerst leveren: op effectieve deadline (leverdatum, anders verzenddatum);
            // regels zonder deadline achteraan.
            $dir = strtolower((string) $request->query('direction', 'asc')) === 'desc' ? 'desc' : 'asc';
            $eff = 'COALESCE(o.deliver_by_date, o.ship_by_date)';
            $rowsQuery->orderByRaw("{$eff} IS NULL")
                ->orderByRaw("{$eff} {$dir}")
                ->orderByDesc('o.created_at');
        } elseif ((string) $request->query('sort') === 'stock_combo') {
            // Voorraad bij bestellen / nu: eerst wat nu eindelijk leverbaar is
            // (zie STOCK_COMBOS); onbekend altijd achteraan, daarbinnen oudste eerst.
            $dir = strtolower((string) $request->query('direction', 'asc')) === 'desc' ? 'desc' : 'asc';
            $rank = $this->stockComboRankSql();
            $unknown = count(self::STOCK_COMBOS);
            $rowsQuery->orderByRaw("({$rank}) = {$unknown}")
                ->orderByRaw("{$rank} {$dir}")
                ->orderBy('o.created_at')
                ->orderBy('op.id');
        } else {
            $rowsQuery->orderBy($sortColumn, $sortDir);
        }

        $rows = $rowsQuery->forPage($page, $perPage)->get();

        $products = $this->productMap($rows->pluck('product_id')->all());
        $deadlines = $this->deadlineMap($rows->pluck('order_id')->all());
        $productionReady = $this->productionReadyMap($rows->pluck('id')->all());

        $data = $rows->map(function ($r) use ($products, $deadlines, $productionReady): array {
            $p = $products[$r->product_id] ?? null;
            $d = $deadlines[$r->order_id] ?? [];

            return [
                'id' => (int) $r->id,
                'order_id' => (int) $r->order_id,
                'invoice_id' => $r->invoice_id,
                'product_id' => $r->product_id ? (int) $r->product_id : null,
                'name' => $r->name,
                'sku' => $r->sku,
                'options' => $this->extrasLine($r->product_extras),
                'quantity' => (int) $r->quantity,
                'stock' => $p ? (int) $p['stock'] : null,
                // Voorraadstatus op besteldatum (historisch, los van de huidige
                // 'stock' hierboven): 'in_stock' / 'out_of_stock' / null.
                'stock_status_at_order' => $r->stock_status_at_order,
                // Voorraad nu voor déze regel (oudste bestelling eerst gedekt):
                // 'in_stock' / 'out_of_stock' / null (geen product).
                'stock_status_now' => $r->now_in_stock === null ? null : ((int) $r->now_in_stock === 1 ? 'in_stock' : 'out_of_stock'),
                'image_url' => $p['image_url'] ?? null,
                'order_origin' => $r->order_origin,
                'is_priority' => (bool) $r->is_priority,
                'on_hold' => $r->on_hold_at !== null,
                'on_hold_reason' => $r->on_hold_reason,
                'fulfillment_status' => $r->fulfillment_status,
                'customer_name' => trim((string) ($r->first_name . ' ' . $r->last_name)) ?: $r->email,
                'created_at' => $r->created_at ? (string) $r->created_at : null,
                // Verzend-/leverdeadline, zodat de app dezelfde deadline-chip toont
                // als in de andere orderlijsten (alleen in de ongegroepeerde view).
                'ship_by_date' => $d['ship_by_date'] ?? null,
                'deliver_by_date' => $d['deliver_by_date'] ?? null,
                'deadline_source' => $d['deadline_source'] ?? null,
                'deadline_label' => $d['deadline_label'] ?? null,
                'deadline_urgency' => $d['deadline_urgency'] ?? null,
                // Printstatus van de regel: production_ready, production_ready_at
                // (ISO-8601) en production_ready_by {id, name, avatar_url}.
                ...($productionReady[(int) $r->id] ?? (new OrderProduct())->productionReadyPayload()),
            ];
        })->all();

        return $this->respond($data, $total, $perPage, $page, $totalQuantity);
    }

    private function grouped(Builder $base, Request $request, int $perPage, int $page, string $mode): JsonResponse
    {
        $sortDir = strtolower((string) $request->query('direction', 'desc')) === 'asc' ? 'asc' : 'desc';
        $sortKey = (string) $request->query('sort', 'quantity');
        $orderColumn = $sortKey === 'name' ? 'name' : 'quantity';

        if ($mode === 'product') {
            $base->select([
                DB::raw('MIN(op.id) as id'),
                'op.product_id',
                'op.sku',
                DB::raw('MIN(op.name) as name'),
                DB::raw('SUM(op.quantity) as quantity'),
            ])->groupBy('op.product_id', 'op.sku');
        } else {
            // Per productgroep: join naar products voor de groep-id + naam.
            if (! $this->hasProductJoin($base)) {
                $base->leftJoin('dashed__products as p', 'p.id', '=', 'op.product_id');
            }
            $base->leftJoin('dashed__product_groups as pg', 'pg.id', '=', 'p.product_group_id')
                ->select([
                    DB::raw('MIN(op.id) as id'),
                    DB::raw('p.product_group_id as product_group_id'),
                    DB::raw("COALESCE(NULLIF(JSON_UNQUOTE(JSON_EXTRACT(pg.name, '$.\"nl\"')), 'null'), pg.name, 'Geen productgroep') as name"),
                    DB::raw('SUM(op.quantity) as quantity'),
                ])->groupBy('p.product_group_id', 'pg.name');
        }

        $rows = $base->orderBy($orderColumn, $sortDir)->get();
        $total = $rows->count();
        $paged = $rows->forPage($page, $perPage)->values();

        if ($mode === 'product') {
            $products = $this->productMap($paged->pluck('product_id')->all());
            $data = $paged->map(function ($r) use ($products): array {
                $p = $products[$r->product_id] ?? null;

                return [
                    'id' => (int) $r->id,
                    'product_id' => $r->product_id ? (int) $r->product_id : null,
                    'name' => $r->name,
                    'sku' => $r->sku,
                    'quantity' => (int) $r->quantity,
                    'stock' => $p ? (int) $p['stock'] : null,
                    'image_url' => $p['image_url'] ?? null,
                ];
            })->all();
        } else {
            $groups = $this->productGroupMap($paged->pluck('product_group_id')->all());
            $data = $paged->map(function ($r) use ($groups): array {
                $g = $groups[$r->product_group_id] ?? null;

                return [
                    'id' => (int) $r->id,
                    'product_group_id' => $r->product_group_id ? (int) $r->product_group_id : null,
                    'name' => $r->name,
                    'quantity' => (int) $r->quantity,
                    'stock' => $g ? (int) $g['stock'] : null,
                    'image_url' => $g['image_url'] ?? null,
                ];
            })->all();
        }

        return $this->respond($data, $total, $perPage, $page);
    }

    /**
     * Printstatus per orderregel-id, in één query met de melder eager geladen.
     *
     * @param  array<int, int|string>  $ids
     * @return array<int, array<string, mixed>>
     */
    private function productionReadyMap(array $ids): array
    {
        if ($ids === []) {
            return [];
        }

        return OrderProduct::query()
            ->whereIn('id', $ids)
            ->with('productionReadyBy')
            ->get(['id', 'production_ready_at', 'production_ready_by'])
            ->mapWithKeys(fn (OrderProduct $op): array => [$op->id => $op->productionReadyPayload()])
            ->all();
    }

    /**
     * Gekozen productopties als één regel, gelijk aan de Filament-kolom
     * (OpenOrderProductsTable::description): "Naam: Waarde | Naam: Waarde".
     * De query-builder levert product_extras als rauwe JSON-string (geen cast).
     */
    private function extrasLine(mixed $raw): ?string
    {
        $extras = is_string($raw) ? json_decode($raw, true) : $raw;
        if (! is_array($extras) || ! $extras) {
            return null;
        }

        $line = collect($extras)
            ->map(fn ($option) => ($option['name'] ?? '') . ': ' . ($option['value'] ?? ''))
            ->implode(' | ');

        return trim($line) !== ':' && $line !== '' ? $line : null;
    }

    /**
     * Verzend-/leverdeadline per order (zelfde velden als OrderSummaryResource),
     * zodat de open-orderregels dezelfde deadline-chip kunnen tonen. Eén query
     * voor alle orders op de pagina.
     *
     * @param array<int, int|string> $orderIds
     * @return array<int, array<string, mixed>>
     */
    private function deadlineMap(array $orderIds): array
    {
        $ids = array_values(array_filter(array_unique($orderIds)));
        if (! $ids) {
            return [];
        }

        return \Dashed\DashedEcommerceCore\Models\Order::query()
            ->whereIn('id', $ids)
            ->get()
            ->mapWithKeys(fn ($order): array => [$order->id => [
                'ship_by_date' => $order->ship_by_date?->toDateString(),
                'deliver_by_date' => $order->deliver_by_date?->toDateString(),
                'deadline_source' => $order->deadline_source,
                'deadline_label' => $order->deadlineLabel(),
                'deadline_urgency' => $order->deadlineUrgency(),
            ]])
            ->all();
    }

    /**
     * @param array<int, int|string> $ids
     * @return array<int, array{stock:int, image_url:?string}>
     */
    private function productMap(array $ids): array
    {
        $ids = array_values(array_filter(array_unique($ids)));
        if (! $ids) {
            return [];
        }

        return Product::query()->whereIn('id', $ids)->get()->mapWithKeys(function (Product $p): array {
            $imageId = $p->firstImage ?? null;
            $imageUrl = $imageId ? (mediaHelper()->getSingleMedia($imageId)->url ?? null) : null;

            return [$p->id => ['stock' => (int) ($p->stock ?? 0), 'image_url' => $imageUrl]];
        })->all();
    }

    /**
     * @param array<int, int|string> $ids
     * @return array<int, array{stock:int, image_url:?string}>
     */
    private function productGroupMap(array $ids): array
    {
        $ids = array_values(array_filter(array_unique($ids)));
        if (! $ids) {
            return [];
        }

        return ProductGroup::query()->whereIn('id', $ids)->get()->mapWithKeys(function (ProductGroup $g): array {
            $imageId = $g->firstImage ?? null;
            $imageUrl = $imageId ? (mediaHelper()->getSingleMedia($imageId)->url ?? null) : null;

            // Groep-voorraad = som van de voorraad van de producten in de groep,
            // zodat de voorraadweergave consistent is met de andere tabs.
            $stock = (int) $g->products()->sum('stock');

            return [$g->id => ['stock' => $stock, 'image_url' => $imageUrl]];
        })->all();
    }

    /**
     * @param array<string, string> $allowed
     * @return array{0:string,1:string}
     */
    private function sort(Request $request, array $allowed, string $default, string $defaultDir): array
    {
        $key = (string) $request->query('sort', '');
        $dir = strtolower((string) $request->query('direction', $defaultDir)) === 'asc' ? 'asc' : 'desc';

        return [$allowed[$key] ?? $default, isset($allowed[$key]) ? $dir : $defaultDir];
    }

    private function hasProductJoin(Builder $query): bool
    {
        foreach (($query->joins ?? []) as $join) {
            if ($join->table === 'dashed__products as p') {
                return true;
            }
        }

        return false;
    }

    /**
     * @return array<int, string>
     */
    private function list(mixed $value): array
    {
        if (! is_string($value) || $value === '') {
            return [];
        }

        return array_values(array_filter(array_map('trim', explode(',', $value)), fn ($v) => $v !== ''));
    }

    /**
     * @param array<int, array<string, mixed>> $data
     */
    private function respond(array $data, int $total, int $perPage, int $page, ?int $totalQuantity = null): JsonResponse
    {
        return response()->json([
            'data' => $data,
            'meta' => [
                'total' => $total,
                'total_quantity' => $totalQuantity ?? $total,
                'per_page' => $perPage,
                'current_page' => $page,
                'last_page' => (int) max(1, ceil($total / $perPage)),
            ],
        ]);
    }
}
