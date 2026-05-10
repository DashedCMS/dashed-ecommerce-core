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
        $userLabel = $this->user ? $this->user->name : 'Systeem';
        $orderLabel = $this->order
            ? (($this->order->name ?: 'POS') . ' (' . ($this->order->invoice_id ?: $this->order->id) . ')')
            : null;

        if ($this->tag == 'giftcard.created') {
            $string = $userLabel . ' heeft een cadeaubon aangemaakt met een waarde van ' . CurrencyHelper::formatPrice($this->new_amount) . '.';
        } elseif ($this->tag == 'giftcard.amount.changed.by.admin') {
            $string = $userLabel . ' heeft de waarde van ' . CurrencyHelper::formatPrice($this->old_amount) . ' naar ' . CurrencyHelper::formatPrice($this->new_amount) . ' veranderd.';
        } elseif ($this->tag == 'giftcard.order.transaction.started') {
            $string = $this->order->name . ' is een bestelling gestart (' . ($this->order->invoice_id ?: $this->order->id) . ') met ' . CurrencyHelper::formatPrice($this->order->discount) . ' korting.';
        } elseif ($this->tag == 'giftcard.order.transaction.completed') {
            $string = $this->order->name . ' heeft bestelling (' . ($this->order->invoice_id ?: $this->order->id) . ') betaald.';
        } elseif ($this->tag == 'giftcard.order.transaction.cancelled') {
            $string = $this->order->name . ' heeft bestelling (' . ($this->order->invoice_id ?: $this->order->id) . ') geannuleerd.';
        } elseif ($this->tag == 'giftcard.redeemed') {
            $string = $userLabel . ' heeft ' . CurrencyHelper::formatPrice($this->old_amount - $this->new_amount) . ' van deze cadeaubon ingewisseld'
                . ($orderLabel ? ' op bestelling ' . $orderLabel : '')
                . ' (saldo: ' . CurrencyHelper::formatPrice($this->old_amount) . ' → ' . CurrencyHelper::formatPrice($this->new_amount) . ').';
        } elseif ($this->tag == 'giftcard.merged_to_primary') {
            $string = $userLabel . ' heeft het saldo van deze cadeaubon (' . CurrencyHelper::formatPrice($this->old_amount) . ') overgezet naar een andere cadeaubon bij het bundelen voor afrekenen.';
        } elseif ($this->tag == 'giftcard.merged_from_secondary') {
            $string = $userLabel . ' heeft saldo van andere cadeaubonnen op deze cadeaubon bijgeschreven (' . CurrencyHelper::formatPrice($this->old_amount) . ' → ' . CurrencyHelper::formatPrice($this->new_amount) . ') bij het bundelen voor afrekenen.';
        } elseif ($this->tag == 'discountcode.applied') {
            $string = $userLabel . ' heeft deze kortingscode toegepast'
                . ($orderLabel ? ' op bestelling ' . $orderLabel : '')
                . ($this->new_amount > 0 ? ' (korting: ' . CurrencyHelper::formatPrice($this->new_amount) . ')' : '') . '.';
        } else {
            return 'ERROR tag niet gevonden: ' . $this->tag;
        }

        return $string;
    }
}
