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
        'images' => 'array',
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

    /**
     * De foto's bij deze regel als URL's, in de volgorde van het formulier.
     *
     * Een media-id gaat via de mediabibliotheek; bestaat het bestand niet meer,
     * dan valt de foto weg, zodat er nooit een kapot plaatje op de offerte
     * staat. Een waarde die al een URL is gaat ongewijzigd door, mits ze naar
     * een gewoon webadres of een pad op deze site wijst: alles wat hier
     * uitkomt belandt in een src- of href-attribuut van een klantdocument.
     *
     * @return array<int, string>
     */
    public function imageUrls(array|string $conversion = 'medium'): array
    {
        $urls = [];

        foreach ((array) ($this->images ?? []) as $image) {
            if (is_int($image) || (is_string($image) && ctype_digit($image))) {
                $media = mediaHelper()->getSingleMedia((int) $image, $conversion);
                $url = is_object($media) ? ($media->url ?? null) : $media;
            } else {
                $url = $image;
            }

            if (is_string($url) && preg_match('#^(https?:)?//\S+$|^/[^/\s]\S*$#i', trim($url))) {
                $urls[] = trim($url);
            }
        }

        return $urls;
    }

    /** Telt deze regel mee in het totaal? */
    public function counts(): bool
    {
        return ! $this->is_optional || $this->is_selected;
    }
}
