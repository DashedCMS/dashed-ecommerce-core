<?php

namespace Dashed\DashedEcommerceCore\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class AbandonedCartFlow extends Model
{
    protected $table = 'dashed__abandoned_cart_flows';

    protected $fillable = [
        'name',
        'is_active',
        'discount_prefix',
        'triggers',
        'skip_if_paid_within_days',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'triggers' => 'array',
        'skip_if_paid_within_days' => 'integer',
    ];

    public function hasTrigger(string $trigger): bool
    {
        return in_array($trigger, $this->triggers ?? [], true);
    }

    public function steps(): HasMany
    {
        return $this->hasMany(AbandonedCartFlowStep::class, 'flow_id')->orderBy('sort_order');
    }

    public function emails(): \Illuminate\Database\Eloquent\Relations\HasManyThrough
    {
        return $this->hasManyThrough(
            AbandonedCartEmail::class,
            AbandonedCartFlowStep::class,
            'flow_id',
            'flow_step_id',
            'id',
            'id',
        );
    }

    public function recoveryRate(): float
    {
        $stepIds = $this->steps()->pluck('id');

        if ($stepIds->isEmpty()) {
            return 0.0;
        }

        $sent = AbandonedCartEmail::query()
            ->whereIn('flow_step_id', $stepIds)
            ->whereNotNull('sent_at')
            ->count();

        if ($sent === 0) {
            return 0.0;
        }

        $converted = AbandonedCartEmail::query()
            ->whereIn('flow_step_id', $stepIds)
            ->whereNotNull('converted_at')
            ->count();

        return round(($converted / $sent) * 100, 1);
    }

    public function revenueSum(): float
    {
        $stepIds = $this->steps()->pluck('id');

        if ($stepIds->isEmpty()) {
            return 0.0;
        }

        return (float) AbandonedCartEmail::query()
            ->whereIn('flow_step_id', $stepIds)
            ->whereNotNull('converted_at')
            ->whereNotNull('order_id')
            ->join('dashed__orders', 'dashed__orders.id', '=', 'dashed__abandoned_cart_emails.order_id')
            ->sum('dashed__orders.total');
    }

    public function averageConversionHours(): ?float
    {
        $stepIds = $this->steps()->pluck('id');

        if ($stepIds->isEmpty()) {
            return null;
        }

        $rows = AbandonedCartEmail::query()
            ->whereIn('flow_step_id', $stepIds)
            ->whereNotNull('sent_at')
            ->whereNotNull('converted_at')
            ->get(['sent_at', 'converted_at']);

        if ($rows->isEmpty()) {
            return null;
        }

        $hours = $rows->map(fn ($row) => $row->sent_at->floatDiffInHours($row->converted_at));

        return round($hours->avg(), 1);
    }

    /**
     * Eerste actieve flow, ongeacht de trigger. Alleen voor achterwaartse
     * compatibiliteit: sinds er per trigger een eigen flow actief kan zijn,
     * kies je een flow met getActiveFor().
     */
    public static function getActive(): ?self
    {
        return static::where('is_active', true)->orderBy('id')->first();
    }

    /**
     * De actieve flow voor een trigger. Per trigger kan één flow actief zijn;
     * staan er (bijv. door oude data) meer aan, dan wint de oudste.
     */
    public static function getActiveFor(string $trigger): ?self
    {
        return static::where('is_active', true)
            ->orderBy('id')
            ->get()
            ->first(fn (self $flow) => $flow->hasTrigger($trigger));
    }

    public function activate(): void
    {
        $this->update(['is_active' => true]);
        $this->deactivateOverlappingFlows();
    }

    /**
     * Zet andere actieve flows uit die minstens één trigger met deze flow delen.
     * Flows met andere triggers blijven aan.
     *
     * @return int aantal uitgezette flows
     */
    public function deactivateOverlappingFlows(): int
    {
        $triggers = $this->triggers ?? [];

        if ($triggers === []) {
            return 0;
        }

        $ids = static::where('is_active', true)
            ->where('id', '!=', $this->id)
            ->get()
            ->filter(fn (self $flow) => array_intersect($triggers, $flow->triggers ?? []) !== [])
            ->pluck('id');

        if ($ids->isEmpty()) {
            return 0;
        }

        return static::whereIn('id', $ids)->update(['is_active' => false]);
    }

    public static function createDefault(): self
    {
        $flow = static::create([
            'name' => 'Standaard flow',
            'is_active' => true,
            'discount_prefix' => 'TERUG',
            'triggers' => ['cart_with_email'],
        ]);
        $flow->deactivateOverlappingFlows();

        $steps = [
            [
                'sort_order' => 1,
                'delay_value' => 1,
                'delay_unit' => 'hours',
                'subject' => 'Je hebt iets achtergelaten',
                'button_label' => 'Ga verder met bestellen',
                'incentive_enabled' => false,
                'blocks' => [
                    ['type' => 'text', 'data' => ['content' => '<p>Je winkelwagen staat nog voor je klaar. Kom terug en rond je bestelling af bij <strong>:siteName:</strong>!</p>']],
                    ['type' => 'products', 'data' => []],
                    ['type' => 'button', 'data' => ['label' => 'Ga verder met bestellen', 'url' => '']],
                ],
            ],
            [
                'sort_order' => 2,
                'delay_value' => 24,
                'delay_unit' => 'hours',
                'subject' => 'Je :product: wacht nog op je',
                'button_label' => 'Bestel nu',
                'incentive_enabled' => false,
                'blocks' => [
                    ['type' => 'text', 'data' => ['content' => '<p>Je bent bijna klaar! Je winkelwagen staat nog voor je klaar. Andere klanten gingen je al voor:</p>']],
                    ['type' => 'product', 'data' => []],
                    ['type' => 'review', 'data' => []],
                    ['type' => 'button', 'data' => ['label' => 'Bestel nu', 'url' => '']],
                ],
            ],
            [
                'sort_order' => 3,
                'delay_value' => 72,
                'delay_unit' => 'hours',
                'subject' => 'Speciaal voor jou: een cadeautje',
                'button_label' => 'Bestel met korting',
                'incentive_enabled' => true,
                'incentive_type' => 'amount',
                'incentive_value' => 5,
                'incentive_valid_days' => 7,
                'blocks' => [
                    ['type' => 'text', 'data' => ['content' => '<p>We willen je graag een handje helpen. Gebruik de onderstaande kortingscode bij je bestelling:</p>']],
                    ['type' => 'product', 'data' => []],
                    ['type' => 'discount', 'data' => []],
                    ['type' => 'divider', 'data' => []],
                    ['type' => 'usp', 'data' => ['items' => "Gratis verzending\nSnel geleverd\nVeilig betalen"]],
                    ['type' => 'button', 'data' => ['label' => 'Bestel met korting', 'url' => '']],
                ],
            ],
        ];

        foreach ($steps as $step) {
            $flow->steps()->create($step);
        }

        return $flow;
    }
}
