<?php

declare(strict_types=1);

namespace Dashed\DashedEcommerceCore\Http\Controllers\Api\V1;

use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Routing\Controller;
use Dashed\DashedCore\Classes\Sites;
use Dashed\DashedEcommerceCore\Support\Analysis\AnalysisPeriod;
use Dashed\DashedEcommerceCore\Support\Analysis\AnalysisContext;
use Dashed\DashedEcommerceCore\Support\Analysis\SalesAnalysisPayload;

/**
 * Verkoopanalyse voor de app: dezelfde secties/signalen/narratief als de
 * Filament-pagina, uit dezelfde (gecachete) berekening.
 */
class SalesAnalysisController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $data = $request->validate([
            'start' => ['nullable', 'date'],
            'end' => ['nullable', 'date'],
            'fresh' => ['nullable', 'boolean'],
        ]);

        $period = AnalysisPeriod::make(
            $data['start'] ?? now()->startOfMonth()->toDateString(),
            $data['end'] ?? now()->toDateString(),
        );

        $context = AnalysisContext::for($period, (string) Sites::getActive());
        $payload = SalesAnalysisPayload::build($context, (bool) ($data['fresh'] ?? false));

        return response()->json([
            'start' => $period->start->toDateString(),
            'end' => $period->end->toDateString(),
            'sections' => $payload['sections'],
            'signals' => $payload['signals'],
            'narrative' => $payload['narrative'],
        ]);
    }
}
