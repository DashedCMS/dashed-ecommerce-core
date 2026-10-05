<?php

namespace Dashed\DashedEcommerceCore\Models;

use Illuminate\Support\Facades\DB;
use Illuminate\Database\Eloquent\Model;
use Dashed\DashedEcommerceCore\Classes\VatDisplay;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class QuoteLine extends Model
{
    /**
     * Conversie voor de foto's in de PDF: 400 px breed (dashed-files `small`),
     * ruim genoeg voor een foto van 72 px hoog in druk, en veel lichter dan
     * `medium` (800 px) in een PDF die per mail gaat.
     */
    public const PDF_IMAGE_CONVERSION = 'small';

    /** Het bibliotheek-item achter een media-id (dashed-files); geen dependency van dit pakket. */
    private const MEDIA_ITEM_CLASS = 'RalphJSmit\\Filament\\MediaLibrary\\Models\\MediaLibraryItem';

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

    protected static function booted(): void
    {
        // Warm de conversies op zodra de foto's van een regel veranderen. Voor een
        // net geüploade foto geeft de mediabibliotheek bij de eerste aanvraag het
        // origineel en zet ze de conversie pas in de wachtrij; zonder dit is die
        // eerste aanvraag meestal de PDF die de klant gemaild krijgt.
        static::saved(function (QuoteLine $line) {
            if (empty($line->images) || ! ($line->wasRecentlyCreated || $line->wasChanged('images'))) {
                return;
            }

            // Pas na de commit. Regels ontstaan vaak binnen een transactie (een
            // revisie, een offerte uit een berekening); de wachtrij-job die de
            // conversie maakt ziet het media-item dan nog zonder de aangevraagde
            // conversie en slaat haar over, waarna de PDF het origineel houdt.
            // Zonder lopende transactie draait dit meteen.
            DB::afterCommit(function () use ($line) {
                try {
                    $line->imagePairs();
                    $line->imageUrls(self::PDF_IMAGE_CONVERSION);
                } catch (\Throwable $e) {
                    report($e);
                }
            });
        });
    }

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
     * Een media-id gaat via de mediabibliotheek; staat het item er niet meer in
     * (één query per aanroep, want de helper cachet een URL voor altijd), bestaat
     * het bestand niet meer of gaat het opzoeken mis, dan valt de foto weg, zodat
     * er nooit een kapot plaatje op de offerte staat en de pagina of PDF niet
     * breekt. Elke kandidaat gaat door `safeUrl()`: alleen http(s)-adressen, protocol-
     * relatieve adressen en paden op deze site komen er uit, met spatie en
     * haakjes gecodeerd, zodat het resultaat veilig in een src-, href- of
     * CSS-url() kan, zonder dat elke aanroeper zelf hoeft te escapen.
     *
     * @return array<int, string>
     */
    public function imageUrls(array|string $conversion = 'medium'): array
    {
        $urls = [];
        $existing = $this->existingMediaIds();

        foreach ((array) ($this->images ?? []) as $image) {
            if ($url = self::resolveImageUrl($image, $conversion, $existing)) {
                $urls[] = $url;
            }
        }

        return $urls;
    }

    /**
     * De foto's van deze regel als paren, in één ronde langs de opgeslagen
     * waarden: `thumb` is de medium-URL, `original` de originele. Een foto
     * zonder bruikbare thumb valt helemaal weg; kan het origineel niet worden
     * opgehaald, dan wijst `original` naar de thumb. Zo lopen miniatuur en
     * link nooit uit de pas, wat twee losse `imageUrls()`-lijsten wel kunnen.
     *
     * @return array<int, array{thumb: string, original: string}>
     */
    public function imagePairs(): array
    {
        $pairs = [];
        $existing = $this->existingMediaIds();

        foreach ((array) ($this->images ?? []) as $image) {
            $thumb = self::resolveImageUrl($image, 'medium', $existing);

            if (! $thumb) {
                continue;
            }

            $pairs[] = [
                'thumb' => $thumb,
                'original' => self::resolveImageUrl($image, 'original', $existing) ?? $thumb,
            ];
        }

        return $pairs;
    }

    /**
     * De media-id's van deze regel die nog in de mediabibliotheek staan, in één
     * query. Null als dat niet na te gaan is (dashed-files niet geïnstalleerd of
     * de query faalt): dan geldt het oude gedrag en beslist de helper zelf.
     *
     * @return array<int, true>|null
     */
    private function existingMediaIds(): ?array
    {
        $ids = [];

        foreach ((array) ($this->images ?? []) as $image) {
            if ((is_int($image) || (is_string($image) && ctype_digit($image))) && (int) $image > 0) {
                $ids[] = (int) $image;
            }
        }

        if (! $ids || ! class_exists(self::MEDIA_ITEM_CLASS)) {
            return null;
        }

        try {
            $class = self::MEDIA_ITEM_CLASS;
            $model = new $class();

            return array_fill_keys(
                array_map('intval', $class::query()->whereKey(array_unique($ids))->pluck($model->getKeyName())->all()),
                true,
            );
        } catch (\Throwable $e) {
            report($e);

            return null;
        }
    }

    /**
     * Eén opgeslagen foto (media-id of URL) als veilige URL voor deze conversie, of null.
     *
     * @param  array<int, true>|null  $existing  bestaande media-id's, of null als dat onbekend is
     */
    private static function resolveImageUrl(mixed $image, array|string $conversion, ?array $existing = null): ?string
    {
        if (is_int($image) || (is_string($image) && ctype_digit($image))) {
            $url = null;

            if ((int) $image > 0 && ($existing === null || isset($existing[(int) $image]))) {
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

        return self::safeUrl($url);
    }

    private static function safeUrl(mixed $url): ?string
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
