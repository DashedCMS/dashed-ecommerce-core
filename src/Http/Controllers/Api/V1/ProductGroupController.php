<?php

declare(strict_types=1);

namespace Dashed\DashedEcommerceCore\Http\Controllers\Api\V1;

use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Dashed\DashedEcommerceCore\Models\ProductGroup;
use Dashed\DashedEcommerceCore\Http\Resources\Api\Mobile\ProductGroupResource;

class ProductGroupController extends Controller
{
    public function index(Request $request): AnonymousResourceCollection
    {
        $query = ProductGroup::thisSite()->withCount('products');

        if ($search = $request->query('search')) {
            $query->where('name->nl', 'like', '%' . (string) $search . '%');
        }

        $perPage = (int) config('dashed-mobile-api.default_page_size', 25);

        return ProductGroupResource::collection($query->paginate($perPage));
    }

    public function show(int $productGroup): ProductGroupResource
    {
        $group = ProductGroup::thisSite()
            ->with(['products' => fn ($q) => $q->thisSite()])
            ->findOrFail($productGroup);

        return new ProductGroupResource($group);
    }
}
