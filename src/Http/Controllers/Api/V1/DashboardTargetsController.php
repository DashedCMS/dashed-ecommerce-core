<?php

declare(strict_types=1);

namespace Dashed\DashedEcommerceCore\Http\Controllers\Api\V1;

use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Routing\Controller;
use Dashed\DashedCore\Classes\Sites;
use Dashed\DashedCore\Models\Customsetting;

class DashboardTargetsController extends Controller
{
    /**
     * Per-site omzet- en bestellingsdoel voor één periode (vandaag/week/maand/
     * jaar) opslaan. Een leeg/null doel wist het doel (0 = geen doel).
     */
    public function update(Request $request): JsonResponse
    {
        $data = $request->validate([
            'period' => 'required|in:today,week,month,year',
            'revenue_target' => 'nullable|numeric|min:0',
            'orders_target' => 'nullable|integer|min:0',
        ]);

        $siteId = (string) Sites::getActive();
        $period = $data['period'];

        $revenueTarget = (float) ($data['revenue_target'] ?? 0);
        $ordersTarget = (int) ($data['orders_target'] ?? 0);

        Customsetting::set('dashboard_revenue_target_' . $period, (string) $revenueTarget, $siteId);
        Customsetting::set('dashboard_orders_target_' . $period, (string) $ordersTarget, $siteId);

        return response()->json([
            'period' => $period,
            'revenue_target' => $revenueTarget,
            'orders_target' => $ordersTarget,
        ]);
    }
}
