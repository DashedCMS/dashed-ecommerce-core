<?php

namespace Dashed\DashedEcommerceCore\Models;

use App\Models\User;
use Illuminate\Support\Str;
use Illuminate\Support\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Wishlist extends Model
{
    protected $table = 'dashed__wishlists';

    protected $fillable = ['token', 'user_id', 'email', 'share_token', 'locale', 'site_id', 'last_activity_at', 'flow_cooldown_until'];

    protected $casts = [
        'last_activity_at' => 'datetime',
        'flow_cooldown_until' => 'datetime',
    ];

    protected static function booted(): void
    {
        static::creating(function (Wishlist $wishlist) {
            if (! $wishlist->token) {
                $wishlist->token = (string) Str::uuid();
            }
            if (! $wishlist->last_activity_at) {
                $wishlist->last_activity_at = now();
            }
        });
    }

    public function items(): HasMany
    {
        return $this->hasMany(WishlistItem::class, 'wishlist_id')->orderByDesc('id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    /**
     * Lijsten van dit e-mailadres: rechtstreeks (gast die zijn lijst bewaarde)
     * of via het account met dat adres. Twee aparte where's en geen join,
     * zodat de index op email en die op user_id allebei gebruikt worden.
     */
    public function scopeForEmail(Builder $query, string $email): Builder
    {
        return $query->where(function (Builder $q) use ($email) {
            $q->where('email', $email)
                ->orWhereIn('user_id', User::query()->where('email', $email)->select('id'));
        });
    }

    /**
     * De regels waarvan het product nog bestaat en publiek is, nieuwste eerst.
     * Alles wat een bezoeker of een mail te zien krijgt loopt hierlangs; de
     * andere regels blijven staan voor de statistiek.
     *
     * @return Collection<int, WishlistItem>
     */
    public function publicItems(): Collection
    {
        return $this->items()
            ->with('product')
            ->get()
            ->filter(fn (WishlistItem $item) => $item->product && $item->product->public)
            ->values();
    }

    public function ensureShareToken(): string
    {
        if (! $this->share_token) {
            $this->forceFill(['share_token' => (string) Str::uuid()])->save();
        }

        return $this->share_token;
    }

    public function touchActivity(): void
    {
        $this->forceFill(['last_activity_at' => now()])->save();
    }
}
