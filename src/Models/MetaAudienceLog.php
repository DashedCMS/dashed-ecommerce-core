<?php

declare(strict_types=1);

namespace Dashed\DashedEcommerceCore\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MetaAudienceLog extends Model
{
    public const TYPE_CREATE = 'create';
    public const TYPE_FULL = 'full';
    public const TYPE_INCREMENTAL = 'incremental';

    public const STATUS_SUCCESS = 'success';
    public const STATUS_FAILED = 'failed';
    public const STATUS_RETRYING = 'retrying';

    protected $table = 'dashed__meta_audience_logs';

    protected $guarded = [];

    public function audience(): BelongsTo
    {
        return $this->belongsTo(MetaAudience::class, 'meta_audience_id');
    }

    public static function typeLabel(string $type): string
    {
        return match ($type) {
            self::TYPE_CREATE => __('Aangemaakt bij Meta'),
            self::TYPE_FULL => __('Volledige sync'),
            self::TYPE_INCREMENTAL => __('Incrementele sync'),
            default => $type,
        };
    }

    public static function statusLabel(string $status): string
    {
        return match ($status) {
            self::STATUS_SUCCESS => __('Gelukt'),
            self::STATUS_FAILED => __('Mislukt'),
            self::STATUS_RETRYING => __('Mislukt, wordt opnieuw geprobeerd'),
            default => $status,
        };
    }
}
