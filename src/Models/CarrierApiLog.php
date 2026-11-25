<?php

namespace Dashed\DashedEcommerceCore\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Prunable;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One request to a shipping carrier API (Veloyd, MyParcel, ...): what was
 * sent and what came back. Written by CarrierApiLogger, pruned after
 * RETENTION_DAYS by the daily model:prune run.
 */
class CarrierApiLog extends Model
{
    use Prunable;

    public const RETENTION_DAYS = 60;

    public const UPDATED_AT = null;

    protected $table = 'carrier_api_logs';

    protected $guarded = [];

    protected $casts = [
        'order_id' => 'integer',
        'response_status' => 'integer',
        'duration_ms' => 'integer',
        'created_at' => 'datetime',
    ];

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function prunable(): Builder
    {
        return static::where('created_at', '<', now()->subDays(self::RETENTION_DAYS));
    }

    /** Failed = no response at all (network error, timeout) or an HTTP status of 400 or higher. */
    public function failed(): bool
    {
        return $this->response_status === null || $this->response_status >= 400;
    }

    public function scopeFailed(Builder $query): Builder
    {
        return $query->where(fn (Builder $q) => $q->whereNull('response_status')->orWhere('response_status', '>=', 400));
    }

    /** Body pretty-printed when it is JSON, otherwise as stored. */
    public static function prettyBody(?string $body): string
    {
        if ($body === null || $body === '') {
            return '';
        }

        $decoded = json_decode($body, true);
        if (json_last_error() === JSON_ERROR_NONE && (is_array($decoded))) {
            return (string) json_encode($decoded, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        }

        return $body;
    }
}
