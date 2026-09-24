<?php

namespace Dashed\DashedEcommerceCore\Services\Quotes;

use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Facades\View;
use Illuminate\Support\Facades\Storage;
use Dashed\DashedEcommerceCore\Models\Quote;

/**
 * De offerte-PDF. Twee bestanden per offerte: het document zoals het verstuurd
 * is, en bij akkoord een tweede met de regels die de klant koos en het
 * akkoordblok ingevuld. Dat tweede is het bewijsstuk.
 *
 * Paden hebben geen leidende slash, anders struikelt attachFromStorageDisk
 * erover (zie AttachesInvoice).
 */
class QuotePdf
{
    public static function path(Quote $quote, bool $accepted = false): string
    {
        return self::pathFor($quote, $accepted ? '-akkoord' : '');
    }

    /**
     * Het voorbeeld van de beheerder heeft zijn eigen pad. Zonder dat schrijft
     * een klik op Voorbeeld bij een al verstuurde offerte precies het bestand
     * over dat de klant gemaild kreeg en dat de publieke pagina aanbiedt.
     *
     * Dit staat bewust naast de boolean $accepted en niet als derde waarde
     * daarvan: die boolean onderscheidt de twee documenten van de klant, het
     * verstuurde en het bewijsstuk. Het voorbeeld is een ander soort ding, met
     * een andere lezer (de beheerder), een wegwerplevensduur en eigen toegang.
     * Een drietrapskeuze zou elke bestaande aanroep dwingen een toestand te
     * noemen, en het voorbeeldpad bereikbaar maken vanaf plekken die het
     * klantdocument bedoelen.
     */
    public static function previewPath(Quote $quote): string
    {
        return self::pathFor($quote, '-voorbeeld');
    }

    private static function pathFor(Quote $quote, string $suffix): string
    {
        $name = $quote->quote_number ?: ('concept-'.$quote->id);

        return 'dashed/quotes/quote-'.$name.'-v'.$quote->version.'-'.$quote->hash.$suffix.'.pdf';
    }

    public static function render(Quote $quote, bool $accepted = false): string
    {
        $totals = QuoteTotals::for($quote);

        $html = View::make('dashed-ecommerce-core::quotes.quote', [
            'quote' => $quote,
            'totals' => $totals,
            'accepted' => $accepted,
        ])->render();

        $pdf = App::make('dompdf.wrapper');
        $pdf->loadHTML($html);

        return $pdf->output();
    }

    /**
     * Altijd opnieuw schrijven. Anders dan een factuur kan een offerte tussen
     * twee verstuurmomenten veranderen, en dan zou een bestaand bestand de
     * oude inhoud blijven tonen.
     */
    public static function store(Quote $quote, bool $accepted = false): string
    {
        $path = self::path($quote, $accepted);

        Storage::disk('dashed')->put($path, self::render($quote, $accepted), 'private');

        return $path;
    }

    public static function downloadUrl(Quote $quote, bool $accepted = false): ?string
    {
        if (! Storage::disk('dashed')->exists(self::path($quote, $accepted))) {
            return null;
        }

        return URL::signedRoute('dashed.frontend.quote-download', [
            'hash' => $quote->hash,
            'accepted' => $accepted ? 1 : 0,
        ]);
    }

    /** Het voorbeeld opnieuw schrijven en de ondertekende link erop teruggeven. */
    public static function storePreview(Quote $quote): string
    {
        $path = self::previewPath($quote);

        Storage::disk('dashed')->put($path, self::render($quote), 'private');

        return $path;
    }

    public static function previewDownloadUrl(Quote $quote): ?string
    {
        if (! Storage::disk('dashed')->exists(self::previewPath($quote))) {
            return null;
        }

        return URL::signedRoute('dashed.frontend.quote-download', [
            'hash' => $quote->hash,
            'preview' => 1,
        ]);
    }
}
