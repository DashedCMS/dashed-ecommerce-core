<?php

namespace Dashed\DashedEcommerceCore\Models;

use Illuminate\Support\Str;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class OrderHandledFlow extends Model
{
    protected $table = 'dashed__order_handled_flows';

    protected $fillable = [
        'name',
        'trigger_status',
        'is_active',
        'discount_prefix',
        'skip_if_recently_ordered_within_days',
        'cancel_on_link_click',
        'review_urls',
    ];

    protected $casts = [
        'trigger_status' => 'string',
        'is_active' => 'boolean',
        'cancel_on_link_click' => 'boolean',
        'skip_if_recently_ordered_within_days' => 'integer',
        'review_urls' => 'array',
    ];

    protected static function booted(): void
    {
        static::saved(function (OrderHandledFlow $flow) {
            // Maximaal 1 actieve flow per trigger_status. Verschillende statussen
            // (handled / shipped / packed / ...) mogen los van elkaar actief zijn.
            if ($flow->is_active && ($flow->wasChanged('is_active') || $flow->wasChanged('trigger_status'))) {
                static::query()
                    ->where('id', '!=', $flow->id)
                    ->where('trigger_status', $flow->trigger_status)
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

    /**
     * Backwards-compat: geeft de eerste actieve flow voor de "handled"-status terug.
     * Nieuwe code gebruikt {@see getActiveForStatus()}.
     */
    public static function getActive(): ?self
    {
        return static::getActiveForStatus('handled');
    }

    public static function getActiveForStatus(string $status): ?self
    {
        return static::query()
            ->where('is_active', true)
            ->where('trigger_status', $status)
            ->first();
    }

    public function activate(): void
    {
        // Alleen flows met dezelfde trigger_status uitschakelen, andere statussen
        // mogen tegelijk actief blijven.
        static::query()
            ->where('id', '!=', $this->id)
            ->where('trigger_status', $this->trigger_status)
            ->update(['is_active' => false]);
        $this->update(['is_active' => true]);
    }

    public function enrollments()
    {
        return $this->hasMany(OrderFlowEnrollment::class, 'flow_id');
    }

    public function activeEnrollments()
    {
        return $this->hasMany(OrderFlowEnrollment::class, 'flow_id')->whereNull('cancelled_at');
    }

    /**
     * Kiest een van de geconfigureerde review-URLs via een gewogen
     * willekeurige trekking. Valt terug op de globale Customsetting
     * 'order_handled_flow_review_url' wanneer geen URLs zijn ingesteld.
     *
     * @return array{label: ?string, url: string}|null
     */
    public function pickReviewUrl(): ?array
    {
        $urls = collect($this->review_urls ?? [])
            ->filter(fn ($entry) => is_array($entry) && ! empty($entry['url']))
            ->values();

        if ($urls->isEmpty()) {
            $fallback = (string) (\Dashed\DashedCore\Models\Customsetting::get('order_handled_flow_review_url') ?: '');
            if ($fallback === '') {
                return null;
            }

            return ['label' => null, 'url' => $fallback];
        }

        // Gewogen willekeurige trekking. Default-gewicht = 1.
        $weights = $urls->map(fn ($u) => max(0.0, (float) ($u['weight'] ?? 1)))->toArray();
        $total = array_sum($weights);

        if ($total <= 0) {
            $picked = $urls->random();
        } else {
            $rand = mt_rand() / mt_getrandmax() * $total;
            $cum = 0.0;
            $picked = $urls->first();
            foreach ($urls as $i => $entry) {
                $cum += $weights[$i];
                if ($rand <= $cum) {
                    $picked = $entry;

                    break;
                }
            }
        }

        return [
            'label' => isset($picked['label']) && $picked['label'] !== '' ? (string) $picked['label'] : null,
            'url' => (string) $picked['url'],
        ];
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
            'trigger_status' => 'handled',
            'is_active' => true,
            'discount_prefix' => null,
            'skip_if_recently_ordered_within_days' => 30,
            'cancel_on_link_click' => true,
        ]);

        $locale = app()->getLocale();

        // Prefix de UUID-keys met een 0-padded index zodat MySQL JSON
        // (dat object-keys alfabetisch sorteert) de blokken altijd in
        // exact deze volgorde teruggeeft. Filament Builder accepteert
        // willekeurige stringkeys, dus de prefix is enkel cosmetisch.
        $buildBlocks = function (array $items): array {
            $blocks = [];
            foreach ($items as $i => $item) {
                $key = sprintf('%04d-%s', $i, (string) Str::uuid());
                $blocks[$key] = $item;
            }

            return $blocks;
        };

        $blocks = $buildBlocks([
            // Blok 1: korte begroeting als koptekst.
            ['type' => 'heading', 'data' => ['content' => 'Hoi :firstName:']],
            // Blok 2: hoofdtekst van de mail met de vraag om een review.
            ['type' => 'paragraph', 'data' => ['content' => '<p>Bedankt dat je bij <strong>:siteName:</strong> hebt besteld (bestelnummer :orderNumber:). We zijn benieuwd hoe je je bestelling vond! Een review helpt ons enorm en helpt andere klanten een goede keuze maken.</p>']],
            // Blok 3: call-to-action knop naar de review-URL.
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
