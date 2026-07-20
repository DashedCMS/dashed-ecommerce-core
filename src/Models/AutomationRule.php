<?php

declare(strict_types=1);

namespace Dashed\DashedEcommerceCore\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\HasMany;

class AutomationRule extends Model
{
    protected $table = 'dashed__automation_rules';

    protected $fillable = [
        'site_id',
        'name',
        'trigger',
        'conditions',
        'actions',
        'is_active',
    ];

    protected $casts = [
        'conditions' => 'array',
        'actions' => 'array',
        'is_active' => 'boolean',
    ];

    public function runs(): HasMany
    {
        return $this->hasMany(AutomationRuleRun::class, 'rule_id');
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    public function scopeForTrigger(Builder $query, string $key): Builder
    {
        return $query->where('trigger', $key);
    }
}
