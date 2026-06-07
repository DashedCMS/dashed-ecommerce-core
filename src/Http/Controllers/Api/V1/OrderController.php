<?php

declare(strict_types=1);

namespace Dashed\DashedEcommerceCore\Http\Controllers\Api\V1;

use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Routing\Controller;
use Illuminate\Validation\Rule;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Dashed\DashedEcommerceCore\Classes\Orders;
use Dashed\DashedEcommerceCore\Models\Order;
use Dashed\DashedEcommerceCore\Http\Resources\Api\Mobile\OrderResource;
use Dashed\DashedEcommerceCore\Http\Resources\Api\Mobile\OrderSummaryResource;

class OrderController extends Controller
{
    private const CHANGEABLE_STATUSES = ['paid', 'partially_paid', 'cancelled', 'waiting_for_confirmation'];

    /**
     * Betaalstatus-opties — gelijk aan de Filament order-resource.
     */
    private const STATUS_OPTIONS = [
        'paid' => 'Betaald',
        'partially_paid' => 'Gedeeltelijk betaald',
        'waiting_for_confirmation' => 'Wachten op bevestiging',
        'pending' => 'Lopende aankoop',
        'concept' => 'Concept',
        'cancelled' => 'Geannuleerd',
        'return' => 'Retour',
    ];

    public function index(Request $request): AnonymousResourceCollection
    {
        $query = Order::thisSite();

        // Dashboard-shortcut: alleen onafgehandelde orders.
        if ($request->boolean('unhandled')) {
            $query->unhandled();
        }

        $this->applyArrayFilter($query, 'status', $request->query('status'));
        $this->applyFulfillmentFilter($query, $request->query('fulfillment_status'));
        $this->applyArrayFilter($query, 'retour_status', $request->query('retour_status'));
        $this->applyArrayFilter($query, 'order_origin', $request->query('order_origin'));
        $this->applyArrayFilter($query, 'utm_source', $request->query('utm_source'));
        $this->applyArrayFilter($query, 'utm_medium', $request->query('utm_medium'));
        $this->applyArrayFilter($query, 'utm_campaign', $request->query('utm_campaign'));
        $this->applyArrayFilter($query, 'country', $request->query('country'));

        if ($startDate = $request->query('start_date')) {
            $query->whereDate('created_at', '>=', $startDate);
        }
        if ($endDate = $request->query('end_date')) {
            $query->whereDate('created_at', '<=', $endDate);
        }

        if ($search = trim((string) $request->query('search'))) {
            $query->where(function (Builder $q) use ($search): void {
                foreach (['invoice_id', 'first_name', 'last_name', 'email', 'company_name', 'city'] as $column) {
                    $q->orWhere($column, 'like', "%{$search}%");
                }
                if (is_numeric($search)) {
                    $q->orWhere('id', (int) $search);
                }
            });
        }

        $perPage = (int) config('dashed-mobile-api.default_page_size', 25);

        return OrderSummaryResource::collection(
            $query->orderByDesc('created_at')->paginate($perPage),
        );
    }

    /**
     * Beschikbare filteropties (statisch + dynamisch per site), zodat de app de
     * filter-UI kan vullen — gelijk aan de Filament order-resource.
     */
    public function filterOptions(Request $request): JsonResponse
    {
        return response()->json([
            'data' => [
                'statuses' => $this->mapOptions(self::STATUS_OPTIONS),
                'fulfillment_statuses' => $this->mapOptions(Orders::getFulfillmentStatusses()),
                'retour_statuses' => $this->mapOptions(Orders::getReturnStatusses()),
                'order_origins' => $this->distinctOptions('order_origin'),
                'utm_sources' => $this->distinctOptions('utm_source'),
                'utm_mediums' => $this->distinctOptions('utm_medium'),
                'utm_campaigns' => $this->distinctOptions('utm_campaign'),
                'countries' => $this->distinctOptions('country'),
            ],
        ]);
    }

    public function show(int $order): OrderResource
    {
        $model = Order::thisSite()->findOrFail($order);

        return new OrderResource($model->load('orderProducts'));
    }

    public function update(Request $request, int $order): OrderResource
    {
        $model = Order::thisSite()->findOrFail($order);

        $data = $request->validate([
            'status' => ['required', 'string', Rule::in(self::CHANGEABLE_STATUSES)],
        ]);

        $model->changeStatus($data['status']);

        activity()
            ->performedOn($model)
            ->causedBy($request->user())
            ->withProperties($data)
            ->log('mobile-api: orderstatus gewijzigd');

        return new OrderResource($model->fresh()->load('orderProducts'));
    }

    private function applyArrayFilter(Builder $query, string $column, mixed $value): void
    {
        $values = $this->toList($value);
        if ($values) {
            $query->whereIn($column, $values);
        }
    }

    private function applyFulfillmentFilter(Builder $query, mixed $value): void
    {
        $values = $this->toList($value);
        if (! $values) {
            return;
        }

        // 'unhandled_virtual' (en de widget-shortcut 'unhandled') = alles behalve afgehandeld.
        if (in_array('unhandled_virtual', $values, true)) {
            $query->whereNotIn('fulfillment_status', ['handled', 'partially_handled']);

            return;
        }

        $query->whereIn('fulfillment_status', $values);
    }

    /**
     * @return array<int, string>
     */
    private function toList(mixed $value): array
    {
        if (is_array($value)) {
            $items = $value;
        } elseif (is_string($value) && $value !== '') {
            $items = explode(',', $value);
        } else {
            return [];
        }

        return array_values(array_filter(array_map('trim', $items), fn ($v) => $v !== ''));
    }

    /**
     * @param array<string, string> $map
     * @return array<int, array{value: string, label: string}>
     */
    private function mapOptions(array $map): array
    {
        $out = [];
        foreach ($map as $value => $label) {
            $out[] = ['value' => (string) $value, 'label' => (string) $label];
        }

        return $out;
    }

    /**
     * @return array<int, array{value: string, label: string}>
     */
    private function distinctOptions(string $column): array
    {
        return Order::thisSite()
            ->whereNotNull($column)
            ->where($column, '!=', '')
            ->distinct()
            ->orderBy($column)
            ->pluck($column)
            ->map(fn ($v) => ['value' => (string) $v, 'label' => ucfirst((string) $v)])
            ->values()
            ->all();
    }
}
