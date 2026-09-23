<?php

namespace Dashed\DashedEcommerceCore\Models;

use Illuminate\Database\Eloquent\Model;
use Dashed\DashedEcommerceCore\Classes\VatDisplay;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class QuoteLine extends Model
{
    protected $table = 'dashed__quote_lines';

    protected $guarded = [];

    protected $casts = [
        'quantity' => 'integer',
        'unit_price' => 'decimal:2',
        'vat_rate' => 'decimal:2',
        'is_optional' => 'boolean',
        'is_selected' => 'boolean',
        'sort_order' => 'integer',
    ];

    public function quote(): BelongsTo
    {
        return $this->belongsTo(Quote::class);
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    /** Regeltotaal inclusief btw. */
    public function lineTotal(): float
    {
        return round((float) $this->unit_price * (int) $this->quantity, 2);
    }

    public function lineTotalExVat(): float
    {
        return round(VatDisplay::exFromIncl($this->lineTotal(), (float) $this->vat_rate), 2);
    }

    public function lineVat(): float
    {
        return round($this->lineTotal() - $this->lineTotalExVat(), 2);
    }

    public function unitPriceExVat(): float
    {
        return round(VatDisplay::exFromIncl((float) $this->unit_price, (float) $this->vat_rate), 2);
    }

    /** Telt deze regel mee in het totaal? */
    public function counts(): bool
    {
        return ! $this->is_optional || $this->is_selected;
    }
}
