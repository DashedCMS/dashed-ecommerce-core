<?php

declare(strict_types=1);

namespace Dashed\DashedEcommerceCore\Http\Controllers\Api\V1;

use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Dashed\DashedEcommerceCore\Models\Product;
use Dashed\DashedEcommerceCore\Http\Resources\Api\Mobile\ProductResource;

class ProductController extends Controller
{
    private const SORTABLE = ['name', 'price', 'stock', 'total_purchases'];

    public function index(Request $request): AnonymousResourceCollection
    {
        $query = Product::thisSite();

        if ($request->has('public')) {
            $query->where('public', $request->boolean('public'));
        }

        if ($groupId = $request->query('product_group_id')) {
            $query->where('product_group_id', (int) $groupId);
        }

        if ($categoryId = $request->query('product_category_id')) {
            $query->whereHas('productCategories', function ($q) use ($categoryId): void {
                $q->whereKey((int) $categoryId);
            });
        }

        if ($search = $request->query('search')) {
            $query->search((string) $search);
        }

        // Lage/negatieve voorraad: voorraad op of onder de drempel, of <= 0.
        if ($request->boolean('low_stock')) {
            $query->where('use_stock', true)
                ->where(function ($q): void {
                    $q->whereColumn('stock', '<=', 'low_stock_notification_limit')
                        ->orWhere('stock', '<=', 0);
                });
        }

        $sort = (string) $request->query('sort', '');
        if (in_array($sort, self::SORTABLE, true)) {
            $direction = strtolower((string) $request->query('direction', 'asc')) === 'desc' ? 'desc' : 'asc';
            $query->orderBy($sort, $direction);
        } else {
            // Stabiele volgorde voor betrouwbare paginatie (infinite scroll).
            $query->orderBy('id');
        }

        $perPage = (int) config('dashed-mobile-api.default_page_size', 25);

        return ProductResource::collection($query->paginate($perPage));
    }

    public function show(int $product): ProductResource
    {
        return new ProductResource(Product::thisSite()->findOrFail($product));
    }

    public function update(Request $request, int $product): ProductResource
    {
        $model = Product::thisSite()->findOrFail($product);

        $data = $request->validate([
            'price' => ['sometimes', 'numeric', 'min:0'],
            'public' => ['sometimes', 'boolean'],

            // Voorraad
            'use_stock' => ['sometimes', 'boolean'],
            'stock' => ['sometimes', 'integer', 'min:0'],
            'low_stock_notification' => ['sometimes', 'boolean'],
            'low_stock_notification_limit' => ['sometimes', 'nullable', 'integer', 'min:1'],
            'out_of_stock_sellable' => ['sometimes', 'boolean'],
            'expected_in_stock_date' => ['sometimes', 'nullable', 'date'],
            'expected_delivery_in_days' => ['sometimes', 'nullable', 'integer', 'min:1'],
            'stock_status' => ['sometimes', 'in:in_stock,out_of_stock'],
            'limit_purchases_per_customer' => ['sometimes', 'boolean'],
            'limit_purchases_per_customer_limit' => ['sometimes', 'nullable', 'integer', 'min:1'],

            // Praktische informatie
            'purchase_price' => ['sometimes', 'nullable', 'numeric', 'min:0'],
            'vat_rate' => ['sometimes', 'numeric', 'min:0', 'max:100'],
            'sku' => ['sometimes', 'string', 'max:255'],
            'ean' => ['sometimes', 'nullable', 'string', 'max:255'],
            'article_code' => ['sometimes', 'nullable', 'string', 'max:255'],
            'weight' => ['sometimes', 'nullable', 'integer', 'min:0'],
            'length' => ['sometimes', 'nullable', 'integer', 'min:0'],
            'width' => ['sometimes', 'nullable', 'integer', 'min:0'],
            'height' => ['sometimes', 'nullable', 'integer', 'min:0'],
        ]);

        $model->fill($data)->save();

        activity()
            ->performedOn($model)
            ->causedBy($request->user())
            ->withProperties($data)
            ->log('mobile-api: product bijgewerkt');

        return new ProductResource($model->fresh());
    }
}
