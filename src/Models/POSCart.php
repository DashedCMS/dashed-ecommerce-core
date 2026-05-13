<?php

namespace Dashed\DashedEcommerceCore\Models;

use Illuminate\Support\Collection;
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
        'applied_discount_codes' => 'array',
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

    /**
     * Pas een kortingscode toe op deze POS-winkelwagen. Cumulatieregel:
     * maximaal één procentuele code (`type === 'percentage'`) tegelijk,
     * vaste-bedrag-codes mogen onbeperkt stapelen. Returnt
     * `['success' => bool, 'message' => string]`.
     */
    public function applyDiscountCode(string $code): array
    {
        $code = trim(strtoupper($code));
        if ($code === '') {
            return ['success' => false, 'message' => 'Geen code ingevoerd.'];
        }

        $applied = is_array($this->applied_discount_codes) ? $this->applied_discount_codes : [];
        foreach ($applied as $existing) {
            if (strtoupper((string) ($existing['code'] ?? '')) === $code) {
                return ['success' => false, 'message' => 'Kortingscode is al toegepast op deze bestelling.'];
            }
        }

        // Eerst raw lookup zodat we precies kunnen zeggen waarom een code
        // wordt geweigerd (giftcard / verlopen / voorraad op / verkeerde site).
        $raw = DiscountCode::where('code', $code)->first();
        if (! $raw) {
            return ['success' => false, 'message' => 'Kortingscode niet gevonden.'];
        }
        if ((int) ($raw->is_giftcard ?? 0) === 1) {
            return ['success' => false, 'message' => 'Dit is een cadeaubon. Gebruik de knop "Cadeaubon toepassen".'];
        }
        if ($raw->start_date && $raw->start_date->isFuture()) {
            return ['success' => false, 'message' => 'Kortingscode is nog niet geldig.'];
        }
        if ($raw->end_date && $raw->end_date->isPast()) {
            return ['success' => false, 'message' => 'Kortingscode is verlopen.'];
        }
        if ((int) ($raw->use_stock ?? 0) === 1 && (int) ($raw->stock ?? 0) <= 0) {
            return ['success' => false, 'message' => 'Kortingscode is op (geen voorraad meer).'];
        }

        $discount = DiscountCode::usable()
            ->where('code', $code)
            ->where(function ($q) {
                $q->where('is_giftcard', 0)->orWhereNull('is_giftcard');
            })
            ->first();

        if (! $discount) {
            return ['success' => false, 'message' => 'Kortingscode niet geldig voor deze winkel.'];
        }

        $type = (string) ($discount->type ?? 'amount');

        if ($type === 'percentage') {
            foreach ($applied as $existing) {
                if (($existing['type'] ?? null) === 'percentage') {
                    return ['success' => false, 'message' => 'Er kan maar één procentuele kortingscode tegelijk worden toegepast.'];
                }
            }
        }

        $applied[] = [
            'code' => $discount->code,
            'discount_code_id' => $discount->id,
            'type' => $type,
            'discount_percentage' => $type === 'percentage' ? (float) ($discount->discount_percentage ?? 0) : 0.0,
            'discount_amount' => $type === 'amount' ? (float) ($discount->discount_amount ?? 0) : 0.0,
        ];

        $this->applied_discount_codes = array_values($applied);

        if (! $this->discount_code) {
            $this->discount_code = $discount->code;
        }

        $this->save();

        $label = $type === 'percentage'
            ? rtrim(rtrim(number_format((float) ($discount->discount_percentage ?? 0), 2, ',', '.'), '0'), ',').'%'
            : '€'.number_format((float) ($discount->discount_amount ?? 0), 2, ',', '.');

        return ['success' => true, 'message' => 'Kortingscode toegevoegd ('.$label.').'];
    }

    public function removeDiscountCode(string $code): bool
    {
        $code = trim(strtoupper($code));
        $applied = is_array($this->applied_discount_codes) ? $this->applied_discount_codes : [];
        $filtered = array_values(array_filter(
            $applied,
            fn ($entry) => strtoupper((string) ($entry['code'] ?? '')) !== $code
        ));

        if (count($filtered) === count($applied)) {
            if (strtoupper((string) ($this->discount_code ?? '')) === $code) {
                $this->discount_code = null;
                $this->save();

                return true;
            }

            return false;
        }

        $this->applied_discount_codes = $filtered;

        if (strtoupper((string) ($this->discount_code ?? '')) === $code) {
            $this->discount_code = $filtered[0]['code'] ?? null;
        }

        $this->save();

        return true;
    }

    /**
     * Eén lijst met toegepaste kortingscode-records. Combineert de nieuwe
     * `applied_discount_codes` JSON-kolom en de legacy single-string
     * `discount_code`-kolom (bestellingen die nog vóór de multi-code feature
     * gemaakt zijn). Dedupt op id.
     */
    public function appliedDiscountCodeRecords(): Collection
    {
        $records = collect();

        $applied = is_array($this->applied_discount_codes) ? $this->applied_discount_codes : [];
        $appliedIds = [];
        foreach ($applied as $entry) {
            $id = (int) ($entry['discount_code_id'] ?? 0);
            if (! $id) {
                continue;
            }
            $model = DiscountCode::usable()->find($id);
            if ($model && ! $model->is_giftcard) {
                $records->push($model);
                $appliedIds[] = $model->id;
            }
        }

        if ($this->discount_code) {
            $legacy = DiscountCode::usable()
                ->where('code', $this->discount_code)
                ->where(function ($q) {
                    $q->where('is_giftcard', 0)->orWhereNull('is_giftcard');
                })
                ->first();

            if ($legacy && ! in_array($legacy->id, $appliedIds, true)) {
                $records->push($legacy);
            }
        }

        return $records;
    }
}
