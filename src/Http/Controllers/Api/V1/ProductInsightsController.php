<?php

declare(strict_types=1);

namespace Dashed\DashedEcommerceCore\Http\Controllers\Api\V1;

use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Routing\Controller;
use Dashed\DashedCore\Classes\Sites;
use Dashed\DashedMobileApi\Support\DashboardPeriod;
use Dashed\DashedEcommerceCore\Support\ProductInsightsService;

/**
 * Productinzichten: toppers, langzaamlopers, marge en dead stock voor de
 * actieve site. Site-bewust en gegate op `dashboard.read` (zie route).
 */
class ProductInsightsController extends Controller
{
    public function index(Request $request, ProductInsightsService $insights): JsonResponse
    {
        $metric = (string) $request->query('metric', 'top');
        if (! in_array($metric, ProductInsightsService::METRICS, true)) {
            $metric = 'top';
        }

        $period = DashboardPeriod::fromRequest($request->query('period'), $request->query('anchor'));
        $site = (string) Sites::getActive();

        $items = $insights->forMetric($metric, $site, $period->start, $period->end);

        return response()->json([
            'metric' => $metric,
            'period' => $period->key,
            'items' => $items,
        ]);
    }
}
