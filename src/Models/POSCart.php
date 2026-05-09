<?php

namespace Dashed\DashedEcommerceCore\Models;

use Spatie\Activitylog\LogOptions;
use Illuminate\Database\Eloquent\Model;
use Spatie\Activitylog\Traits\LogsActivity;

class POSCart extends Model
{
    use LogsActivity;

    protected static $logFillable = true;

    protected $table = 'dashed__pos_carts';

    protected $casts = [
        'products' => 'array',
        'custom_fields' => 'array',
        'prices_ex_vat' => 'boolean',
        'applied_gift_cards' => 'array',
    ];

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()->logExcept(['products', 'custom_fields']);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Probeer een cadeaubon toe te passen op deze POS-winkelwagen. Returnt
     * `['success' => bool, 'message' => string, 'amount' => float]`.
     *
     * Validaties: code bestaat, is een gift card, heeft saldo, valt binnen
     * geldigheidsperiode, niet al toegepast.
     */
    public function applyGiftcard(string $code): array
    {
        $code = trim(strtoupper($code));
        if ($code === '') {
            return ['success' => false, 'message' => 'Geen code ingevoerd.', 'amount' => 0.0];
        }

        $applied = is_array($this->applied_gift_cards) ? $this->applied_gift_cards : [];
        foreach ($applied as $existing) {
            if (strtoupper((string) ($existing['code'] ?? '')) === $code) {
                return ['success' => false, 'message' => 'Cadeaubon is al toegepast op deze bestelling.', 'amount' => 0.0];
            }
        }

        $discount = DiscountCode::where('code', $code)->where('is_giftcard', 1)->first();
        if (! $discount) {
            return ['success' => false, 'message' => 'Cadeaubon niet gevonden.', 'amount' => 0.0];
        }

        if ($discount->start_date && $discount->start_date->isFuture()) {
            return ['success' => false, 'message' => 'Cadeaubon is nog niet geldig.', 'amount' => 0.0];
        }
        if ($discount->end_date && $discount->end_date->isPast()) {
            return ['success' => false, 'message' => 'Cadeaubon is verlopen.', 'amount' => 0.0];
        }

        $balance = round((float) ($discount->discount_amount ?? 0), 2);
        if ($balance <= 0.0) {
            return ['success' => false, 'message' => 'Cadeaubon heeft geen saldo meer.', 'amount' => 0.0];
        }

        $applied[] = [
            'code' => $discount->code,
            'discount_code_id' => $discount->id,
            'balance' => $balance,
        ];
        $this->applied_gift_cards = array_values($applied);
        $this->save();

        return ['success' => true, 'message' => 'Cadeaubon toegevoegd (€'.number_format($balance, 2, ',', '.').' beschikbaar).', 'amount' => $balance];
    }

    public function removeGiftcard(string $code): bool
    {
        $code = trim(strtoupper($code));
        $applied = is_array($this->applied_gift_cards) ? $this->applied_gift_cards : [];
        $filtered = array_values(array_filter(
            $applied,
            fn ($entry) => strtoupper((string) ($entry['code'] ?? '')) !== $code
        ));

        if (count($filtered) === count($applied)) {
            return false;
        }

        $this->applied_gift_cards = $filtered;
        $this->save();

        return true;
    }

    /**
     * Som van het beschikbare saldo van alle toegepaste cadeaubonnen.
     * Wordt gebruikt om een bovengrens te bepalen voor de inwisselwaarde;
     * de daadwerkelijke afboeking gebeurt bij order-finalisatie waar elke
     * cadeaubon evenredig of in volgorde wordt afgeboekt tot de orderwaarde.
     */
    public function appliedGiftCardsTotal(): float
    {
        $applied = is_array($this->applied_gift_cards) ? $this->applied_gift_cards : [];

        return round(array_sum(array_map(
            fn ($entry) => (float) ($entry['balance'] ?? 0),
            $applied
        )), 2);
    }
}
