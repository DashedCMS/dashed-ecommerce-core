<?php

namespace Dashed\DashedEcommerceCore\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Een verstuurde herinneringstap van een order op rekening. De unieke
 * index op (order_id, stage) is wat voorkomt dat een stap twee keer de deur
 * uit gaat, ook als de dagelijkse ronde dubbel draait.
 */
class OrderPaymentReminder extends Model
{
    protected $table = 'dashed__order_payment_reminders';

    protected $casts = [
        'sent_at' => 'datetime',
    ];

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }
}
