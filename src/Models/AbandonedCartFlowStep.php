<?php

namespace Dashed\DashedEcommerceCore\Models;

use Illuminate\Database\Eloquent\Model;
use Spatie\Translatable\HasTranslations;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AbandonedCartFlowStep extends Model
{
    use HasTranslations;

    protected $table = 'dashed__abandoned_cart_flow_steps';

    protected $fillable = [
        'flow_id',
        'sort_order',
        'delay_value',
        'delay_unit',
        'subject',
        'intro_text',
        'blocks',
        'button_label',
        'show_products',
        'show_review',
        'incentive_enabled',
        'incentive_type',
        'incentive_value',
        'incentive_valid_days',
        'enabled',
    ];

    public $translatable = [
        'subject',
        'blocks',
    ];

    protected $casts = [
        'blocks' => 'array',
        'show_products' => 'boolean',
        'show_review' => 'boolean',
        'incentive_enabled' => 'boolean',
        'incentive_value' => 'float',
        'enabled' => 'boolean',
    ];

    public function flow(): BelongsTo
    {
        return $this->belongsTo(AbandonedCartFlow::class, 'flow_id');
    }

    public function emails(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(AbandonedCartEmail::class, 'flow_step_id');
    }

    public function getDelayInHoursAttribute(): int
    {
        return match ($this->delay_unit) {
            'days' => $this->delay_value * 24,
            default => $this->delay_value,
        };
    }

    public function getDelayLabelAttribute(): string
    {
        return $this->delay_value . ' ' . match ($this->delay_unit) {
            'days' => $this->delay_value === 1 ? 'dag' : 'dagen',
            default => $this->delay_value === 1 ? 'uur' : 'uur',
        };
    }
}
