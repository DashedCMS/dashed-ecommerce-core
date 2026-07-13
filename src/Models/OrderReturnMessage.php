<?php

namespace Dashed\DashedEcommerceCore\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class OrderReturnMessage extends Model
{
    public const SENDER_ADMIN = 'admin';
    public const SENDER_CUSTOMER = 'customer';

    protected $table = 'dashed__order_return_messages';

    protected $fillable = [
        'order_return_id',
        'sender',
        'message',
    ];

    public function orderReturn(): BelongsTo
    {
        return $this->belongsTo(OrderReturn::class);
    }

    public function isFromCustomer(): bool
    {
        return $this->sender === self::SENDER_CUSTOMER;
    }
}
