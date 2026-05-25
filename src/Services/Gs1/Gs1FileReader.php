<?php

namespace Dashed\DashedEcommerceCore\Services\Gs1;

use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use Dashed\DashedEcommerceCore\ValueObjects\Gs1Row;

/**
 * Leest een GS1 contract-Excel uit en levert de productrijen plus
 * metadata over welke sheet de contractsheet is. De Reference Data
 * en Instructies sheets worden genegeerd.
 */
class Gs1FileReader
{
    private const IGNORED_SHEETS = [
        'Reference Data',
        'Instructies',
    ];

    private const PLACEHOLDER_TEMPLATE_SHEET = '{ContractNr}';

    public function read(string $absolutePath): Gs1FileContents
    {
        $spreadsheet = IOFactory::load($absolutePath);
        [$contractSheetName, $contractNumber] = $this->resolveContractSheet($spreadsheet);

        $sheet = $spreadsheet->getSheetByName($contractSheetName);
        $rows = [];

        foreach ($sheet->getRowIterator(2) as $rowIndex => $row) {
            $values = [];
            $cellIterator = $row->getCellIterator('A', 'M');
            $cellIterator->setIterateOnlyExistingCells(false);
            foreach ($cellIterator as $cell) {
                $values[] = $cell->getValue();
            }

            $gs1Row = Gs1Row::fromArray($values);

            if ($this->isEmptyRow($gs1Row)) {
                continue;
            }

            $rows[$rowIndex] = $gs1Row;
        }

        return new Gs1FileContents(
            contractSheetName: $contractSheetName,
            contractNumber: $contractNumber,
            rows: $rows,
        );
    }

    /**
     * @return array{0: string, 1: ?string} [sheet name, contract number]
     */
    private function resolveContractSheet(Spreadsheet $spreadsheet): array
    {
        $candidates = [];

        foreach ($spreadsheet->getSheetNames() as $name) {
            if (in_array($name, self::IGNORED_SHEETS, true)) {
                continue;
            }
            if ($name === self::PLACEHOLDER_TEMPLATE_SHEET) {
                continue;
            }
            $candidates[] = $name;
        }

        if (count($candidates) === 0) {
            throw new \RuntimeException('Geen contract sheet gevonden in het GS1-bestand.');
        }

        if (count($candidates) > 1) {
            throw new \RuntimeException(
                'Meerdere mogelijke contract sheets gevonden: ' . implode(', ', $candidates)
            );
        }

        $name = $candidates[0];
        $contractNumber = ctype_digit($name) ? $name : null;

        return [$name, $contractNumber];
    }

    private function isEmptyRow(Gs1Row $row): bool
    {
        return $row->gtin === null
            && $row->status === null
            && $row->description === null;
    }
}
