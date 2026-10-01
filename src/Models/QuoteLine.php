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
     * Een media-id gaat via de mediabibliotheek; bestaat het bestand niet meer
     * of gaat het opzoeken mis, dan valt de foto weg, zodat er nooit een kapot
     * plaatje op de offerte staat en de pagina of PDF niet breekt. Elke
     * kandidaat gaat door `veiligeUrl()`: alleen http(s)-adressen, protocol-
     * relatieve adressen en paden op deze site komen er uit, met spatie en
     * haakjes gecodeerd, zodat het resultaat veilig in een src-, href- of
     * CSS-url() kan, zonder dat elke aanroeper zelf hoeft te escapen.
     *
     * @return array<int, string>
     */
    public function imageUrls(array|string $conversion = 'medium'): array
    {
        $urls = [];

        foreach ((array) ($this->images ?? []) as $image) {
            if (is_int($image) || (is_string($image) && ctype_digit($image))) {
                $url = null;

                if ((int) $image > 0) {
                    try {
                        $media = mediaHelper()->getSingleMedia((int) $image, $conversion);
                        $url = is_object($media) ? ($media->url ?? null) : $media;
                    } catch (\Throwable $e) {
                        report($e);
                    }
                }
            } else {
                $url = $image;
            }

            if ($url = self::veiligeUrl($url)) {
                $urls[] = $url;
            }
        }

        return $urls;
    }

    private static function veiligeUrl(mixed $url): ?string
    {
        if (! is_string($url) || ($url = trim($url)) === '') {
            return null;
        }

        $url = strtr($url, [' ' => '%20', '(' => '%28', ')' => '%29', "'" => '%27']);

        if (preg_match('/["<>\\\\`\s\x00-\x1f\x7f]/', $url)) {
            return null;
        }

        return preg_match('#^(https?://[^/]|//[^/]|/[^/\\\\])#i', $url) ? $url : null;
    }

    /** Telt deze regel mee in het totaal? */
    public function counts(): bool
    {
        return ! $this->is_optional || $this->is_selected;
    }
}
