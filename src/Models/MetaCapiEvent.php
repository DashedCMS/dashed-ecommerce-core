<?php

namespace Dashed\DashedEcommerceCore\Models;

use Illuminate\Support\Carbon;
use Illuminate\Database\Eloquent\Model;
use Dashed\DashedEcommerceCore\Services\Meta\ConversionsApi;
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

    /**
     * Verstuurd terwijl er een test event code was ingevuld: Meta zet zo'n
     * event onder "Test events" en telt het niet als aankoop.
     */
    public function sentAsTest(): bool
    {
        return $this->status === self::STATUS_SENT && ($this->response['test'] ?? false) === true;
    }

    public function displayStatusLabel(): string
    {
        return $this->sentAsTest() ? __('Verstuurd als test') : static::statusLabel((string) $this->status);
    }

    public function displayStatusColor(): string
    {
        return match (true) {
            $this->sentAsTest() => 'warning',
            $this->status === self::STATUS_SENT => 'success',
            $this->status === self::STATUS_FAILED => 'danger',
            $this->status === self::STATUS_SKIPPED => 'gray',
            default => 'warning',
        };
    }

    /**
     * Mislukt, overgeslagen of alleen als test verstuurd, en niet ouder dan
     * Meta nog aanneemt: een later verstuurde aankoop zou de datum van vandaag
     * krijgen en niet meer met de browserpixel ontdubbeld worden.
     */
    public function canResend(): bool
    {
        if ($this->order_id === null) {
            return false;
        }

        if (! $this->created_at || $this->created_at->lt(Carbon::now()->subDays(ConversionsApi::MAX_EVENT_AGE_DAYS))) {
            return false;
        }

        return in_array($this->status, [self::STATUS_FAILED, self::STATUS_SKIPPED], true) || $this->sentAsTest();
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
