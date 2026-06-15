<?php

namespace Dashed\DashedEcommerceCore\Filament\Widgets\Statistics;

use Carbon\Carbon;
use Filament\Widgets\Widget;
use Dashed\DashedEcommerceCore\Models\Order;
use Dashed\DashedCore\Models\Customsetting;

class DoelenWidget extends Widget
{
    protected string $view = 'dashed-ecommerce-core::filament.widgets.doelen-widget';

    protected int | string | array $columnSpan = 'full';

    private const PERIODS = [
        'today' => 'Vandaag',
        'week' => 'Deze week',
        'month' => 'Deze maand',
        'year' => 'Dit jaar',
    ];

    public function rows(): array
    {
        $rows = [];
        foreach (self::PERIODS as $key => $label) {
            [$start, $end] = $this->range($key);

            $paid = Order::whereBetween('created_at', [$start, $end])->isPaid()->get(['id', 'total']);
            $revenue = round((float) $paid->sum('total'), 2);
            $orders = $paid->count();

            $revenueTarget = (float) Customsetting::get('dashboard_revenue_target_' . $key);
            $ordersTarget = (int) Customsetting::get('dashboard_orders_target_' . $key);

            $rows[] = [
                'key' => $key,
                'label' => $label,
                'revenue' => $revenue,
                'revenueTarget' => $revenueTarget,
                'revenuePct' => $revenueTarget > 0 ? (int) round($revenue / $revenueTarget * 100) : 0,
                'orders' => $orders,
                'ordersTarget' => $ordersTarget,
                'ordersPct' => $ordersTarget > 0 ? (int) round($orders / $ordersTarget * 100) : 0,
                'hasTarget' => $revenueTarget > 0 || $ordersTarget > 0,
            ];
        }

        return $rows;
    }

    private function range(string $key): array
    {
        $now = Carbon::now();

        return match ($key) {
            'week' => [$now->copy()->startOfWeek(), $now->copy()->endOfWeek()],
            'month' => [$now->copy()->startOfMonth(), $now->copy()->endOfMonth()],
            'year' => [$now->copy()->startOfYear(), $now->copy()->endOfYear()],
            default => [$now->copy()->startOfDay(), $now->copy()->endOfDay()],
        };
    }
}
