<?php

namespace Dashed\DashedEcommerceCore\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;
use Illuminate\Database\Eloquent\Relations\HasMany;

class OrderHandledFlow extends Model
{
    protected $table = 'dashed__order_handled_flows';

    protected $fillable = [
        'name',
        'is_active',
        'discount_prefix',
        'skip_if_recently_ordered_within_days',
        'cancel_on_link_click',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'cancel_on_link_click' => 'boolean',
        'skip_if_recently_ordered_within_days' => 'integer',
    ];

    protected static function booted(): void
    {
        static::saved(function (OrderHandledFlow $flow) {
            // Maximaal 1 actieve flow tegelijk - in lijn met AbandonedCartFlow.
            if ($flow->is_active && $flow->wasChanged('is_active')) {
                static::query()
                    ->where('id', '!=', $flow->id)
                    ->where('is_active', true)
                    ->update(['is_active' => false]);
            }
        });
    }

    public function steps(): HasMany
    {
        return $this->hasMany(OrderHandledFlowStep::class, 'flow_id')->orderBy('sort_order');
    }

    public function activeSteps(): HasMany
    {
        return $this->hasMany(OrderHandledFlowStep::class, 'flow_id')
            ->where('is_active', true)
            ->orderBy('sort_order');
    }

    public static function getActive(): ?self
    {
        return static::query()->where('is_active', true)->first();
    }

    public function activate(): void
    {
        static::query()->where('id', '!=', $this->id)->update(['is_active' => false]);
        $this->update(['is_active' => true]);
    }

    /**
     * Maakt een complete standaard-flow aan met 1 zinnige opvolg-stap
     * (14 dagen na fulfillment_status = handled). Andere flows worden
     * automatisch op inactive gezet door de booted() saved-hook.
     */
    public static function createDefault(): self
    {
        $flow = static::create([
            'name' => 'Standaard flow',
            'is_active' => true,
            'discount_prefix' => null,
            'skip_if_recently_ordered_within_days' => 30,
            'cancel_on_link_click' => true,
        ]);

        $locale = app()->getLocale();

        $buildBlocks = function (array $items): array {
            $blocks = [];
            foreach ($items as $item) {
                $blocks[(string) Str::uuid()] = $item;
            }

            return $blocks;
        };

        $blocks = $buildBlocks([
            ['type' => 'heading', 'data' => ['content' => 'Hoi :firstName:, hoe vond je je bestelling?']],
            ['type' => 'paragraph', 'data' => ['content' => '<p>Bedankt dat je bij <strong>:siteName:</strong> hebt besteld (bestelnummer :orderNumber:). We zijn benieuwd hoe je je bestelling vond! Een review helpt ons enorm en helpt ook andere klanten een goede keuze maken.</p>']],
            ['type' => 'button', 'data' => ['label' => 'Schrijf een review', 'url' => ':reviewUrl:']],
        ]);

        $step = $flow->steps()->make([
            'sort_order' => 1,
            'send_after_minutes' => 20160, // 14 dagen
            'is_active' => true,
        ]);
        $step->setTranslation('subject', $locale, 'Hoe vond je je bestelling?');
        $step->setTranslation('blocks', $locale, $blocks);
        $step->save();

        return $flow;
    }
}
