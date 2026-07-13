<?php

declare(strict_types=1);

namespace Dashed\DashedEcommerceCore\Http\Controllers\Api\V1;

use Illuminate\Http\Request;
use Dashed\DashedEcommerceCore\Support\SmartSearch;
use Illuminate\Http\JsonResponse;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\DB;
use Dashed\DashedCore\Classes\Sites;
use Illuminate\Database\Query\Builder;
use Dashed\DashedEcommerceCore\Models\Product;
use Dashed\DashedEcommerceCore\Models\ProductGroup;

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
        $query = DB::table('dashed__order_products as op')
            ->join('dashed__orders as o', 'o.id', '=', 'op.order_id')
            ->where('o.site_id', $siteId)
            ->whereNull('op.deleted_at')
            ->whereNotIn('op.sku', self::COST_SKUS)
            ->whereIn('o.status', self::PAID_STATUSES)
            // Alleen betaalde bestellingen met een echte factuur-id; nooit proforma/retour.
            ->whereNotNull('o.invoice_id')
            ->where('o.invoice_id', '!=', '')
            ->whereNotIn('o.invoice_id', ['PROFORMA', 'RETURN']);

        // Fulfillment status: standaard 'unhandled' (zoals Filament); expliciet leeg = alles.
        $fulfillment = $request->has('fulfillment_status') ? (string) $request->query('fulfillment_status') : 'unhandled';
        if ($fulfillment !== '') {
            $query->where('o.fulfillment_status', $fulfillment);
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

        SmartSearch::apply($query, $request->query('search'), ['op.name', 'o.invoice_id']);

        return $query;
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
        $rows = (clone $base)
            ->select([
                'op.id', 'op.order_id', 'op.product_id', 'op.name', 'op.sku', 'op.quantity',
                'o.invoice_id', 'o.order_origin', 'o.fulfillment_status', 'o.created_at',
                'o.first_name', 'o.last_name', 'o.email',
            ])
            ->orderBy($sortColumn, $sortDir)
            ->forPage($page, $perPage)
            ->get();

        $products = $this->productMap($rows->pluck('product_id')->all());

        $data = $rows->map(function ($r) use ($products): array {
            $p = $products[$r->product_id] ?? null;

            return [
                'id' => (int) $r->id,
                'order_id' => (int) $r->order_id,
                'invoice_id' => $r->invoice_id,
                'product_id' => $r->product_id ? (int) $r->product_id : null,
                'name' => $r->name,
                'sku' => $r->sku,
                'quantity' => (int) $r->quantity,
                'stock' => $p ? (int) $p['stock'] : null,
                'image_url' => $p['image_url'] ?? null,
                'order_origin' => $r->order_origin,
                'fulfillment_status' => $r->fulfillment_status,
                'customer_name' => trim((string) ($r->first_name . ' ' . $r->last_name)) ?: $r->email,
                'created_at' => $r->created_at ? (string) $r->created_at : null,
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
