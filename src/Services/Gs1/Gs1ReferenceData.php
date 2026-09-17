<?php

namespace Dashed\DashedEcommerceCore\Services\Gs1;

use PhpOffice\PhpSpreadsheet\IOFactory;

/**
 * De keuzelijsten uit het verborgen blad Reference Data van een
 * mijnGS1-download. Bron voor de keuzevelden en voor de controle voordat
 * een waarde het uploadbestand in gaat. Ontbreekt een lijst, dan is elke
 * niet-lege waarde goed: dan is er niets om tegen te toetsen.
 */
final class Gs1ReferenceData
{
    public const COLUMNS = [
        'packagingType' => 'A',
        'country' => 'B',
        'language' => 'C',
        'unit' => 'D',
        'classification' => 'E',
        'status' => 'F',
        'consumerUnit' => 'G',
    ];

    /**
     * @param  array<string, list<string>>  $lists
     */
    private function __construct(private readonly array $lists)
    {
    }

    public static function fromFile(string $path): self
    {
        $reader = IOFactory::createReaderForFile($path);
        $reader->setReadDataOnly(true);
        $reader->setLoadSheetsOnly(['Reference Data']);
        $sheet = $reader->load($path)->getSheetByName('Reference Data');

        if (! $sheet) {
            return new self([]);
        }

        $lists = [];
        foreach (self::COLUMNS as $field => $column) {
            $highest = $sheet->getHighestDataRow($column);
            $values = [];
            foreach ($sheet->rangeToArray("{$column}1:{$column}{$highest}", null, false, false) as $row) {
                $value = trim((string) ($row[0] ?? ''));
                if ($value !== '') {
                    $values[] = $value;
                }
            }
            $lists[$field] = array_values(array_unique($values));
        }

        return new self($lists);
    }

    public static function fromArray(array $lists): self
    {
        return new self($lists);
    }

    public function toArray(): array
    {
        return $this->lists;
    }

    public function has(string $field): bool
    {
        return ! empty($this->lists[$field]);
    }

    /**
     * @return array<string, string>
     */
    public function options(string $field): array
    {
        $values = $this->lists[$field] ?? [];

        return array_combine($values, $values);
    }

    public function isValid(string $field, ?string $value): bool
    {
        if ($value === null || trim($value) === '') {
            return false;
        }

        if (! $this->has($field)) {
            return true;
        }

        return in_array($value, $this->lists[$field], true);
    }
}
