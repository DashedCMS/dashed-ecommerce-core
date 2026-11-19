<?php

namespace Dashed\DashedEcommerceCore\Reports;

use Illuminate\Support\Facades\DB;
use Dashed\DashedCore\Classes\Sites;
use Dashed\DashedEcommerceCore\Models\Product;
use Dashed\DashedEcommerceCore\Models\ReturnReason;
use Dashed\DashedEcommerceCore\Models\PaymentMethod;
use Dashed\DashedEcommerceCore\Models\ShippingMethod;
use Dashed\DashedEcommerceCore\Models\ProductCategory;

/**
 * Gedeelde keuzelijsten en naamvertalingen voor de rapportbronnen van de
 * webshop. Namen worden in één query per groepering opgehaald, nooit per rij.
 */
class ReportLabels
{
    public const DISTINCT_LIMIT = 300;

    /** @return array<int|string, string> */
    public static function paymentMethods(?array $ids = null): array
    {
        return PaymentMethod::query()
            ->when($ids !== null, fn ($q) => $q->whereIn('id', $ids))
            ->get()
            ->mapWithKeys(fn ($method) => [$method->id => (string) ($method->name ?: $method->id)])
            ->all();
    }

    /** @return array<int|string, string> */
    public static function shippingMethods(?array $ids = null): array
    {
        return ShippingMethod::query()
            ->when($ids !== null, fn ($q) => $q->whereIn('id', $ids))
            ->get()
            ->mapWithKeys(fn ($method) => [$method->id => (string) ($method->name ?: $method->id)])
            ->all();
    }

    /** @return array<int|string, string> */
    public static function products(array $ids): array
    {
        return Product::withTrashed()
            ->whereIn('id', $ids)
            ->get(['id', 'name', 'sku'])
            ->mapWithKeys(fn ($product) => [$product->id => trim((string) $product->name) ?: (string) $product->sku])
            ->all();
    }

    /** @return array<int|string, string> */
    public static function categories(?array $ids = null): array
    {
        return ProductCategory::query()
            ->when($ids !== null, fn ($q) => $q->whereIn('id', $ids))
            ->get(['id', 'name'])
            ->mapWithKeys(fn ($category) => [$category->id => (string) ($category->name ?: $category->id)])
            ->all();
    }

    /** @return array<int|string, string> */
    public static function returnReasons(?array $ids = null): array
    {
        return ReturnReason::query()
            ->when($ids !== null, fn ($q) => $q->whereIn('id', $ids))
            ->get()
            ->mapWithKeys(fn ($reason) => [$reason->id => (string) ($reason->label ?: $reason->id)])
            ->all();
    }

    /** @return array<string, string> */
    public static function sites(): array
    {
        return collect(Sites::getSites())->mapWithKeys(fn ($site) => [(string) $site['id'] => (string) $site['name']])->all();
    }

    /** @return array<string, string> */
    public static function origins(): array
    {
        $labels = ['own' => __('Webshop'), 'pos' => __('Kassa'), 'Bol' => 'Bol'];

        return collect(self::distinct('dashed__orders', 'order_origin'))
            ->mapWithKeys(fn ($origin) => [$origin => $labels[$origin] ?? $origin])
            ->all();
    }

    /**
     * Gebruikte waarden van een kolom, als keuzelijst voor een filter.
     *
     * @return array<string, string>
     */
    public static function distinctOptions(string $table, string $column): array
    {
        return collect(self::distinct($table, $column))->mapWithKeys(fn ($value) => [$value => $value])->all();
    }

    /** @return array<int, string> */
    protected static function distinct(string $table, string $column): array
    {
        return rescue(fn () => DB::table($table)
            ->whereNotNull($column)
            ->where($column, '!=', '')
            ->distinct()
            ->orderBy($column)
            ->limit(self::DISTINCT_LIMIT)
            ->pluck($column)
            ->map(fn ($value) => (string) $value)
            ->all(), [], false);
    }
}
