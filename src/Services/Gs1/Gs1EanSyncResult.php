<?php

namespace Dashed\DashedEcommerceCore\Services\Gs1;

/**
 * Bevat per categorie de rijen uit een GS1-sync. `updated` zijn
 * producten waarvan de EAN is bijgewerkt. `alreadyInSync` zijn
 * rijen waar de EAN in CMS al gelijk was aan de GS1-waarde.
 * `skippedHasEan` zijn rijen waar het product al een andere EAN
 * had. `notFound` zijn rijen zonder match op productnaam.
 * `conflicts` zijn rijen waar de GTIN al door een ander product in
 * gebruik is.
 */
class Gs1EanSyncResult
{
    /** @var array<int, array{row: int, product_id: int, gtin: string}> */
    public array $updated = [];

    /** @var array<int, array{row: int, product_id: int, gtin: string, existing: string}> */
    public array $skippedHasEan = [];

    /** @var array<int, array{row: int, description: ?string, gtin: string}> */
    public array $notFound = [];

    /** @var array<int, array{row: int, product_id: int, gtin: string, conflict_id: int}> */
    public array $conflicts = [];

    public int $alreadyInSync = 0;

    public function totalRowsConsidered(): int
    {
        return count($this->updated) + count($this->skippedHasEan) + count($this->notFound) + count($this->conflicts) + $this->alreadyInSync;
    }
}
