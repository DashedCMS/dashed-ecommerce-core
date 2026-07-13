<?php

declare(strict_types=1);

namespace Dashed\DashedEcommerceCore\Http\Controllers\Api\V1;

use Illuminate\Http\Request;
use Dashed\DashedEcommerceCore\Support\SmartSearch;
use Illuminate\Routing\Controller;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Dashed\DashedEcommerceCore\Models\ProductGroup;
use Dashed\DashedEcommerceCore\Models\ProductCategory;
use Dashed\DashedEcommerceCore\Http\Resources\Api\Mobile\ProductGroupResource;
use Dashed\DashedEcommerceCore\Http\Resources\Api\Mobile\ProductCategoryResource;

class ProductGroupController extends Controller
{
    public function index(Request $request): AnonymousResourceCollection
    {
        $query = ProductGroup::thisSite()->withCount('products');

        SmartSearch::apply($query, $request->query('search'), ['name->nl', 'slug->nl']);

        // Filter op één of meerdere productcategorieën (komma-gescheiden).
        $categoryIds = array_values(array_filter(
            array_map('trim', explode(',', (string) $request->query('product_category_id'))),
            fn ($v) => $v !== '',
        ));
        if ($categoryIds) {
            $query->whereHas('productCategories', fn (Builder $q) => $q->whereIn('product_category_id', $categoryIds));
        }

        $perPage = (int) config('dashed-mobile-api.default_page_size', 25);

        return ProductGroupResource::collection($query->paginate($perPage));
    }

    /**
     * Beschikbare productcategorieën voor de actieve site, zodat de app het
     * categorie-filter kan vullen.
     */
    public function categories(): AnonymousResourceCollection
    {
        return ProductCategoryResource::collection(
            ProductCategory::thisSite()
                ->orderBy('name->nl')
                ->get(),
        );
    }

    public function show(int $productGroup): ProductGroupResource
    {
        $group = ProductGroup::thisSite()
            ->with(['products' => fn ($q) => $q->thisSite()])
            ->findOrFail($productGroup);

        return new ProductGroupResource($group);
    }
}
