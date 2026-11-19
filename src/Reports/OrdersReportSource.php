<?php

namespace Dashed\DashedEcommerceCore\Reports;

use Illuminate\Support\Facades\DB;
use Dashed\DashedCore\Reports\ReportFilter;
use Dashed\DashedCore\Reports\ReportSource;
use Illuminate\Database\Eloquent\Builder;
use Dashed\DashedCore\Reports\ReportMeasure;
use Dashed\DashedCore\Reports\ReportGrouping;
use Dashed\DashedEcommerceCore\Models\Order;
use Dashed\DashedEcommerceCore\Classes\OrderStatistics;

/**
 * Bestellingen: betaalde orders (Order::PAID_STATUSES) zonder proforma's,
 * creditorders en geannuleerde orders, dezelfde afbakening als de
 * dashboardcijfers (isPaid + calculatableForStats). Omzet exclusief btw is
 * het totaal min de btw van de order.
 */
class OrdersReportSource extends ReportSource
{
    public function key(): string
    {
        return 'orders';
    }

    public function label(): string
    {
        return __('Bestellingen');
    }

    public function permission(): ?string
    {
        return 'view_order';
    }

    public function query(): Builder
    {
        return Order::query()
            ->whereIn('dashed__orders.status', Order::PAID_STATUSES)
            ->whereNotIn('dashed__orders.invoice_id', ['PROFORMA', 'RETURN']);
    }

    public function dateColumn(): string
    {
        return 'dashed__orders.created_at';
    }

    public function measures(): array
    {
        return [
            'count' => ReportMeasure::make(__('Aantal bestellingen'), 'COUNT(dashed__orders.id)'),
            'revenue_incl' => ReportMeasure::make(__('Omzet incl. btw'), 'COALESCE(SUM(dashed__orders.total), 0)', 'money'),
            'revenue_excl' => ReportMeasure::make(__('Omzet excl. btw'), 'COALESCE(SUM(dashed__orders.total - COALESCE(dashed__orders.btw, 0)), 0)', 'money'),
            'average' => ReportMeasure::make(__('Gemiddelde orderwaarde'), 'AVG(dashed__orders.total)', 'money'),
            'products_sold' => ReportMeasure::make(__('Verkochte stuks'), 'COALESCE(SUM(report_order_qty.quantity), 0)', 'number', ['order_quantities']),
            'customers' => ReportMeasure::make(__('Unieke klanten'), 'COUNT(DISTINCT dashed__orders.email)'),
        ];
    }

    public function groupings(): array
    {
        return parent::groupings() + [
            'payment_method' => ReportGrouping::column(__('Betaalmethode'), 'dashed__orders.payment_method_id', fn (array $ids) => ReportLabels::paymentMethods($ids)),
            'shipping_method' => ReportGrouping::column(__('Verzendmethode'), 'dashed__orders.shipping_method_id', fn (array $ids) => ReportLabels::shippingMethods($ids)),
            'country' => ReportGrouping::column(__('Land'), 'dashed__orders.country'),
            'origin' => ReportGrouping::column(__('Herkomst / kanaal'), 'dashed__orders.order_origin', fn () => ReportLabels::origins()),
            'utm_source' => ReportGrouping::column(__('Bron (utm_source)'), 'dashed__orders.utm_source'),
            'site' => ReportGrouping::column(__('Site'), 'dashed__orders.site_id', fn () => ReportLabels::sites()),
        ];
    }

    public function filters(): array
    {
        return [
            'site' => ReportFilter::make(__('Site'), 'dashed__orders.site_id', fn () => ReportLabels::sites()),
            'country' => ReportFilter::make(__('Land'), 'dashed__orders.country', fn () => ReportLabels::distinctOptions('dashed__orders', 'country')),
            'origin' => ReportFilter::make(__('Herkomst / kanaal'), 'dashed__orders.order_origin', fn () => ReportLabels::origins()),
            'payment_method' => ReportFilter::make(__('Betaalmethode'), 'dashed__orders.payment_method_id', fn () => ReportLabels::paymentMethods()),
            'shipping_method' => ReportFilter::make(__('Verzendmethode'), 'dashed__orders.shipping_method_id', fn () => ReportLabels::shippingMethods()),
            'utm_source' => ReportFilter::make(__('Bron (utm_source)'), 'dashed__orders.utm_source', fn () => ReportLabels::distinctOptions('dashed__orders', 'utm_source')),
        ];
    }

    public function joins(): array
    {
        return [
            // Verkochte stuks per order als subquery, zodat een order in de
            // join één rij blijft en omzet en aantallen niet verdubbelen.
            'order_quantities' => fn (Builder $query) => $query->leftJoinSub(
                DB::table('dashed__order_products')
                    ->selectRaw('order_id, SUM(quantity) as quantity')
                    ->whereNotIn('sku', OrderStatistics::NON_PRODUCT_SKUS)
                    ->groupBy('order_id'),
                'report_order_qty',
                'report_order_qty.order_id',
                '=',
                'dashed__orders.id',
            ),
        ];
    }
}
