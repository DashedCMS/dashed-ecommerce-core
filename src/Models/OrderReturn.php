<?php

namespace Dashed\DashedEcommerceCore\Models;

use Illuminate\Support\Str;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class OrderReturn extends Model
{
    public const STATUS_REQUESTED = 'requested';
    public const STATUS_APPROVED = 'approved';
    public const STATUS_REJECTED = 'rejected';
    public const STATUS_HANDLED = 'handled';

    protected $table = 'dashed__order_returns';

    protected $guarded = [];

    protected $casts = [
        'requested_at' => 'datetime',
        'approved_at' => 'datetime',
        'rejected_at' => 'datetime',
        'handled_at' => 'datetime',
    ];

    protected static function booted(): void
    {
        static::creating(function (OrderReturn $return) {
            if (! $return->hash) {
                $return->hash = Str::random(32);
            }
            if (! $return->status) {
                $return->status = self::STATUS_REQUESTED;
            }
            if (! $return->requested_at) {
                $return->requested_at = now();
            }
        });
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function scopeRequested(Builder $query): Builder
    {
        return $query->where('status', self::STATUS_REQUESTED);
    }

    public function scopeOpen(Builder $query): Builder
    {
        return $query->whereNotIn('status', [self::STATUS_REJECTED, self::STATUS_HANDLED]);
    }

    public static function statusLabels(): array
    {
        return [
            self::STATUS_REQUESTED => __('Aangevraagd'),
            self::STATUS_APPROVED => __('Goedgekeurd'),
            self::STATUS_REJECTED => __('Afgekeurd'),
            self::STATUS_HANDLED => __('Afgehandeld'),
        ];
    }

    public function statusLabel(): string
    {
        return self::statusLabels()[$this->status] ?? $this->status;
    }
}
