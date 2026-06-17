<?php

declare(strict_types=1);

namespace Dashed\DashedEcommerceCore\Http\Controllers\Api\V1;

use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Http\JsonResponse;
use Illuminate\Routing\Controller;
use Dashed\DashedCore\Classes\Sites;
use Dashed\DashedEcommerceCore\Models\DiscountCode;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Dashed\DashedEcommerceCore\Http\Resources\Api\Mobile\DiscountCodeResource;

/**
 * Kortingscode-beheer voor de app (focus-set). Site-scoping loopt via de
 * `site_ids`-JSON-array op het model (whereJsonContains op de actieve site).
 */
class DiscountController extends Controller
{
    public function index(Request $request): AnonymousResourceCollection
    {
        $query = DiscountCode::query()
            ->whereJsonContains('site_ids', Sites::getActive());

        if ($search = trim((string) $request->query('search', ''))) {
            $like = '%' . $search . '%';
            $query->where(function ($q) use ($like): void {
                $q->where('name', 'like', $like)
                    ->orWhere('code', 'like', $like);
            });
        }

        return DiscountCodeResource::collection(
            $query->orderByDesc('id')->get(),
        );
    }

    public function show(int $id): DiscountCodeResource
    {
        return new DiscountCodeResource($this->findForSite($id));
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate($this->rules($request));

        $discountCode = new DiscountCode();
        $this->fill($discountCode, $data);
        $discountCode->site_ids = [Sites::getActive()];
        $discountCode->save();

        return (new DiscountCodeResource($discountCode->fresh()))
            ->response()
            ->setStatusCode(201);
    }

    public function update(Request $request, int $id): DiscountCodeResource
    {
        $discountCode = $this->findForSite($id);

        $data = $request->validate($this->rules($request, $id));

        $this->fill($discountCode, $data);
        $discountCode->save();

        return new DiscountCodeResource($discountCode->fresh());
    }

    public function destroy(int $id): JsonResponse
    {
        $this->findForSite($id)->delete();

        return response()->json(['success' => true]);
    }

    private function findForSite(int $id): DiscountCode
    {
        return DiscountCode::query()
            ->whereJsonContains('site_ids', Sites::getActive())
            ->findOrFail($id);
    }

    /**
     * @return array<string, mixed>
     */
    private function rules(Request $request, ?int $ignoreId = null): array
    {
        $type = $request->input('type');

        $codeRule = Rule::unique('dashed__discount_codes', 'code')->whereNull('deleted_at');
        if ($ignoreId !== null) {
            $codeRule->ignore($ignoreId);
        }

        return [
            'name' => ['required', 'string', 'max:255'],
            'code' => ['required', 'string', 'max:255', $codeRule],
            'type' => ['required', Rule::in(['percentage', 'amount'])],
            'discount_percentage' => [
                Rule::requiredIf($type === 'percentage'),
                'nullable',
                'numeric',
                'min:0',
                'max:100',
            ],
            'discount_amount' => [
                Rule::requiredIf($type === 'amount'),
                'nullable',
                'numeric',
                'gt:0',
            ],
            'start_date' => ['nullable', 'date'],
            'end_date' => ['nullable', 'date', 'after:start_date'],
            'use_stock' => ['sometimes', 'boolean'],
            'stock' => [
                Rule::requiredIf($request->boolean('use_stock')),
                'nullable',
                'integer',
                'min:0',
            ],
        ];
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function fill(DiscountCode $discountCode, array $data): void
    {
        $useStock = (bool) ($data['use_stock'] ?? false);

        $discountCode->name = $data['name'];
        $discountCode->code = $data['code'];
        $discountCode->type = $data['type'];
        $discountCode->discount_percentage = $data['type'] === 'percentage' ? $data['discount_percentage'] : null;
        $discountCode->discount_amount = $data['type'] === 'amount' ? $data['discount_amount'] : null;
        $discountCode->start_date = $data['start_date'] ?? null;
        $discountCode->end_date = $data['end_date'] ?? null;
        $discountCode->use_stock = $useStock;
        $discountCode->stock = $useStock ? ($data['stock'] ?? null) : null;
    }
}
