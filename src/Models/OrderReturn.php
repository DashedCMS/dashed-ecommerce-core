<?php

namespace Dashed\DashedEcommerceCore\Models;

use Illuminate\Support\Str;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Mail;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Dashed\DashedEcommerceCore\Events\Orders\OrderReturnApprovedEvent;
use Dashed\DashedEcommerceCore\Mail\OrderReturn\OrderReturnCustomMail;
use Dashed\DashedEcommerceCore\Mail\OrderReturn\OrderReturnApprovedMail;
use Dashed\DashedEcommerceCore\Mail\OrderReturn\OrderReturnRejectedMail;

class OrderReturn extends Model
{
    public const STATUS_REQUESTED = 'requested';
    public const STATUS_APPROVED = 'approved';
    public const STATUS_REJECTED = 'rejected';
    public const STATUS_HANDLED = 'handled';

    protected $table = 'dashed__order_returns';

    protected $guarded = [];

    protected $casts = [
        'requested_at' => 'datetime',
        'approved_at' => 'datetime',
        'rejected_at' => 'datetime',
        'handled_at' => 'datetime',
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
        return $query->whereNotIn('status', [self::STATUS_REJECTED, self::STATUS_HANDLED]);
    }

    public function scopeNotHandled(Builder $query): Builder
    {
        return $query->where('status', '!=', self::STATUS_HANDLED);
    }

    public static function statusLabels(): array
    {
        return [
            self::STATUS_REQUESTED => __('Aangevraagd'),
            self::STATUS_APPROVED => __('Goedgekeurd'),
            self::STATUS_REJECTED => __('Afgekeurd'),
            self::STATUS_HANDLED => __('Afgehandeld'),
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
        Mail::to($this->email)->queue(new OrderReturnApprovedMail($this));
        OrderReturnApprovedEvent::dispatch($this);
    }

    public function reject(string $reason): void
    {
        $this->status = self::STATUS_REJECTED;
        $this->rejected_at = now();
        $this->rejected_reason = $reason;
        $this->save();

        $this->logToOrder('order.return-rejected');
        Mail::to($this->email)->queue(new OrderReturnRejectedMail($this));
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

    protected function logToOrder(string $tag): void
    {
        $log = new OrderLog();
        $log->order_id = $this->order_id;
        $log->user_id = Auth::id();
        $log->tag = $tag;
        $log->save();
    }
}
