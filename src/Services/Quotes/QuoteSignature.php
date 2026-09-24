<?php

namespace Dashed\DashedEcommerceCore\Services\Quotes;

/**
 * De getekende handtekening op de akkoordpagina. De browser stuurt een
 * PNG-data-URL uit het tekenvlak; die komt uit de Livewire-staat en is dus
 * vrij door de klant in te vullen. Daarom alleen een echte PNG van
 * redelijke omvang, en de data-URL wordt opnieuw opgebouwd uit de
 * gedecodeerde bytes in plaats van de string van de klant over te nemen.
 * Die string belandt later letterlijk in een src-attribuut van de PDF.
 */
class QuoteSignature
{
    public const MAX_BYTES = 300_000;

    private const PREFIX = 'data:image/png;base64,';

    /** Een schone data-URL, of null als dit geen bruikbare handtekening is. */
    public static function normalize(?string $dataUrl): ?string
    {
        if (! $dataUrl || ! str_starts_with($dataUrl, self::PREFIX)) {
            return null;
        }

        $bytes = base64_decode(substr($dataUrl, strlen(self::PREFIX)), true);

        if ($bytes === false || $bytes === '' || strlen($bytes) > self::MAX_BYTES) {
            return null;
        }

        $info = @getimagesizefromstring($bytes);

        if (! $info || $info[2] !== IMAGETYPE_PNG || $info[0] < 50 || $info[1] < 20) {
            return null;
        }

        return self::PREFIX.base64_encode($bytes);
    }
}
