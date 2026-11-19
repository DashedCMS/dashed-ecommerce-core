<?php

declare(strict_types=1);

namespace Dashed\DashedEcommerceCore\Models;

use Dashed\DashedCore\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Een eigen productreview. Hangt aan de productgroep als het product er een
 * heeft (alle varianten delen dan dezelfde reviews), anders aan het product;
 * `product_id` bewaart altijd welke variant er gekocht is. Een review met
 * `order_product_id` komt van een geverifieerde koper via het verzoek na
 * levering.
 */
class ProductReview extends Model
{
    public const STATUS_PENDING = 'pending';

    public const STATUS_APPROVED = 'approved';

    public const STATUS_REJECTED = 'rejected';

    protected $table = 'dashed__product_reviews';

    protected $guarded = [];

    protected $casts = [
        'rating' => 'integer',
        'verified_buyer' => 'boolean',
        'replied_at' => 'datetime',
        'approved_at' => 'datetime',
    ];

    /**
     * De productpagina staat in de paginacache. Alleen als er iets verandert
     * dat op de pagina staat (iets goedgekeurds, of iets dat goedgekeurd
     * was) wordt de pagina van het product en zijn groep vergeten.
     */
    protected static function booted(): void
    {
        $forget = function (self $record): void {
            $visible = $record->status === self::STATUS_APPROVED || $record->getOriginal('status') === self::STATUS_APPROVED;

            if (! $visible || ! class_exists(\Dashed\DashedCore\Classes\Caching\CacheInvalidator::class)) {
                return;
            }

            if ($record->exists && ! $record->wasRecentlyCreated && ! $record->wasChanged(['status', 'rating', 'title', 'body', 'name', 'reply'])) {
                return;
            }

            rescue(function () use ($record): void {
                if ($record->product) {
                    \Dashed\DashedCore\Classes\Caching\CacheInvalidator::forModel($record->product);
                }
                if ($record->productGroup) {
                    \Dashed\DashedCore\Classes\Caching\CacheInvalidator::forModel($record->productGroup);
                }
            }, report: false);
        };

        static::saved($forget);
        static::deleted($forget);
    }

    public static function statuses(): array
    {
        return [
            self::STATUS_PENDING => __('Wacht op beoordeling'),
            self::STATUS_APPROVED => __('Goedgekeurd'),
            self::STATUS_REJECTED => __('Afgewezen'),
        ];
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class)->withTrashed();
    }

    public function productGroup(): BelongsTo
    {
        return $this->belongsTo(ProductGroup::class);
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function orderProduct(): BelongsTo
    {
        return $this->belongsTo(OrderProduct::class);
    }

    public function repliedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'replied_by');
    }

    public function scopeApproved(Builder $query): Builder
    {
        return $query->where('status', self::STATUS_APPROVED);
    }

    /**
     * De reviews van de groep van dit product, of van het losse product.
     */
    public function scopeForProduct(Builder $query, Product $product): Builder
    {
        return $product->product_group_id
            ? $query->where('product_group_id', $product->product_group_id)
            : $query->where('product_id', $product->getKey());
    }

    public function approve(): void
    {
        $this->forceFill(['status' => self::STATUS_APPROVED, 'approved_at' => $this->approved_at ?: now()])->save();
    }

    public function reject(): void
    {
        $this->forceFill(['status' => self::STATUS_REJECTED])->save();
    }
}
