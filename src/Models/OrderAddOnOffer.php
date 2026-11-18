<?php

namespace Dashed\DashedEcommerceCore\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Het bijbestel-aanbod bij een order: wanneer getoond, gemaild (of overgeslagen) en geklikt. */
class OrderAddOnOffer extends Model
{
    protected $table = 'dashed__order_add_on_offers';

    protected $fillable = [
        'order_id',
        'email',
        'shown_at',
        'mail_sent_at',
        'mail_skipped',
        'clicked_at',
        'clicks',
    ];

    protected $casts = [
        'shown_at' => 'datetime',
        'mail_sent_at' => 'datetime',
        'clicked_at' => 'datetime',
        'clicks' => 'integer',
    ];

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class, 'order_id');
    }

    /** De rij van deze order, aangemaakt met het (genormaliseerde) e-mailadres als hij er nog niet is. */
    public static function for(Order $order): self
    {
        return self::firstOrCreate(
            ['order_id' => $order->id],
            ['email' => mb_strtolower(trim((string) $order->email)) ?: null],
        );
    }

    /** Eerste keer getoond (bedankpagina); een fout mag de pagina nooit breken. */
    public static function markShown(Order $order): void
    {
        rescue(function () use ($order) {
            $offer = self::for($order);
            $offer->shown_at ?: $offer->update(['shown_at' => now()]);
        }, null, false);
    }

    /** Een klik op de ondertekende link: eerste klikmoment en het aantal kliks. */
    public static function countClick(Order $order): void
    {
        rescue(function () use ($order) {
            $offer = self::for($order);
            $offer->update(['clicked_at' => $offer->clicked_at ?: now(), 'clicks' => (int) $offer->clicks + 1]);
        }, null, false);
    }
}
