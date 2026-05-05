<?php

namespace Dashed\DashedEcommerceCore\Models;

use Illuminate\Database\Eloquent\Model;

class ApiSubscriptionLog extends Model
{
    protected $table = 'dashed__api_subscription_logs';

    protected $guarded = [];

    protected $casts = [
        'synced_at' => 'datetime',
    ];

    public const STATUS_SUCCESS = 'success';
    public const STATUS_FAILED = 'failed';
    public const STATUS_SKIPPED = 'skipped';

    public const SOURCE_ORDER = 'order';
    public const SOURCE_CART = 'cart';
    public const SOURCE_POPUP = 'popup';
    public const SOURCE_FORM = 'form';
    public const SOURCE_USER = 'user';
    public const SOURCE_BACKFILL = 'backfill';

    public static function record(string $email, string $apiClass, string $source, string $status, ?string $error = null): self
    {
        return self::create([
            'email' => mb_strtolower(trim($email)),
            'api_class' => $apiClass,
            'source' => $source,
            'status' => $status,
            'error' => $error,
            'synced_at' => now(),
        ]);
    }
}
