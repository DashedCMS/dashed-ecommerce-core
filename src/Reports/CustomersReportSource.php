<?php

namespace Dashed\DashedEcommerceCore\Reports;

use Illuminate\Database\Eloquent\Builder;
use Dashed\DashedCore\Reports\ReportSource;
use Dashed\DashedCore\Reports\ReportMeasure;
use Dashed\DashedEcommerceCore\Models\Order;
use Dashed\DashedCore\Reports\ReportGrouping;

/**
 * Klanten, gemeten aan hun eerste betaalde bestelling: per klant (op
 * e-mailadres, zodat gasten meetellen) de datum van de eerste order. Groeperen
 * per maand geeft zo het aantal nieuwe klanten per maand.
 */
class CustomersReportSource extends ReportSource
{
    public function key(): string
    {
        return 'customers';
    }

    public function label(): string
    {
        return __('Klanten');
    }

    public function permission(): ?string
    {
        return 'view_user';
    }

    public function query(): Builder
    {
        $firstOrders = Order::query()
            ->toBase()
            ->selectRaw('email, MIN(created_at) as first_order_at, COUNT(*) as order_count, SUM(total) as total_spent')
            ->whereIn('status', Order::PAID_STATUSES)
            ->whereNotIn('invoice_id', ['PROFORMA', 'RETURN'])
            ->whereNotNull('email')
            ->where('email', '!=', '')
            ->groupBy('email');

        return Order::query()
            ->withoutGlobalScopes()
            ->fromSub($firstOrders, 'report_customers');
    }

    public function dateColumn(): string
    {
        return 'report_customers.first_order_at';
    }

    public function measures(): array
    {
        return [
            'new_customers' => ReportMeasure::make(__('Nieuwe klanten'), 'COUNT(*)'),
            'repeat_customers' => ReportMeasure::make(__('Waarvan later opnieuw besteld'), 'SUM(CASE WHEN report_customers.order_count > 1 THEN 1 ELSE 0 END)'),
            'lifetime_value' => ReportMeasure::make(__('Gemiddelde besteding per klant'), 'AVG(report_customers.total_spent)', 'money'),
        ];
    }

    public function groupings(): array
    {
        return parent::groupings() + [
            'orders' => ReportGrouping::column(__('Aantal bestellingen'), 'report_customers.order_count'),
        ];
    }
}
