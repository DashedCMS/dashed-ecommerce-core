<?php

namespace Dashed\DashedEcommerceCore\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Eén betaalde eerste bestelling via een verwijscode. Pending tot de
 * wachttijd (de retourtermijn) voorbij is, daarna rewarded of rejected.
 */
class Referral extends Model
{
    public const STATUS_PENDING = 'pending';
    public const STATUS_REWARDED = 'rewarded';
    public const STATUS_REJECTED = 'rejected';

    protected $table = 'dashed__referrals';

    protected $guarded = [];

    protected $casts = [
        'qualifies_at' => 'datetime',
        'rewarded_at' => 'datetime',
    ];

    public static function statuses(): array
    {
        return [
            self::STATUS_PENDING => __('In afwachting'),
            self::STATUS_REWARDED => __('Beloond'),
            self::STATUS_REJECTED => __('Afgewezen'),
        ];
    }

    public static function rejectReasons(): array
    {
        return [
            'self_referral' => __('Verwijzer en nieuwe klant zijn dezelfde persoon'),
            'not_first_order' => __('Niet de eerste bestelling van deze klant'),
            'same_address' => __('Zelfde adres als de verwijzer'),
            'same_ip' => __('Zelfde IP-adres als de verwijzer'),
            'returned' => __('Bestelling geretourneerd of geannuleerd'),
            'manual' => __('Met de hand afgewezen'),
        ];
    }

    public function referralCode(): BelongsTo
    {
        return $this->belongsTo(ReferralCode::class);
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function rewardDiscountCode(): BelongsTo
    {
        return $this->belongsTo(DiscountCode::class, 'reward_discount_code_id');
    }
}
