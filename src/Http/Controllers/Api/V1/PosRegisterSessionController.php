<?php

declare(strict_types=1);

namespace Dashed\DashedEcommerceCore\Http\Controllers\Api\V1;

use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Routing\Controller;
use Dashed\DashedCore\Classes\Sites;
use Dashed\DashedEcommerceCore\Models\Order;
use Dashed\DashedEcommerceCore\Models\OrderPayment;
use Dashed\DashedEcommerceCore\Models\PaymentMethod;
use Dashed\DashedEcommerceCore\Models\PosRegisterSession;

/**
 * Dagafsluiting / kasstaat (Z-rapport) voor de mobiele kassa.
 *
 * Eén open sessie per (user + site + dag). De omzet sinds `opened_at` wordt live
 * berekend uit POS-orders (`order_origin = 'pos'`, betaald, op de actieve site),
 * uitgesplitst per betaalmethode. De verwachte kas = startkas + som van de
 * contante betalingen. Bij afsluiten wordt dit als snapshot weggeschreven.
 */
class PosRegisterSessionController extends Controller
{
    /**
     * POST point-of-sale/open-day — open een kassasessie voor de huidige
     * gebruiker + site. Bestaat er al een open sessie, dan geven we die terug.
     */
    public function openDay(Request $request): JsonResponse
    {
        $data = $request->validate([
            'opening_float' => ['required', 'numeric', 'min:0'],
        ]);

        $siteId = Sites::getActive();
        $userId = (int) $request->user()->id;

        $existing = PosRegisterSession::query()
            ->where('site_id', $siteId)
            ->where('user_id', $userId)
            ->open()
            ->first();

        if ($existing) {
            return response()->json([
                'ok' => true,
                'already_open' => true,
                'session' => $this->sessionPayload($existing),
            ]);
        }

        $session = PosRegisterSession::create([
            'site_id' => $siteId,
            'user_id' => $userId,
            'opened_at' => now(),
            'opening_float' => round((float) $data['opening_float'], 2),
        ]);

        return response()->json([
            'ok' => true,
            'already_open' => false,
            'session' => $this->sessionPayload($session),
        ]);
    }

    /**
     * GET point-of-sale/day-summary — live samenvatting voor de open sessie van
     * de huidige gebruiker. 404 als er geen open sessie is.
     */
    public function daySummary(Request $request): JsonResponse
    {
        $session = $this->currentOpenSession($request);

        if (! $session) {
            return response()->json([
                'ok' => false,
                'message' => 'Geen geopende kassasessie.',
            ], 404);
        }

        $summary = $this->computeSummary($session);

        return response()->json(array_merge(['ok' => true], $summary));
    }

    /**
     * POST point-of-sale/close-day — sluit de open sessie af en schrijf de
     * kasstaat (Z-rapport) weg: getelde kas, verwachte kas, verschil en de
     * per-methode-snapshot.
     */
    public function closeDay(Request $request): JsonResponse
    {
        $data = $request->validate([
            'counted_cash' => ['required', 'numeric', 'min:0'],
            'notes' => ['nullable', 'string'],
        ]);

        $session = $this->currentOpenSession($request);

        if (! $session) {
            return response()->json([
                'ok' => false,
                'message' => 'Geen geopende kassasessie.',
            ], 404);
        }

        $summary = $this->computeSummary($session);

        $countedCash = round((float) $data['counted_cash'], 2);
        $expectedCash = round((float) $summary['expected_cash'], 2);

        $session->update([
            'closed_at' => now(),
            'counted_cash' => $countedCash,
            'expected_cash' => $expectedCash,
            'totals' => $summary['totals'],
            'difference' => round($countedCash - $expectedCash, 2),
            'notes' => $data['notes'] ?? null,
        ]);

        return response()->json([
            'ok' => true,
            'session' => $this->sessionPayload($session->fresh(), $summary['order_count']),
        ]);
    }

    /**
     * GET point-of-sale/day-report/{session} — een opgeslagen (afgesloten)
     * sessie teruggeven als Z-rapport, voor heruitdraaien. Site + user scoped.
     */
    public function dayReport(Request $request, PosRegisterSession $session): JsonResponse
    {
        if (
            $session->site_id !== Sites::getActive()
            || (int) $session->user_id !== (int) $request->user()->id
        ) {
            return response()->json([
                'ok' => false,
                'message' => 'Kassasessie niet gevonden.',
            ], 404);
        }

        return response()->json([
            'ok' => true,
            'session' => $this->sessionPayload($session),
        ]);
    }

