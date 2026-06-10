<?php

namespace Dashed\DashedEcommerceCore\Models;

use Illuminate\Database\Eloquent\Model;

class ProductFinder extends Model
{
    protected $table = 'dashed__product_finders';

    protected $guarded = [];

    protected $casts = [
        'is_active' => 'boolean',
        'questions' => 'array',
        'category_ids' => 'array',
        'only_in_stock' => 'boolean',
        'result_count' => 'integer',
    ];
}
