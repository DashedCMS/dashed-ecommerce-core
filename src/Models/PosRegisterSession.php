<?php

declare(strict_types=1);

namespace Dashed\DashedEcommerceCore\Models;

use Dashed\DashedCore\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Kassasessie (dagafsluiting / kasstaat — Z-rapport).
 *
 * Eén open sessie per (user + site + dag): bij openen wordt `opened_at` en de
 * startkas (`opening_float`) vastgelegd; bij afsluiten `closed_at`, de getelde
 * kas (`counted_cash`), de verwachte kas (`expected_cash`), het verschil
 * (`difference`) en een snapshot van de omzet per betaalmethode (`totals`).
 */
class PosRegisterSession extends Model
{
    protected $table = 'dashed__pos_register_sessions';

    protected $guarded = [];

    protected $casts = [
        'opened_at' => 'datetime',
        'closed_at' => 'datetime',
        'opening_float' => 'decimal:2',
        'counted_cash' => 'decimal:2',
        'expected_cash' => 'decimal:2',
        'difference' => 'decimal:2',
        'totals' => 'array',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function scopeOpen($query)
    {
        return $query->whereNull('closed_at');
    }

    public function isOpen(): bool
    {
        return $this->closed_at === null;
    }
}
