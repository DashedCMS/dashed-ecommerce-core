<?php

namespace Dashed\DashedEcommerceCore\Services\Gs1;

use ZipArchive;

/**
 * Leest de keuzelijsten van een blad rechtstreeks uit de XML. mijnGS1 zet
 * ze als x14-uitbreiding neer (een lijst die naar een ander blad wijst).
 * PhpSpreadsheet leest die wel in, maar bij het weghalen van rijen laat
 * removeRow() ze vallen (1.30) of verschuift ze (5.x). Gs1FileUpdater wist
 * ze daarom en zet met deze gegevens de originele bereiken terug.
 */
final class Gs1ValidationLists
{
    private const RELATIONSHIPS_NS = 'http://schemas.openxmlformats.org/officeDocument/2006/relationships';

    /**
     * @return list<array{0: string, 1: string}> [formule, sqref]
     */
    public function read(string $xlsxPath, string $sheetName): array
    {
        $zip = new ZipArchive();
        if ($zip->open($xlsxPath) !== true) {
            return [];
        }

        try {
            $sheetPath = $this->sheetPath($zip, $sheetName);
            if (! $sheetPath) {
                return [];
            }

            $xml = (string) $zip->getFromName($sheetPath);
            preg_match_all(
                '/<x14:dataValidation\b[^>]*>.*?<xm:f>(.*?)<\/xm:f>.*?<xm:sqref>(.*?)<\/xm:sqref>.*?<\/x14:dataValidation>/s',
                $xml,
                $matches,
                PREG_SET_ORDER,
            );

            return array_map(
                fn (array $match) => [html_entity_decode($match[1], ENT_QUOTES | ENT_XML1), trim($match[2])],
                $matches,
            );
        } finally {
            $zip->close();
        }
    }

    private function sheetPath(ZipArchive $zip, string $sheetName): ?string
    {
        $workbook = simplexml_load_string((string) $zip->getFromName('xl/workbook.xml'));
        $relations = simplexml_load_string((string) $zip->getFromName('xl/_rels/workbook.xml.rels'));
        if (! $workbook || ! $relations) {
            return null;
        }

        $relationId = null;
        foreach ($workbook->sheets->sheet as $sheet) {
            if ((string) $sheet['name'] === $sheetName) {
                $relationId = (string) $sheet->attributes(self::RELATIONSHIPS_NS)['id'];
            }
        }
        if (! $relationId) {
            return null;
        }

        foreach ($relations->Relationship as $relation) {
            if ((string) $relation['Id'] === $relationId) {
                $target = ltrim((string) $relation['Target'], '/');

                return str_starts_with($target, 'xl/') ? $target : 'xl/' . $target;
            }
        }

        return null;
    }
}
