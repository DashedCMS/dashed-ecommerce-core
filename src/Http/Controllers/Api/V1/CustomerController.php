<?php

declare(strict_types=1);

namespace Dashed\DashedEcommerceCore\Http\Controllers\Api\V1;

use Illuminate\Http\Request;
use Dashed\DashedEcommerceCore\Support\SmartSearch;
use Illuminate\Http\JsonResponse;
use Illuminate\Routing\Controller;
use Dashed\DashedEcommerceCore\Models\Order;
use Dashed\DashedEcommerceCore\Classes\CustomerHistory;

/**
 * Klanten = aggregatie van bestellingen op e-mailadres (er is geen los klant-
 * model). Levert een doorzoekbare lijst en een 360°-profiel met bestelhistorie.
 */
class CustomerController extends Controller
{
    private const PAID_STATUSES = ['paid', 'waiting_for_confirmation', 'partially_paid'];

    public function index(Request $request): JsonResponse
    {
        $perPage = (int) config('dashed-mobile-api.default_page_size', 25);
        $page = max(1, (int) $request->query('page', 1));
        $search = trim((string) $request->query('search', ''));

        $base = Order::thisSite()
            ->whereNotNull('email')
            ->where('email', '!=', '')
            ->when($search !== '', function ($q) use ($search): void {
                SmartSearch::apply($q, $search, ['email', 'first_name', 'last_name', 'company_name', 'phone_number']);
            });

        $total = (clone $base)->distinct('email')->count('email');

        $paid = "CASE WHEN status IN ('" . implode("','", self::PAID_STATUSES) . "') THEN total ELSE 0 END";

        $rows = (clone $base)
            ->groupBy('email')
            ->selectRaw("
                email,
                MAX(first_name) as first_name,
                MAX(last_name) as last_name,
                MAX(phone_number) as phone_number,
                MAX(company_name) as company_name,
                COUNT(*) as orders_count,
                SUM($paid) as total_spent,
                MAX(created_at) as last_order_at
            ")
            ->orderByRaw('MAX(created_at) DESC')
            ->forPage($page, $perPage)
            ->get();

        $data = $rows->map(fn ($r) => $this->listRow($r))->all();

        return response()->json([
            'data' => $data,
            'meta' => [
                'total' => $total,
                'per_page' => $perPage,
                'current_page' => $page,
                'last_page' => (int) max(1, ceil($total / $perPage)),
            ],
        ]);
    }

    public function profile(Request $request): JsonResponse
    {
        $email = trim((string) $request->query('email', ''));
        abort_if($email === '', 404, 'Geen e-mailadres opgegeven');

        // Anchor = meest recente bestelling van deze klant op de actieve site. Alle
        // CRM-statistieken (LTV, AOV, klanttype, ...) komen uit CustomerHistory zodat
        // we de rekenlogica niet dupliceren.
        $latest = Order::thisSite()
            ->where('email', $email)
            ->orderByDesc('created_at')
            ->first();

        abort_if($latest === null, 404, 'Klant niet gevonden');

        $history = new CustomerHistory($latest);
        $recent = $history->recentOrders(25);

        return response()->json([
            'data' => [
                'email' => $email,
                'first_name' => $latest->first_name,
                'last_name' => $latest->last_name,
                'name' => trim((string) ($latest->first_name . ' ' . $latest->last_name)) ?: $email,
                'phone_number' => $latest->phone_number,
                'company_name' => $latest->company_name,
                'city' => $latest->city ?: $latest->invoice_city,
                'country' => $latest->country ?: $latest->invoice_country,
                'lifetime_spent' => (float) round($history->lifetimeSpent(), 2),
                'average_order_value' => (float) round($history->averageOrderValue(), 2),
                'paid_count' => $history->paidCount(),
                'total_count' => $history->totalCount(),
                'customer_type' => $history->customerType(),
                'days_since_last_order' => $history->daysSinceLastOrder(),
                'first_order_at' => optional($history->firstOrderAt())->toIso8601String(),
                'last_order_at' => optional($history->lastOrderAt())->toIso8601String(),
                'favorite_payment_method' => $history->favoritePaymentMethod(),
                // Aliassen voor terugwaartse compatibiliteit met de bestaande app-parser.
                'orders_count' => $history->totalCount(),
                'paid_orders_count' => $history->paidCount(),
                'total_spent' => (float) round($history->lifetimeSpent(), 2),
                'recent_orders' => $recent->map(fn ($o) => $this->orderRow($o))->all(),
                // Alias: oudere clients lezen `orders`.
                'orders' => $recent->map(fn ($o) => $this->orderRow($o))->all(),
            ],
        ]);
    }

    private function orderRow(Order $o): array
    {
        return [
            'id' => (int) $o->id,
            'invoice_id' => $o->invoice_id,
            'total' => $o->total !== null ? (float) $o->total : null,
            'status' => $o->status,
            'fulfillment_status' => $o->fulfillment_status,
            'created_at' => optional($o->created_at)->toIso8601String(),
        ];
    }

    private function listRow($r): array
    {
        return [
            'email' => $r->email,
            'name' => trim((string) ($r->first_name . ' ' . $r->last_name)) ?: $r->email,
            'company_name' => $r->company_name,
            'phone_number' => $r->phone_number,
            'orders_count' => (int) $r->orders_count,
            'total_spent' => (float) $r->total_spent,
            'last_order_at' => $r->last_order_at ? (string) $r->last_order_at : null,
        ];
    }
}
