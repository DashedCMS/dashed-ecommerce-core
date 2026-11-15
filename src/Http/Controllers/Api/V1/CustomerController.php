<?php

declare(strict_types=1);

namespace Dashed\DashedEcommerceCore\Http\Controllers\Api\V1;

use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Dashed\DashedCore\Models\User;
use Dashed\DashedCore\Classes\Sites;
use Illuminate\Routing\Controller;
use Dashed\DashedEcommerceCore\Models\Order;
use Dashed\DashedEcommerceCore\Support\SmartSearch;
use Dashed\DashedEcommerceCore\Classes\CustomerHistory;
use Dashed\DashedEcommerceCore\Models\CustomerAnnotation;
use Dashed\DashedCore\Classes\QueryHelpers\TokenizedSearch;

/**
 * Klanten = aggregatie van bestellingen op e-mailadres (er is geen los klant-
 * model). Levert een doorzoekbare lijst en een 360°-profiel met bestelhistorie.
 */
class CustomerController extends Controller
{
    private const PAID_STATUSES = ['paid', 'waiting_for_confirmation', 'partially_paid'];

    /**
     * Zoek échte klant-accounts (User) met hun id — voor het koppelen van een
     * account aan de kassa-bon (customer_user_id). Spiegelt de Filament POS
     * "Account"-select: TokenizedSearch over first_name/last_name/email. In
     * tegenstelling tot index() (e-mail-aggregatie zonder id) geeft dit de
     * user-id terug zodat de app kan koppelen.
     */
    public function accounts(Request $request): JsonResponse
    {
        $search = trim((string) $request->query('search', ''));
        if (mb_strlen($search) < 2) {
            return response()->json(['data' => []]);
        }

        $users = TokenizedSearch::apply(User::query(), $search, ['first_name', 'last_name', 'email'])
            ->limit(25)
            ->get(['id', 'first_name', 'last_name', 'email', 'company']);

        return response()->json([
            'data' => $users->map(fn (User $u) => [
                'id' => $u->id,
                'name' => $u->name,
                'email' => $u->email,
                'company' => $u->company,
            ])->values(),
        ]);
    }

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
                // Chronologische activiteitstijdlijn (plaatsingen + orderlogs) over
                // de orders van deze klant. Oudere clients negeren dit veld simpelweg.
                'timeline' => $this->buildTimeline($email),
                // Notitie + vrije-tekst tags van deze klant. Oudere clients negeren dit.
                'annotation' => $this->annotationPayload($email),
            ],
        ]);
    }

    /**
     * Haal de notitie + tags van één klant op (los endpoint, zodat het
     * annotatie-paneel ook buiten het volledige profiel te laden is).
     */
    public function annotation(Request $request): JsonResponse
    {
        $email = trim((string) $request->query('email', ''));
        abort_if($email === '', 404, 'Geen e-mailadres opgegeven');

        return response()->json(['data' => $this->annotationPayload($email)]);
    }

    /**
     * Sla de notitie + tags van één klant op (upsert op site_id + email). Een
     * lege notitie en geen tags verwijderen de annotatie weer.
     */
    public function saveAnnotation(Request $request): JsonResponse
    {
        $data = $request->validate([
            'email' => ['required', 'string', 'email'],
            'notes' => ['nullable', 'string', 'max:5000'],
            'tags' => ['nullable', 'array', 'max:40'],
            'tags.*' => ['string', 'max:60'],
        ]);

        $email = trim((string) $data['email']);
        $notes = isset($data['notes']) ? trim((string) $data['notes']) : '';
        $tags = $this->cleanTags($data['tags'] ?? []);
        $siteId = (string) Sites::getActive();

        if ($notes === '' && $tags === []) {
            CustomerAnnotation::query()
                ->where('site_id', $siteId)
                ->where('email', $email)
                ->delete();

            return response()->json(['data' => $this->annotationPayload($email)]);
        }

        CustomerAnnotation::query()->updateOrCreate(
            ['site_id' => $siteId, 'email' => $email],
            ['notes' => $notes !== '' ? $notes : null, 'tags' => $tags ?: null],
        );

        return response()->json(['data' => $this->annotationPayload($email)]);
    }

    /**
     * Notitie + tags van deze klant, plus eerder gebruikte tags op deze site als
     * suggesties voor de invoer.
     */
    private function annotationPayload(string $email): array
    {
        $siteId = (string) Sites::getActive();

        $annotation = CustomerAnnotation::query()
            ->where('site_id', $siteId)
            ->where('email', $email)
            ->first();

        return [
            'email' => $email,
            'notes' => $annotation?->notes,
            'tags' => array_values((array) ($annotation?->tags ?? [])),
            'updated_at' => optional($annotation?->updated_at)->toIso8601String(),
            'tag_suggestions' => $this->tagSuggestions($siteId),
        ];
    }

    /** Distinct, alfabetisch gesorteerde tags die op deze site al eens gebruikt zijn. */
    private function tagSuggestions(string $siteId): array
    {
        $all = CustomerAnnotation::query()
            ->where('site_id', $siteId)
            ->whereNotNull('tags')
            ->pluck('tags')
            ->flatMap(fn ($tags) => (array) $tags)
            ->map(fn ($tag) => trim((string) $tag))
            ->filter()
            ->unique()
            ->sort()
            ->values()
            ->all();

        return array_slice($all, 0, 50);
    }

    /** Trim, ontdubbel (case-insensitief) en verwijder lege tags. */
    private function cleanTags(array $tags): array
    {
        $seen = [];
        $clean = [];
        foreach ($tags as $tag) {
            $tag = trim((string) $tag);
            if ($tag === '') {
                continue;
            }
            $key = mb_strtolower($tag);
            if (isset($seen[$key])) {
                continue;
            }
            $seen[$key] = true;
            $clean[] = $tag;
        }

        return $clean;
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

    /**
     * Bouwt een samengevoegde, aflopend-chronologische tijdlijn uit de orders van
     * een klant: een "geplaatst"-event per order plus elk orderlog-event (status-
     * wijzigingen, mails, notities, retouren — het log is de event-stroom). Puur
     * read-only aggregatie binnen ec-core; geen schema-wijziging.
     */
    private function buildTimeline(string $email): array
    {
        $orders = Order::thisSite()
            ->where('email', $email)
            ->with('logs')
            ->orderByDesc('created_at')
            ->limit(25)
            ->get();

        $events = [];
        foreach ($orders as $order) {
            $ref = $order->invoice_id ?: ('#' . $order->id);

            if ($order->created_at) {
                $events[] = [
                    'type' => 'order_placed',
                    'order_id' => (int) $order->id,
                    'order_ref' => $ref,
                    'amount' => $order->total !== null ? (float) $order->total : null,
                    'at' => $order->created_at->toIso8601String(),
                ];
            }

            foreach ($order->logs as $log) {
                $tag = trim((string) ($log->tag ?? ''));
                $note = trim((string) ($log->note ?? ''));
                if (($tag === '' && $note === '') || ! $log->created_at) {
                    continue;
                }
                $events[] = [
                    'type' => 'log',
                    'order_id' => (int) $order->id,
                    'order_ref' => $ref,
                    'tag' => $tag !== '' ? $tag : null,
                    'note' => $note !== '' ? $note : null,
                    'at' => $log->created_at->toIso8601String(),
                ];
            }
        }

        usort($events, fn ($a, $b) => strcmp($b['at'], $a['at']));

        return array_slice($events, 0, 60);
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
