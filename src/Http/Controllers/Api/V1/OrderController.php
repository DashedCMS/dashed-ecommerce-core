<?php

declare(strict_types=1);

namespace Dashed\DashedEcommerceCore\Http\Controllers\Api\V1;

use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Validation\Rule;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Dashed\DashedEcommerceCore\Models\Order;
use Dashed\DashedEcommerceCore\Http\Resources\Api\Mobile\OrderResource;
use Dashed\DashedEcommerceCore\Http\Resources\Api\Mobile\OrderSummaryResource;

class OrderController extends Controller
{
    private const CHANGEABLE_STATUSES = ['paid', 'partially_paid', 'cancelled', 'waiting_for_confirmation'];

    public function index(Request $request): AnonymousResourceCollection
    {
        $query = Order::thisSite();

        if ($request->boolean('unhandled')) {
            $query->unhandled();
        }

        if ($status = $request->query('status')) {
            $query->where('status', (string) $status);
        }

        $perPage = (int) config('dashed-mobile-api.default_page_size', 25);

        return OrderSummaryResource::collection(
            $query->orderByDesc('created_at')->paginate($perPage),
        );
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
}
