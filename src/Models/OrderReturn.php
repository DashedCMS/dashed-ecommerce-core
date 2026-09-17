<?php

namespace Dashed\DashedEcommerceCore\Models;

use Illuminate\Support\Str;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Mail;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Dashed\DashedEcommerceCore\Events\Orders\OrderReturnClosedEvent;
use Dashed\DashedEcommerceCore\Events\Orders\OrderReturnApprovedEvent;
use Dashed\DashedEcommerceCore\Events\Orders\OrderReturnRejectedEvent;
use Dashed\DashedEcommerceCore\Mail\OrderReturn\OrderReturnCustomMail;
use Dashed\DashedEcommerceCore\Mail\OrderReturn\OrderReturnApprovedMail;
use Dashed\DashedEcommerceCore\Mail\OrderReturn\OrderReturnRejectedMail;

class OrderReturn extends Model
{
    public const STATUS_REQUESTED = 'requested';
    public const STATUS_APPROVED = 'approved';
    public const STATUS_REJECTED = 'rejected';
    public const STATUS_HANDLED = 'handled';
    public const STATUS_CLOSED = 'closed';

    protected $table = 'dashed__order_returns';

    protected $guarded = [];

    protected $casts = [
        'requested_at' => 'datetime',
        'approved_at' => 'datetime',
        'rejected_at' => 'datetime',
        'handled_at' => 'datetime',
        'processed_at' => 'datetime',
        'closed_at' => 'datetime',
        'bol_handled_at' => 'datetime',
        'auto_accepted' => 'boolean',
    ];

    protected static function booted(): void
    {
        static::creating(function (OrderReturn $return) {
            if (! $return->hash) {
                $return->hash = Str::random(32);
            }
            if (! $return->status) {
                $return->status = self::STATUS_REQUESTED;
            }
            if (! $return->requested_at) {
                $return->requested_at = now();
            }
        });
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    /**
     * De creditorder die bij het verwerken is ontstaan. Eén retour, hooguit één
     * creditorder; de kolom is nullable zolang er niets verwerkt is.
     */
    public function creditOrder(): BelongsTo
    {
        return $this->belongsTo(Order::class, 'credit_order_id');
    }

    public function lines(): HasMany
    {
        return $this->hasMany(OrderReturnLine::class);
    }

    public function messages(): HasMany
    {
        return $this->hasMany(OrderReturnMessage::class)->orderBy('created_at')->orderBy('id');
    }

    public function scopeRequested(Builder $query): Builder
    {
        return $query->where('status', self::STATUS_REQUESTED);
    }

    public function scopeOpen(Builder $query): Builder
    {
        return $query->whereNotIn('status', [self::STATUS_REJECTED, self::STATUS_HANDLED, self::STATUS_CLOSED]);
    }

    public function scopeNotHandled(Builder $query): Builder
    {
        return $query->whereNotIn('status', [self::STATUS_HANDLED, self::STATUS_CLOSED]);
    }

    public static function statusLabels(): array
    {
        return [
            self::STATUS_REQUESTED => __('Aangevraagd'),
            self::STATUS_APPROVED => __('Goedgekeurd'),
            self::STATUS_REJECTED => __('Afgekeurd'),
            self::STATUS_HANDLED => __('Verwerkt'),
            self::STATUS_CLOSED => __('Gesloten'),
        ];
    }

    public function statusLabel(): string
    {
        return self::statusLabels()[$this->status] ?? $this->status;
    }

    public function approve(?string $adminNote = null): void
    {
        $this->status = self::STATUS_APPROVED;
        $this->approved_at = now();
        if ($adminNote) {
            $this->admin_note = $adminNote;
        }
        $this->save();

        $this->logToOrder('order.return-approved');
        if ($this->skipsCustomerMail()) {
            OrderLog::createLog(orderId: $this->order_id, tag: 'order.return-mail-skipped-bol');
        } else {
            Mail::to($this->email)->queue(new OrderReturnApprovedMail($this));
        }
        OrderReturnApprovedEvent::dispatch($this);
    }

    public function reject(string $reason): void
    {
        $this->status = self::STATUS_REJECTED;
        $this->rejected_at = now();
        $this->rejected_reason = $reason;
        $this->save();

        $this->logToOrder('order.return-rejected');
        if ($this->skipsCustomerMail()) {
            OrderLog::createLog(orderId: $this->order_id, tag: 'order.return-mail-skipped-bol');
        } else {
            Mail::to($this->email)->queue(new OrderReturnRejectedMail($this));
        }
        OrderReturnRejectedEvent::dispatch($this);
    }

    public function sendCustomEmail(string $subject, string $message, ?string $email = null): void
    {
        $to = $email ?: $this->email;

        $this->messages()->create([
            'sender' => OrderReturnMessage::SENDER_ADMIN,
            'message' => $message,
        ]);

        Mail::to($to)->queue(new OrderReturnCustomMail($this, $message, $subject));
    }

    public function markHandled(): void
    {
        $this->status = self::STATUS_HANDLED;
        $this->handled_at = now();
        $this->save();

        $this->order?->update(['retour_status' => 'handled']);
        $this->logToOrder('order.return-handled');
    }

    /**
     * Sluiten zonder creditering: de retour is afgerond maar er komt geen
     * creditorder. Alleen vanuit goedgekeurd, altijd met reden, nooit met mail:
     * wie de klant iets wil uitleggen gebruikt sendCustomEmail().
     */
    public function close(string $reason): void
    {
        $reason = trim($reason);
        if ($reason === '') {
            throw new \InvalidArgumentException(__('Geef een reden op om de retour te sluiten.'));
        }
        if ($this->status !== self::STATUS_APPROVED) {
            throw new \InvalidArgumentException(__('Alleen een goedgekeurde retour kan gesloten worden.'));
        }

        $this->status = self::STATUS_CLOSED;
        $this->closed_reason = $reason;
        $this->closed_at = now();
        $this->save();

        $this->order?->update(['retour_status' => 'handled']);
        $this->logToOrder('order.return-closed');
        OrderReturnClosedEvent::dispatch($this);
    }

    /**
     * Terugbetaald betekent: de creditorder heeft een betaalde betaling. De
     * creditorder zelf blijft op status 'return'; zie de spec waarom er geen
     * changeStatus('paid') op wordt gedaan.
     */
    public function refundPayment(): ?OrderPayment
    {
        if (! $this->credit_order_id) {
            return null;
        }

        return OrderPayment::query()
            ->where('order_id', $this->credit_order_id)
            ->where('status', 'paid')
            ->orderBy('id')
            ->first();
    }

    public function isRefunded(): bool
    {
        return $this->refundPayment() !== null;
    }

    public function creditedAmount(): float
    {
        return $this->creditOrder ? abs((float) $this->creditOrder->total) : 0.0;
    }

    /**
     * Een marktplaatsbestelling praat zelf met zijn klant: die klant is klant
     * van Bol en kent ons niet, en Bol heeft de retour zelf aangemeld. Elke
     * andere retourmail (registrar, processor, refund) sloeg dit al over; hier
     * ontbrak het, dus een afgekeurde Bol-retour mailde de Bol-klant.
     */
    protected function skipsCustomerMail(): bool
    {
        return $this->order?->order_origin === 'Bol';
    }

    protected function logToOrder(string $tag): void
    {
        $log = new OrderLog();
        $log->order_id = $this->order_id;
        $log->user_id = Auth::id();
        $log->tag = $tag;
        $log->save();
    }
}
