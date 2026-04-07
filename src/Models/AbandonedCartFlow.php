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
    ];

    protected $casts = [
        'is_active' => 'boolean',
    ];

    public function steps(): HasMany
    {
        return $this->hasMany(AbandonedCartFlowStep::class, 'flow_id')->orderBy('sort_order');
    }

    public static function getActive(): ?self
    {
        return static::where('is_active', true)->first();
    }

    public function activate(): void
    {
        static::where('id', '!=', $this->id)->update(['is_active' => false]);
        $this->update(['is_active' => true]);
    }

    public static function createDefault(): self
    {
        $flow = static::create(['name' => 'Standaard flow', 'is_active' => true]);
        static::where('id', '!=', $flow->id)->update(['is_active' => false]);

        $steps = [
            [
                'sort_order' => 1,
                'delay_value' => 1,
                'delay_unit' => 'hours',
                'subject' => 'Je hebt iets achtergelaten',
                'intro_text' => '<p>Je winkelwagen staat nog voor je klaar. Kom terug en rond je bestelling af!</p>',
                'button_label' => 'Ga verder met bestellen',
                'show_products' => true,
                'show_review' => false,
                'incentive_enabled' => false,
            ],
            [
                'sort_order' => 2,
                'delay_value' => 24,
                'delay_unit' => 'hours',
                'subject' => 'Je :product wacht nog op je',
                'intro_text' => '<p>Je bent bijna klaar! Je winkelwagen staat nog voor je klaar. Andere klanten gingen je al voor — dit is wat zij ervan vinden:</p>',
                'button_label' => 'Bestel nu',
                'show_products' => true,
                'show_review' => true,
                'incentive_enabled' => false,
            ],
            [
                'sort_order' => 3,
                'delay_value' => 72,
                'delay_unit' => 'hours',
                'subject' => 'Speciaal voor jou: een cadeautje',
                'intro_text' => '<p>We willen je graag een handje helpen. Gebruik de onderstaande kortingscode bij je bestelling:</p>',
                'button_label' => 'Bestel met korting',
                'show_products' => true,
                'show_review' => false,
                'incentive_enabled' => true,
                'incentive_type' => 'amount',
                'incentive_value' => 5,
                'incentive_valid_days' => 7,
            ],
        ];

        foreach ($steps as $step) {
            $flow->steps()->create($step);
        }

        return $flow;
    }
}
