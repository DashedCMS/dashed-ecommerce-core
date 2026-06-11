<?php

declare(strict_types=1);

namespace Dashed\DashedEcommerceCore\Http\Controllers\Api\V1;

use Illuminate\Http\JsonResponse;
use Illuminate\Routing\Controller;
use Dashed\DashedCore\Classes\Sites;
use Dashed\DashedEcommerceCore\Support\InsightsService;

class InsightsController extends Controller
{
    /** Cashflow-puls + voorspellend inkoopadvies voor de actieve site. */
    public function index(InsightsService $insights): JsonResponse
    {
        return response()->json($insights->all((string) Sites::getActive()));
    }
}
