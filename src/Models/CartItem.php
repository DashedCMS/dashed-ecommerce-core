<?php

namespace Dashed\DashedEcommerceCore\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CartItem extends Model
{
    protected $table = 'dashed__cart_items';

    protected $fillable = [
        'cart_id',
        'product_id',
        'name',
        'unit_price',
        'quantity',
        'options',
    ];

    protected $casts = [
        'options' => 'array',
        'unit_price' => 'float',
        'quantity' => 'int',
    ];

    public function cart(): BelongsTo
    {
        return $this->belongsTo(Cart::class, 'cart_id');
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(\Dashed\DashedEcommerceCore\Models\Product::class, 'product_id');
    }

    public function isCustom(): bool
    {
        return (bool) data_get($this->options, 'customProduct', false) || $this->product_id === null;
    }
}
