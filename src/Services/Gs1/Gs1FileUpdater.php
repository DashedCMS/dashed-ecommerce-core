<?php

namespace Dashed\DashedEcommerceCore\Services\Gs1;

use Illuminate\Support\Str;
use Illuminate\Support\Facades\Storage;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Cell\DataType;
use Dashed\DashedEcommerceCore\Models\Gs1Run;
use Dashed\DashedEcommerceCore\Models\Product;
use Dashed\DashedEcommerceCore\Models\Gs1RunLine;
use PhpOffice\PhpSpreadsheet\Cell\DataValidation;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use Dashed\DashedEcommerceCore\ValueObjects\Gs1Row;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx as XlsxWriter;

/**
 * Schrijft het bestand dat terug naar mijnGS1 gaat, uitgaande van het
 * origineel zodat Reference Data en de bladen blijven staan. mijnGS1 vraagt
 * de regels die niet wijzigen weg te halen, dus alleen wat deze run
 * verandert blijft over, met plaatshouders onderaan.
 */
class Gs1FileUpdater
{
    public function __construct(
        private readonly Gs1FileReader $reader,
        private readonly Gs1ValidationLists $validationLists,
    ) {
    }

    public function write(Gs1Run $run, Gs1Plan $plan): string
    {
        $source = Storage::disk('local')->path($run->file_path);
        $validations = $this->validationLists->read($source, $run->contract_sheet);
        $book = IOFactory::load($source);
        $sheet = $book->getSheetByName($run->contract_sheet);
        $original = $this->reader->rowsFromSheet($sheet);
        $resolver = new Gs1MetaResolver($run->site_id);

        $out = [];
        $multi = [];
        $lines = $run->lines()->whereNotNull('applied_at')->whereNull('reverted_at')->get();

        foreach ($lines as $line) {
            $rowNumber = $line->sheet_rows[0];
            $row = $original[$rowNumber] ?? null;
            $newRow = $this->rowFor($line, $row, $resolver);

            if ($newRow === null) {
                continue;
            }

            $out[$rowNumber] = $newRow;
            if (count($line->sheet_rows) > 1) {
                $multi[] = $line->gtin;
            }
        }
        ksort($out);

        $highest = $sheet->getHighestRow();
        if ($highest > 1) {
            $sheet->removeRow(2, $highest - 1);
        }

        $rowNumber = 2;
        foreach ($out as $row) {
            $this->writeRow($sheet, $rowNumber++, $row);
        }
        foreach ($plan->placeholders as $index => $productId) {
            $product = Product::with(['productCategories', 'productGroup'])->find($productId);
            if ($product) {
                $this->writeRow($sheet, $rowNumber++, $resolver->resolve($product, (string) ($index + 1)));
            }
        }

        $this->restoreValidations($sheet, $validations);
        $book->setActiveSheetIndex($book->getIndex($sheet));

        $relative = 'gs1-runs/' . $run->id . '/' . Str::slug($run->contract_sheet ?: 'gs1') . '-gs1-upload-' . now()->format('Y-m-d-His') . '.xlsx';
        Storage::disk('local')->makeDirectory(dirname($relative));
        (new XlsxWriter($book))->save(Storage::disk('local')->path($relative));

        $run->summary = array_merge($run->summary ?? [], [
            'multi_row_gtins' => array_values(array_unique($multi)),
            'file_rows' => $rowNumber - 2,
        ]);
        $run->result_path = $relative;
        $run->save();

        return $relative;
    }

    private function rowFor(Gs1RunLine $line, ?Gs1Row $row, Gs1MetaResolver $resolver): ?Gs1Row
    {
        $isNewCode = $line->kind === Gs1RunLine::KIND_POOL
            || ($line->kind === Gs1RunLine::KIND_WEES && $line->decision === Gs1RunLine::DECISION_VRIJGEVEN);

        if ($isNewCode) {
            $product = Product::with(['productCategories', 'productGroup'])->find($line->product_id);

            return $product ? $resolver->resolve($product, $line->gtin) : null;
        }

        if ($line->kind === Gs1RunLine::KIND_NAAM_MATCH && $row && ! $row->isActive()) {
            $row->status = 'Actief';

            return $row;
        }

        if ($line->kind === Gs1RunLine::KIND_WEES && $line->decision === Gs1RunLine::DECISION_INACTIEF && $row) {
            $row->status = 'Inactief';

            return $row;
        }

        return null;
    }

    private function writeRow(Worksheet $sheet, int $rowNumber, Gs1Row $row): void
    {
        foreach ($row->toArray() as $columnIndex => $value) {
            $sheet->setCellValueExplicit([$columnIndex + 1, $rowNumber], $value === null ? '' : (string) $value, DataType::TYPE_STRING);
        }
    }

    /**
     * @param  list<array{0: string, 1: string}>  $validations
     */
    private function restoreValidations(Worksheet $sheet, array $validations): void
    {
        // PhpSpreadsheet (1.30 en 5.x) leest de x14-uitbreiding bij het
        // laden in als gewone validaties op het blad. removeRow() laat die
        // daarna vallen (1.30) of verschuift ze naar een ander bereik (5.x).
        // Daarom wissen we wat er nog staat en zetten we de originele
        // bereiken uit Gs1ValidationLists terug. Zonder wissen komen
        // verschoven lijsten naast de teruggezette te staan: dubbele,
        // overlappende validaties, en Excel meldt het bestand dan als
        // beschadigd.
        foreach (array_keys($sheet->getDataValidationCollection()) as $range) {
            $sheet->setDataValidation($range, null);
        }

        foreach ($validations as [$formula, $sqref]) {
            foreach (preg_split('/\s+/', $sqref) as $range) {
                $validation = new DataValidation();
                $validation->setType(DataValidation::TYPE_LIST)
                    ->setAllowBlank(true)
                    ->setShowDropDown(true)
                    ->setShowInputMessage(true)
                    ->setShowErrorMessage(true)
                    ->setFormula1($formula);

                $sheet->setDataValidation($range, $validation);
            }
        }
    }
}
