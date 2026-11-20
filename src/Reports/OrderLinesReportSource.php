<?php

namespace Dashed\DashedEcommerceCore\Reports;

use Illuminate\Database\Eloquent\Builder;
use Dashed\DashedCore\Reports\ReportFilter;
use Dashed\DashedCore\Reports\ReportSource;
use Dashed\DashedCore\Reports\ReportMeasure;
use Dashed\DashedEcommerceCore\Models\Order;
use Dashed\DashedCore\Reports\ReportGrouping;
use Dashed\DashedEcommerceCore\Models\OrderProduct;
use Dashed\DashedEcommerceCore\Classes\OrderStatistics;

/**
 * Bestelregels en producten: de productregels (zonder verzend- en
 * betaalkosten) van dezelfde betaalde orders als de bron Bestellingen. Het
 * regelbedrag (`price`) is inclusief btw, `btw` is het btw-bedrag van de
 * regel.
 *
 * Groeperen per categorie telt een product dat in twee categorieën staat in
 * beide mee; het totaal onderaan telt elke regel één keer.
 */
class OrderLinesReportSource extends ReportSource
{
    public function key(): string
    {
        return 'order_lines';
    }

    public function label(): string
    {
        return __('Bestelregels en producten');
    }

    public function permission(): ?string
    {
        return 'view_order';
    }

    public function query(): Builder
    {
        return OrderProduct::query()
            ->join('dashed__orders', 'dashed__orders.id', '=', 'dashed__order_products.order_id')
            ->whereIn('dashed__orders.status', Order::PAID_STATUSES)
            ->whereNull('dashed__orders.replaced_by_order_id')
            ->whereNotIn('dashed__orders.invoice_id', ['PROFORMA', 'RETURN'])
            ->whereNotIn('dashed__order_products.sku', OrderStatistics::NON_PRODUCT_SKUS);
    }

    public function dateColumn(): string
    {
        return 'dashed__orders.created_at';
    }

    public function measures(): array
    {
        return [
            'quantity' => ReportMeasure::make(__('Verkochte stuks'), 'COALESCE(SUM(dashed__order_products.quantity), 0)'),
            'revenue_incl' => ReportMeasure::make(__('Omzet incl. btw'), 'COALESCE(SUM(dashed__order_products.price), 0)', 'money'),
            'revenue_excl' => ReportMeasure::make(__('Omzet excl. btw'), 'COALESCE(SUM(dashed__order_products.price - COALESCE(dashed__order_products.btw, 0)), 0)', 'money'),
            'average_price' => ReportMeasure::make(__('Gemiddelde prijs per stuk'), 'SUM(dashed__order_products.price) / NULLIF(SUM(dashed__order_products.quantity), 0)', 'money'),
            'orders' => ReportMeasure::make(__('Aantal bestellingen'), 'COUNT(DISTINCT dashed__order_products.order_id)'),
            'lines' => ReportMeasure::make(__('Aantal regels'), 'COUNT(dashed__order_products.id)'),
        ];
    }

    public function groupings(): array
    {
        return parent::groupings() + [
            'product' => ReportGrouping::column(__('Product'), 'dashed__order_products.product_id', fn (array $ids) => ReportLabels::products($ids)),
            'category' => ReportGrouping::column(__('Categorie'), 'report_product_category.product_category_id', fn (array $ids) => ReportLabels::categories($ids), ['category']),
            'payment_method' => ReportGrouping::column(__('Betaalmethode'), 'dashed__orders.payment_method_id', fn (array $ids) => ReportLabels::paymentMethods($ids)),
            'shipping_method' => ReportGrouping::column(__('Verzendmethode'), 'dashed__orders.shipping_method_id', fn (array $ids) => ReportLabels::shippingMethods($ids)),
            'country' => ReportGrouping::column(__('Land'), 'dashed__orders.country'),
            'origin' => ReportGrouping::column(__('Herkomst / kanaal'), 'dashed__orders.order_origin', fn () => ReportLabels::origins()),
            'utm_source' => ReportGrouping::column(__('Bron (utm_source)'), 'dashed__orders.utm_source'),
        ];
    }

    public function filters(): array
    {
        return [
            'site' => ReportFilter::make(__('Site'), 'dashed__orders.site_id', fn () => ReportLabels::sites()),
            'category' => ReportFilter::make(__('Categorie'), 'report_product_category.product_category_id', fn () => ReportLabels::categories(), ['category']),
            'country' => ReportFilter::make(__('Land'), 'dashed__orders.country', fn () => ReportLabels::distinctOptions('dashed__orders', 'country')),
            'origin' => ReportFilter::make(__('Herkomst / kanaal'), 'dashed__orders.order_origin', fn () => ReportLabels::origins()),
            'payment_method' => ReportFilter::make(__('Betaalmethode'), 'dashed__orders.payment_method_id', fn () => ReportLabels::paymentMethods()),
        ];
    }

    public function joins(): array
    {
        return [
            'category' => fn (Builder $query) => $query->join(
                'dashed__product_category as report_product_category',
                'report_product_category.product_id',
                '=',
                'dashed__order_products.product_id',
            ),
        ];
    }
}
