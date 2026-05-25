<?php

namespace Dashed\DashedEcommerceCore\Services\Gs1;

use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Cell\DataType;
use Dashed\DashedEcommerceCore\ValueObjects\Gs1Row;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx as XlsxWriter;

/**
 * Bouwt een vers Excel-bestand met de 13 GS1-kolommen. Geen Reference
 * Data of Instructies sheet — de gebruiker plakt deze rijen in zijn
 * eigen mijnGS1 contractbestand, of upload deze direct in mijnGS1.
 */
class Gs1FileWriter
{
    private const HEADERS = [
        'GS1 Artikelcode (GTIN)',
        'Status',
        'Productclassificatie',
        'Gaat naar de consument',
        'Verpakkings type',
        "Landen of Regio's",
        'Productomschrijving (max 300 tekens)',
        'Taal',
        'Merk',
        'Submerk',
        'Aantal',
        'Eenheid',
        'Afbeelding (max 500 tekens)',
    ];

    /**
     * @param  array<int, Gs1Row>  $rows
     */
    public function write(array $rows, string $outputPath, string $sheetName = 'Producten'): void
    {
        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle($sheetName);

        foreach (self::HEADERS as $index => $header) {
            $sheet->setCellValueByColumnAndRow($index + 1, 1, $header);
        }

        $rowNumber = 2;
        foreach ($rows as $row) {
            $values = $row->toArray();
            foreach ($values as $columnIndex => $value) {
                $sheet->setCellValueExplicitByColumnAndRow(
                    $columnIndex + 1,
                    $rowNumber,
                    $value ?? '',
                    DataType::TYPE_STRING,
                );
            }
            $rowNumber++;
        }

        foreach (range(1, count(self::HEADERS)) as $column) {
            $sheet->getColumnDimensionByColumn($column)->setAutoSize(true);
        }

        (new XlsxWriter($spreadsheet))->save($outputPath);
    }
}
