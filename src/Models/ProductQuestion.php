<?php

declare(strict_types=1);

namespace Dashed\DashedEcommerceCore\Models;

use Dashed\DashedCore\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Een vraag van een bezoeker over een product. Pas zichtbaar op de
 * productpagina als hij beantwoord én goedgekeurd is; de vraagsteller krijgt
 * het antwoord per mail, ook als de vraag niet getoond wordt.
 */
class ProductQuestion extends Model
{
    public const STATUS_PENDING = 'pending';

    public const STATUS_APPROVED = 'approved';

    public const STATUS_REJECTED = 'rejected';

    protected $table = 'dashed__product_questions';

    protected $guarded = [];

    protected $casts = [
        'answered_at' => 'datetime',
        'customer_notified_at' => 'datetime',
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

            if ($record->exists && ! $record->wasRecentlyCreated && ! $record->wasChanged(['status', 'question', 'answer', 'name'])) {
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
            self::STATUS_APPROVED => __('Tonen op productpagina'),
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

    public function answeredBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'answered_by');
    }

    /** Beantwoord en goedgekeurd: alleen die staan op de productpagina. */
    public function scopeVisible(Builder $query): Builder
    {
        return $query->where('status', self::STATUS_APPROVED)->whereNotNull('answer')->where('answer', '!=', '');
    }

    public function scopeForProduct(Builder $query, Product $product): Builder
    {
        return $product->product_group_id
            ? $query->where('product_group_id', $product->product_group_id)
            : $query->where('product_id', $product->getKey());
    }
}