    private function currentOpenSession(Request $request): ?PosRegisterSession
    {
        return PosRegisterSession::query()
            ->where('site_id', Sites::getActive())
            ->where('user_id', (int) $request->user()->id)
            ->open()
            ->first();
    }

    /**
     * Bereken de live samenvatting voor een sessie: omzet per betaalmethode +
     * verwachte kas. POS-orders = `order_origin = 'pos'`, betaald, op de actieve
     * site, aangemaakt binnen het sessievenster (>= opened_at, <= closed_at als
     * afgesloten). Bedragen per methode komen uit de betaalde OrderPayments.
     *
     * @return array{session: array, opened_at: ?string, opening_float: float, totals: array<int, array>, expected_cash: float, order_count: int}
     */
    private function computeSummary(PosRegisterSession $session): array
    {
        $orderQuery = Order::query()
            ->where('order_origin', 'pos')
            ->where('site_id', $session->site_id)
            ->isPaid()
            ->where('created_at', '>=', $session->opened_at);

        if ($session->closed_at) {
            $orderQuery->where('created_at', '<=', $session->closed_at);
        }

        $orderIds = $orderQuery->pluck('id');
        $orderCount = $orderIds->count();

        $totals = [];
        $cashTotal = 0.0;

        if ($orderIds->isNotEmpty()) {
            $payments = OrderPayment::query()
                ->whereIn('order_id', $orderIds)
                ->where('status', 'paid')
                ->get();

            $methodIds = $payments->pluck('payment_method_id')->filter()->unique();
            $methods = PaymentMethod::withTrashed()
                ->whereIn('id', $methodIds)
                ->get()
                ->keyBy('id');

            $grouped = [];

            foreach ($payments as $payment) {
                $methodId = $payment->payment_method_id;
                $method = $methodId ? $methods->get($methodId) : null;
                $isCash = (bool) ($method->is_cash_payment ?? false);
                $label = $method
                    ? (string) ($method->name ?? '')
                    : (string) ($payment->payment_method ?? 'Onbekend');
                $key = $methodId !== null ? 'm-' . $methodId : 'name-' . $label;

                if (! isset($grouped[$key])) {
                    $grouped[$key] = [
                        'method' => $methodId,
                        'label' => $label !== '' ? $label : 'Onbekend',
                        'is_cash' => $isCash,
                        'total' => 0.0,
                        'count' => 0,
                    ];
                }

                $grouped[$key]['total'] += (float) $payment->amount;
                $grouped[$key]['count']++;

                if ($isCash) {
                    $cashTotal += (float) $payment->amount;
                }
            }

            foreach ($grouped as $row) {
                $totals[] = [
                    'method' => $row['method'],
                    'label' => $row['label'],
                    'is_cash' => $row['is_cash'],
                    'total' => round($row['total'], 2),
                    'count' => $row['count'],
                ];
            }
        }

        $expectedCash = round((float) $session->opening_float + $cashTotal, 2);

        return [
            'session' => $this->sessionPayload($session, $orderCount),
            'opened_at' => optional($session->opened_at)->toIso8601String(),
            'opening_float' => round((float) $session->opening_float, 2),
            'totals' => $totals,
            'expected_cash' => $expectedCash,
            'order_count' => $orderCount,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function sessionPayload(PosRegisterSession $session, ?int $orderCount = null): array
    {
        return [
            'id' => $session->id,
            'site_id' => $session->site_id,
            'user_id' => $session->user_id,
            'opened_at' => optional($session->opened_at)->toIso8601String(),
            'opening_float' => round((float) $session->opening_float, 2),
            'closed_at' => optional($session->closed_at)->toIso8601String(),
            'counted_cash' => $session->counted_cash !== null ? round((float) $session->counted_cash, 2) : null,
            'expected_cash' => $session->expected_cash !== null ? round((float) $session->expected_cash, 2) : null,
            'difference' => $session->difference !== null ? round((float) $session->difference, 2) : null,
            'totals' => $session->totals,
            'notes' => $session->notes,
            'is_open' => $session->isOpen(),
            'order_count' => $orderCount,
        ];
    }
}
