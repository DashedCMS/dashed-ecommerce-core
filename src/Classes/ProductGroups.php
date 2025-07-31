<?php

namespace Dashed\DashedEcommerceCore\Classes;

use Illuminate\Support\Facades\DB;
use Dashed\DashedCore\Models\Customsetting;
use Illuminate\Database\Eloquent\Collection;
use Dashed\DashedEcommerceCore\Models\Product;
use Dashed\DashedEcommerceCore\Models\ProductGroup;
use Dashed\DashedEcommerceCore\Models\ProductCategory;

class ProductGroups
{
    public static function get($limit = 4, ?string $orderBy = null, ?string $order = null)
    {
        $orderByRequest = request()->get('sort-by');
        if ($orderByRequest) {
            if ($orderByRequest == 'price-asc') {
                $orderBy = 'price';
                $order = 'ASC';
            } elseif ($orderByRequest == 'price-desc') {
                $orderBy = 'price';
                $order = 'DESC';
            } elseif ($orderByRequest == 'most-sold') {
                $orderBy = 'purchases';
                $order = 'DESC';
            } elseif ($orderByRequest == 'stock') {
                $orderBy = 'stock';
                $order = 'DESC';
            } elseif ($orderByRequest == 'newest') {
                $orderBy = 'created_at';
                $order = 'DESC';
            } elseif ($orderByRequest == 'order') {
                $orderBy = 'order';
                $order = 'ASC';
            }
        }

        if (! $orderBy) {
            $orderBy = Customsetting::get('product_default_order_type', null, 'price');
        }

        if (! $order) {
            $order = Customsetting::get('product_default_order_sort', null, 'DESC');
        }

        return ProductGroup::search()->publicShowable()->limit($limit)->orderBy($orderBy, $order)->get();
    }
}
