<?php

declare(strict_types=1);

namespace Dashed\DashedEcommerceCore\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AutomationRuleRun extends Model
{
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
