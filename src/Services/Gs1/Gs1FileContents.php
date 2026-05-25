<?php

namespace Dashed\DashedEcommerceCore\Services\Gs1;

use Dashed\DashedEcommerceCore\ValueObjects\Gs1Row;

/**
 * @property array<int, Gs1Row> $rows  Rijnummer (1-based, header = 1) => row
 */
class Gs1FileContents
{
    public function __construct(
        public readonly string $contractSheetName,
        public readonly ?string $contractNumber,
        public readonly array $rows,
    ) {
    }

    /**
     * @return array<int, Gs1Row>
     */
    public function rowsByStatus(string $status): array
    {
        return array_filter(
            $this->rows,
            fn (Gs1Row $row) => $row->status === $status,
        );
    }
}
