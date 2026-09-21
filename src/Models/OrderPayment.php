<?php

namespace Dashed\DashedEcommerceCore\Models;

use Illuminate\Support\Str;
use Spatie\Activitylog\LogOptions;
use Illuminate\Database\Eloquent\Model;
use Dashed\DashedCore\Models\Customsetting;
use Spatie\Activitylog\Traits\LogsActivity;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class OrderPayment extends Model
{
    use LogsActivity;

    protected static $logFillable = true;

    protected $table = 'dashed__order_payments';

    protected $fillable = [
        'order_id',
        'psp',
        'psp_id',
        'payment_method',
        'payment_method_id',
        'psp_payment_method_id',
        'amount',
        'status',
        'payment_hash',
        'attributes',
        'credit_order_id',
    ];

    protected $appends = [
        'payment_method_name',
    ];

    protected $casts = [
        'attributes' => 'array',
        'psp_request' => 'array',
        'psp_response' => 'array',
    ];

    public static function boot()
    {
        parent::boot();

        static::creating(function ($orderPayment) {
            $orderPayment->hash = Str::random(32);
        });

        static::created(function ($orderPayment) {
            if (Customsetting::get('cash_register_available', null, false) && Customsetting::get('cash_register_track_cash_book', null, '') && ($orderPayment->paymentMethod->is_cash_payment ?? false)) {
                $cashRegisterAmount = Customsetting::get('cash_register_amount', null, 0);
                $cashRegisterAmount = $cashRegisterAmount + $orderPayment->amount;
                Customsetting::set('cash_register_amount', $cashRegisterAmount);
            }
        });
    }

    /**
     * Wat er precies naar de betaalprovider ging, vastgelegd door de provider
     * zelf vlak voor de aanroep. Bij de bestelling is dat per betaling in te
     * zien, zodat je bij een geweigerde betaling ziet wat er ontbrak.
     */
    public function recordPspRequest(array $payload): void
    {
        $this->recordPspColumn('psp_request', [
            'sent_at' => now()->toIso8601String(),
            'data' => json_decode(json_encode($payload, JSON_PARTIAL_OUTPUT_ON_ERROR), true),
        ]);
    }

    public function recordPspResponse(array $response): void
    {
        $this->recordPspColumn('psp_response', [
            'received_at' => now()->toIso8601String(),
            ...$response,
        ]);
    }

    /**
     * Vastleggen mag een betaling nooit tegenhouden, ook niet midden in een
     * uitrol waarin de kolom nog ontbreekt. Lukt het niet, dan gaat het
     * attribuut weer van het model af, anders klapt de volgende save() van
     * de provider alsnog op dezelfde kolom.
     */
    protected function recordPspColumn(string $column, array $value): void
    {
        try {
            $this->{$column} = $value;
            $this->saveQuietly();
        } catch (\Throwable $e) {
            unset($this->{$column});
            report($e);
        }
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults();
    }

    public function order()
    {
        return $this->belongsTo(Order::class);
    }

    public function paymentMethod(): BelongsTo
    {
        return $this->belongsTo(PaymentMethod::class)
            ->withTrashed();
    }

    public function scopePaid($query)
    {
        return $query->where('status', 'paid');
    }

    public function getPaymentMethodNameAttribute(): string
    {
        if ($this->paymentMethod) {
            return (string) ($this->paymentMethod->name ?? '');
        }

        return (string) ($this->payment_method ?? '');
    }

    public function getPaymentMethodInstructionsAttribute(): string
    {
        if ($this->paymentMethod) {
            return $this->paymentMethod->payment_instructions ?: '';
        } else {
            return '';
        }
    }

    public function changeStatus($newStatus = null): string
    {
        if (! $newStatus || $this->status == $newStatus) {
            return '';
        }

        $this->status = $newStatus;
        $this->save();

        if ($newStatus == 'cancelled') {
            // Een geannuleerde betaling zegt iets over die ene betaling, niet
            // over de bestelling. Een bestelling die al (deels) betaald is,
            // of waar nog een andere betaling van open staat (een klant die
            // het opnieuw probeert), blijft wat hij was. Anders annuleerde
            // de klantpagina een betaalde bestelling zodra hij de laatste,
            // bij de PSP vervallen betaling nakeek; de link in de
            // fulfilment-mail wijst precies naar die betaling.
            //
            // Hetzelfde geldt voor een bestelling die op bevestiging wacht
            // (handmatige of kassabestelling, overboeking): die status komt
            // nooit van een PSP-betaling, dus een afgebroken betaallink is
            // geen reden om hem te laten vervallen. Annuleren is daar een
            // keuze van de winkel.
            $order = $this->order;

            if ($order && in_array($order->status, ['paid', 'partially_paid', 'waiting_for_confirmation'], true)) {
                return '';
            }

            $others = $order?->orderPayments()->where('id', '!=', $this->id);

            if ($others && (clone $others)->whereIn('status', ['paid', 'pending'])->exists()) {
                return '';
            }

            return 'cancelled';
        } elseif ($newStatus == 'paid') {
            if ($this->order->orderPayments()->where('status', 'paid')->sum('amount') >= $this->order->total) {
                return 'paid';
            } else {
                return 'partially_paid';
            }
        }

        return '';
    }
}
