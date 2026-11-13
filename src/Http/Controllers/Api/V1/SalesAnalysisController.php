<?php

declare(strict_types=1);

namespace Dashed\DashedEcommerceCore\Http\Controllers\Api\V1;

use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Routing\Controller;
use Dashed\DashedCore\Classes\Sites;
use Dashed\DashedEcommerceCore\Models\Order;
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

    /**
     * Omzet + aantal bestellingen per verkoopkanaal (order_origin) binnen een
     * periode (default: deze maand). Betaalde omzet volgt dezelfde paid-statuses
     * als de rest van de app. Read-only.
     */
    public function channelBreakdown(Request $request): JsonResponse
    {
        $data = $request->validate([
            'start' => ['nullable', 'date'],
            'end' => ['nullable', 'date'],
        ]);

        $period = AnalysisPeriod::make(
            $data['start'] ?? now()->startOfMonth()->toDateString(),
            $data['end'] ?? now()->toDateString(),
        );

        $paid = "CASE WHEN status IN ('paid','waiting_for_confirmation','partially_paid') THEN total ELSE 0 END";

        $rows = Order::thisSite()
            ->whereDate('created_at', '>=', $period->start->toDateString())
            ->whereDate('created_at', '<=', $period->end->toDateString())
            ->selectRaw("COALESCE(NULLIF(order_origin, ''), 'own') as channel")
            ->selectRaw('COUNT(*) as orders')
            ->selectRaw("SUM($paid) as revenue")
            ->groupBy('channel')
            ->orderByDesc('revenue')
            ->get();

        return response()->json([
            'start' => $period->start->toDateString(),
            'end' => $period->end->toDateString(),
            'data' => $rows->map(fn ($r) => [
                'channel' => (string) $r->channel,
                'orders' => (int) $r->orders,
                'revenue' => (float) $r->revenue,
            ])->all(),
        ]);
    }
}
