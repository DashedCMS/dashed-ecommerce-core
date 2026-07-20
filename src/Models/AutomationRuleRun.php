<?php

declare(strict_types=1);

namespace Dashed\DashedEcommerceCore\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AutomationRuleRun extends Model
{
    /**
     * Geclaimd, acties draaien nog (of het worker-proces is halverwege
     * gecrasht — zie AutomationEngine::STALE_RUNNING_MINUTES). Een rij in
     * deze status is een échte, in-flight run: hij telt mee voor laag 2 van
     * de lus-beveiliging, precies zoals success/failed dat al deden.
     */
    public const STATUS_RUNNING = 'running';
    public const STATUS_SUCCESS = 'success';
    public const STATUS_FAILED = 'failed';

    protected $table = 'dashed__automation_rule_runs';

    protected $fillable = [
        'rule_id',
        'site_id',
        'subject_type',
        'subject_id',
        'trigger',
        'status',
        'results',
        'error',
    ];

    protected $casts = [
        'results' => 'array',
    ];

    public function subject(): MorphTo
    {
        return $this->morphTo();
    }

    public function rule(): BelongsTo
    {
        return $this->belongsTo(AutomationRule::class, 'rule_id');
    }
}
