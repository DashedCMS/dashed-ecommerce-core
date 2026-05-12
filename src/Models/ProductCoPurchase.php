<?php

namespace Dashed\DashedEcommerceCore\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Materialized "X was bought with Y" pair counts. Refreshed nightly by
 * `PrecomputeCoPurchaseScoresJob`. Read by `FrequentlyBoughtTogetherStrategy`
 * to surface candidate recommendations without scanning all orders live.
 *
 * `product_a_id < product_b_id` is the canonical order so each pair has
 * exactly one row. The job enforces this invariant; queries that look
 * up "products bought with X" must check both columns and merge.
 */
class ProductCoPurchase extends Model
{
    protected $table = 'dashed__product_co_purchase';

    protected $fillable = [
        'product_a_id',
        'product_b_id',
        'co_count',
        'score',
        'last_computed_at',
    ];

    protected $casts = [
        'score' => 'decimal:4',
        'last_computed_at' => 'datetime',
        'co_count' => 'integer',
    ];

    public function productA(): BelongsTo
    {
        return $this->belongsTo(Product::class, 'product_a_id');
    }

    public function productB(): BelongsTo
    {
        return $this->belongsTo(Product::class, 'product_b_id');
    }
}
