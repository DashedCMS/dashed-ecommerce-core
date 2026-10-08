<?php

namespace Dashed\DashedEcommerceCore\Models;

use Illuminate\Database\Eloquent\Model;
use Dashed\DashedEcommerceCore\Jobs\SendMetaPurchaseEventJob;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Logboek en idempotentiesleutel van events naar Meta's Conversions API. */
class MetaCapiEvent extends Model
{
    public const STATUS_PENDING = 'pending';
    public const STATUS_SENT = 'sent';
    public const STATUS_FAILED = 'failed';
    public const STATUS_SKIPPED = 'skipped';

    protected $table = 'dashed__meta_capi_events';

    protected $guarded = [];

    protected $attributes = [
        'status' => self::STATUS_PENDING,
        'attempts' => 0,
    ];

    protected $casts = [
        'payload' => 'array',
        'response' => 'array',
        'attempts' => 'integer',
        'sent_at' => 'datetime',
    ];

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public static function statusLabel(string $status): string
    {
        return match ($status) {
            self::STATUS_PENDING => __('In de wachtrij'),
            self::STATUS_SENT => __('Verstuurd'),
            self::STATUS_FAILED => __('Mislukt'),
            self::STATUS_SKIPPED => __('Overgeslagen'),
            default => $status,
        };
    }

    public function canResend(): bool
    {
        return $this->order_id !== null
            && in_array($this->status, [self::STATUS_FAILED, self::STATUS_SKIPPED], true);
    }

    /** Zet het event opnieuw in de wachtrij; de job beslist opnieuw of het mag. */
    public function resend(): bool
    {
        if (! $this->canResend()) {
            return false;
        }

        $this->update(['status' => self::STATUS_PENDING]);
        SendMetaPurchaseEventJob::dispatch($this->order_id)->onQueue('ecommerce');

        return true;
    }
}
