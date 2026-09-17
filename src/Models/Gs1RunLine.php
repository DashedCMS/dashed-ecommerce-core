<?php

namespace Dashed\DashedEcommerceCore\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Een GTIN uit de download waar de run iets mee doet. `kind` zegt wat de
 * analyse ervan vond, `decision` wat ermee gebeurt.
 */
class Gs1RunLine extends Model
{
    public const KIND_NAAM_MATCH = 'naam_match';
    public const KIND_POOL = 'pool';
    public const KIND_WEES = 'wees';
    public const KIND_AL_GEKOPPELD = 'al_gekoppeld';

    public const DECISION_LATEN = 'laten';
    public const DECISION_INACTIEF = 'inactief';
    public const DECISION_VRIJGEVEN = 'vrijgeven';
    public const DECISION_KOPPELEN = 'koppelen';
    public const DECISION_TOEWIJZEN = 'toewijzen';

    protected $table = 'dashed__gs1_run_lines';

    protected $fillable = [
        'gs1_run_id', 'gtin', 'sheet_rows', 'gs1_status', 'gs1_description', 'kind', 'decision',
        'product_id', 'previous_product_id', 'reused_inactive', 'skip_reason', 'applied_at', 'reverted_at',
    ];

    protected $casts = [
        'sheet_rows' => 'array',
        'reused_inactive' => 'boolean',
        'applied_at' => 'datetime',
        'reverted_at' => 'datetime',
    ];

    public function run(): BelongsTo
    {
        return $this->belongsTo(Gs1Run::class, 'gs1_run_id');
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class)->withTrashed();
    }

    public function previousProduct(): BelongsTo
    {
        return $this->belongsTo(Product::class, 'previous_product_id')->withTrashed();
    }

    /**
     * Een code die al eens bij GS1 een omschrijving had en nu een nieuwe
     * krijgt. mijnGS1 kan die weigeren; zulke regels zijn terug te draaien.
     */
    public function isReuse(): bool
    {
        return $this->reused_inactive || $this->kind === self::KIND_WEES;
    }

    public function canRevert(): bool
    {
        return $this->applied_at !== null && $this->reverted_at === null && $this->product_id !== null;
    }
}
