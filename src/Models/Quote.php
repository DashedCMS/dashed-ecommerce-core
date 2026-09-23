<?php

namespace Dashed\DashedEcommerceCore\Models;

use Illuminate\Support\Str;
use Illuminate\Support\Collection;
use Dashed\DashedCore\Models\User;
use Dashed\DashedCore\Classes\Sites;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Quote extends Model
{
    public const STATUS_CONCEPT = 'concept';
    public const STATUS_SENT = 'sent';
    public const STATUS_ACCEPTED = 'accepted';
    public const STATUS_REJECTED = 'rejected';
    public const STATUS_EXPIRED = 'expired';
    public const STATUS_WITHDRAWN = 'withdrawn';
    public const STATUS_SUPERSEDED = 'superseded';

    public const ROUTE_PREPAY = 'prepay';
    public const ROUTE_ON_ACCOUNT = 'on_account';

    protected $table = 'dashed__quotes';

    protected $guarded = [];

    protected $casts = [
        'valid_until' => 'date',
        'prices_ex_vat' => 'boolean',
        'total' => 'decimal:2',
        'sent_at' => 'datetime',
        'viewed_at' => 'datetime',
        'accepted_at' => 'datetime',
        'rejected_at' => 'datetime',
        'reminder_sent_at' => 'datetime',
    ];

    protected static function booted(): void
    {
        static::creating(function (Quote $quote) {
            if (! $quote->hash) {
                $quote->hash = Str::random(32);
            }
            if (! $quote->status) {
                $quote->status = self::STATUS_CONCEPT;
            }
            if (! $quote->site_id) {
                $quote->site_id = Sites::getActive();
            }
            if (! $quote->locale) {
                $quote->locale = app()->getLocale();
            }
            if (! $quote->version) {
                $quote->version = 1;
            }
        });
    }

    public function lines(): HasMany
    {
        return $this->hasMany(QuoteLine::class)->orderBy('sort_order')->orderBy('id');
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function parent(): BelongsTo
    {
        return $this->belongsTo(self::class, 'parent_quote_id');
    }

    public function versions(): HasMany
    {
        return $this->hasMany(self::class, 'parent_quote_id');
    }

    /** De wortel van de versieketen: versie 1, of de offerte zelf. */
    public function rootQuote(): self
    {
        return $this->parent_quote_id ? ($this->parent ?? $this) : $this;
    }

    /** Regels die meetellen: alles wat niet optioneel is, plus de aangevinkte keuzes. */
    public function selectedLines(): Collection
    {
        return $this->lines->filter(fn (QuoteLine $line) => ! $line->is_optional || $line->is_selected)->values();
    }

    public function isExpired(): bool
    {
        if ($this->status !== self::STATUS_SENT) {
            return $this->status === self::STATUS_EXPIRED;
        }

        return $this->valid_until !== null && $this->valid_until->endOfDay()->isPast();
    }

    /** Kan de klant hier nog iets mee? Alleen dan tonen we de knoppen. */
    public function isAnswerable(): bool
    {
        return $this->status === self::STATUS_SENT && ! $this->isExpired();
    }

    public function scopeOpen(Builder $query): Builder
    {
        return $query->where('status', self::STATUS_SENT);
    }

    public function scopeSent(Builder $query): Builder
    {
        return $query->whereNotNull('sent_at');
    }

    public static function statusLabels(): array
    {
        return [
            self::STATUS_CONCEPT => __('Concept'),
            self::STATUS_SENT => __('Verstuurd'),
            self::STATUS_ACCEPTED => __('Geaccepteerd'),
            self::STATUS_REJECTED => __('Afgewezen'),
            self::STATUS_EXPIRED => __('Verlopen'),
            self::STATUS_WITHDRAWN => __('Ingetrokken'),
            self::STATUS_SUPERSEDED => __('Vervangen'),
        ];
    }

    public function statusLabel(): string
    {
        return self::statusLabels()[$this->status] ?? $this->status;
    }

    /** Nummer zoals het op de PDF en in de lijst staat, met de versie erbij. */
    public function displayNumber(): string
    {
        $number = $this->quote_number ?: __('Concept');

        return $this->version > 1 ? $number.' v'.$this->version : $number;
    }

    public function fullName(): string
    {
        return trim(($this->first_name ?? '').' '.($this->last_name ?? ''));
    }

    public function publicUrl(): string
    {
        return url('/quote/'.$this->hash);
    }
}
