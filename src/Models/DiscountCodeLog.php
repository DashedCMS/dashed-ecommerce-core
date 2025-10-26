<?php

namespace Dashed\DashedEcommerceCore\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Dashed\DashedEcommerceCore\Classes\CurrencyHelper;

class DiscountCodeLog extends Model
{
    protected $table = 'dashed__discount_code_logs';

    protected $casts = [
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
        'deleted_at' => 'datetime',
    ];

    public function discountCode(): BelongsTo
    {
        return $this->belongsTo(DiscountCode::class, 'discount_code_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class, 'order_id');
    }

    public function tag()
    {
        if ($this->tag == 'giftcard.created') {
            $string = ($this->user ? $this->user->name : 'Systeem') . ' heeft een cadeaubon aangemaakt met een waarde van ' . CurrencyHelper::formatPrice($this->new_amount) . '.';
        } elseif ($this->tag == 'giftcard.amount.changed.by.admin') {
            $string = ($this->user ? $this->user->name : 'Systeem') . ' heeft de waarde van ' . CurrencyHelper::formatPrice($this->old_amount) . ' naar ' . CurrencyHelper::formatPrice($this->new_amount) . ' veranderd.';
        } elseif ($this->tag == 'giftcard.order.transaction.started') {
            $string = $this->order->name . ' is een bestelling gestart (' . ($this->order->invoice_id ?: $this->order->id) . ') met ' . CurrencyHelper::formatPrice($this->order->discount) . ' korting.';
        } elseif ($this->tag == 'giftcard.order.transaction.completed') {
            $string = $this->order->name . ' heeft bestelling (' . ($this->order->invoice_id ?: $this->order->id) . ') betaald.';
        } elseif ($this->tag == 'giftcard.order.transaction.cancelled') {
            $string = $this->order->name . ' heeft bestelling (' . ($this->order->invoice_id ?: $this->order->id) . ') geannuleerd.';
        } else {
            return 'ERROR tag niet gevonden: ' . $this->tag;
        }

        return $string;
    }
}
