<?php

namespace Dashed\DashedEcommerceCore\Reports;

use Illuminate\Database\Eloquent\Builder;
use Dashed\DashedCore\Reports\ReportFilter;
use Dashed\DashedCore\Reports\ReportSource;
use Dashed\DashedCore\Reports\ReportMeasure;
use Dashed\DashedCore\Reports\ReportGrouping;
use Dashed\DashedEcommerceCore\Models\OrderReturn;

/**
 * Retouren: per retourregel, zodat er op retourreden gegroepeerd kan worden.
 * Het aantal retouren telt daarom DISTINCT op de retour.
 */
class ReturnsReportSource extends ReportSource
{
    public function key(): string
    {
        return 'returns';
    }

    public function label(): string
    {
        return __('Retouren');
    }

    public function permission(): ?string
    {
        return 'view_order_return';
    }

    public function query(): Builder
    {
        return OrderReturn::query()
            ->join('dashed__order_return_lines', 'dashed__order_return_lines.order_return_id', '=', 'dashed__order_returns.id');
    }

    public function dateColumn(): string
    {
        return 'dashed__order_returns.created_at';
    }

    public function measures(): array
    {
        return [
            'count' => ReportMeasure::make(__('Aantal retouren'), 'COUNT(DISTINCT dashed__order_returns.id)'),
            'items' => ReportMeasure::make(__('Geretourneerde stuks'), 'COALESCE(SUM(dashed__order_return_lines.quantity), 0)'),
            'orders' => ReportMeasure::make(__('Aantal bestellingen'), 'COUNT(DISTINCT dashed__order_returns.order_id)'),
        ];
    }

    public function groupings(): array
    {
        return parent::groupings() + [
            'status' => ReportGrouping::column(__('Status'), 'dashed__order_returns.status', fn () => self::statuses()),
            'reason' => ReportGrouping::column(__('Retourreden'), 'dashed__order_return_lines.return_reason_id', fn (array $ids) => ReportLabels::returnReasons($ids)),
        ];
    }

    public function filters(): array
    {
        return [
            'site' => ReportFilter::make(__('Site'), 'dashed__order_returns.site_id', fn () => ReportLabels::sites()),
            'status' => ReportFilter::make(__('Status'), 'dashed__order_returns.status', fn () => self::statuses()),
            'reason' => ReportFilter::make(__('Retourreden'), 'dashed__order_return_lines.return_reason_id', fn () => ReportLabels::returnReasons()),
        ];
    }

    /** @return array<string, string> */
    protected static function statuses(): array
    {
        return [
            'requested' => __('Aangevraagd'),
            'approved' => __('Goedgekeurd'),
            'rejected' => __('Afgekeurd'),
            'handled' => __('Verwerkt'),
            'closed' => __('Gesloten'),
        ];
    }
}
