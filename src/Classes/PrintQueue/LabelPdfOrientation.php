<?php

declare(strict_types=1);

namespace Dashed\DashedEcommerceCore\Classes\PrintQueue;

use Throwable;

/**
 * Draait liggende pagina's van een verzendlabel-PDF naar staand.
 *
 * De print-daemon print met een kaal `lp -d <printer>`, zonder formaat- of
 * oriëntatieopties, dus een liggend A6-label komt op een labelprinter met een
 * staande rol verkeerd uit. Veloyd levert sinds 1 november 2026 (andere
 * labelgenerator, ander PostNL-klantnummer) liggende labels aan waar het eerder
 * staande waren. Elke staande pagina blijft ongemoeid; mislukt het draaien, dan
 * gaat het origineel door, want een verkeerd formaat is beter dan geen label.
 */
class LabelPdfOrientation
{
    public static function portrait(string $pdf): string
    {
        if (! self::needsRotation($pdf)) {
            return $pdf;
        }

        try {
            return self::rotateLandscapePages($pdf) ?? $pdf;
        } catch (Throwable $e) {
            report($e);

            return $pdf;
        }
    }

    /**
     * Goedkope voorcontrole op de ruwe bytes, zodat de parser alleen een PDF
     * ziet die hij aankan en die echt gedraaid moet worden. Dat is geen
     * optimalisatie: de meegeleverde TCPDI-parser roept bij een PDF die hij niet
     * begrijpt die() aan in plaats van een exception te gooien, en dat breekt
     * het hele verzoek af. Daarom alleen een klassieke PDF (geen object- of
     * xref-streams, waarin paginagegevens gecomprimeerd staan) met minstens één
     * liggende MediaBox en nergens al een /Rotate anders dan 0.
     */
    private static function needsRotation(string $pdf): bool
    {
        if (! str_starts_with($pdf, '%PDF-') || ! str_contains($pdf, 'startxref')) {
            return false;
        }

        if (preg_match('#/Type\s*/(ObjStm|XRef)\b#', $pdf) || preg_match('#/Rotate\s+-?[1-9]#', $pdf)) {
            return false;
        }

        if (! preg_match_all('#/MediaBox\s*\[\s*(-?[\d.]+)\s+(-?[\d.]+)\s+(-?[\d.]+)\s+(-?[\d.]+)\s*\]#', $pdf, $boxes, PREG_SET_ORDER)) {
            return false;
        }

        foreach ($boxes as $box) {
            if (abs((float) $box[3] - (float) $box[1]) > abs((float) $box[4] - (float) $box[2])) {
                return true;
            }
        }

        return false;
    }

    /**
     * Null als er niets te draaien valt, zodat het origineel byte voor byte
     * doorgaat.
     */
    private static function rotateLandscapePages(string $pdf): ?string
    {
        if (! class_exists(\TCPDI::class, false)) {
            $base = dirname((new \ReflectionClass(\LynX39\LaraPdfMerger\PdfManage::class))->getFileName());
            require_once $base . '/tcpdf/tcpdf.php';
            require_once $base . '/tcpdf/tcpdi.php';
        }

        $out = new \TCPDI('P', 'pt');
        $out->setPrintHeader(false);
        $out->setPrintFooter(false);
        $out->SetMargins(0, 0, 0);
        $out->SetAutoPageBreak(false, 0);

        $pages = $out->setSourceData($pdf);
        $rotated = false;

        for ($i = 1; $i <= $pages; $i++) {
            $template = $out->importPage($i);
            $size = $out->getTemplateSize($template);
            $w = (float) $size['w'];
            $h = (float) $size['h'];

            if ($w <= $h) {
                $out->AddPage('P', [$w, $h]);
                $out->useTemplate($template, 0, 0, $w, $h);

                continue;
            }

            // Staande pagina h x w. Een kwartslag tegen de klok in rond de
            // linkeronderhoek: getekend vanaf (0, w) valt de liggende inhoud
            // precies in [0, h] x [0, w].
            $out->AddPage('P', [$h, $w]);
            $out->StartTransform();
            $out->Rotate(90, 0, $w);
            $out->useTemplate($template, 0, $w, $w, $h);
            $out->StopTransform();
            $rotated = true;
        }

        if (! $rotated) {
            return null;
        }

        return $out->Output('label.pdf', 'S');
    }
}
