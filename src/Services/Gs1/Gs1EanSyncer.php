<?php

namespace Dashed\DashedEcommerceCore\Services\Gs1;

use Dashed\DashedEcommerceCore\Models\Product;

/**
 * Leest een GS1 Excel en synchroniseert EAN-codes terug naar Product-
 * records. Match gebeurt op productnaam in elke beschikbare locale.
 *
 * Regels per rij:
 *  - Geen geldige GTIN in de rij → overgeslagen (niet geteld).
 *  - Geen match op productnaam → `notFound`.
 *  - Product heeft al exact deze EAN → `alreadyInSync`.
 *  - Product heeft al een andere EAN → `skippedHasEan` (nooit
 *    overschreven; alleen lege EANs worden gevuld).
 *  - GTIN is al in gebruik door een ander product → `conflicts`.
 *  - Anders → EAN toegekend en `updated`.
 */
class Gs1EanSyncer
{
    public function __construct(
        private readonly Gs1FileReader $reader,
        private readonly Gs1NameMatcher $matcher = new Gs1NameMatcher(),
    ) {
    }

    public function sync(string $absolutePath): Gs1EanSyncResult
    {
        $contents = $this->reader->read($absolutePath);
        $result = new Gs1EanSyncResult();

        $index = $this->matcher->index(Product::query());

        foreach ($contents->rows as $rowNumber => $row) {
            if (! $row->hasRealGtin() || ! $row->description) {
                continue;
            }

            $key = Gs1NameMatcher::normalize($row->description);
            $productId = $index[$key] ?? null;

            if (! $productId) {
                $result->notFound[] = [
                    'row' => $rowNumber,
                    'description' => $row->description,
                    'gtin' => $row->gtin,
                ];

                continue;
            }

            $product = Product::find($productId);
            if (! $product) {
                continue;
            }

            if ($product->ean === $row->gtin) {
                $result->alreadyInSync++;

                continue;
            }

            if (! empty($product->ean)) {
                $result->skippedHasEan[] = [
                    'row' => $rowNumber,
                    'product_id' => $productId,
                    'gtin' => $row->gtin,
                    'existing' => $product->ean,
                ];

                continue;
            }

            $conflict = Product::where('ean', $row->gtin)
                ->where('id', '!=', $productId)
                ->first();
            if ($conflict) {
                $result->conflicts[] = [
                    'row' => $rowNumber,
                    'product_id' => $productId,
                    'gtin' => $row->gtin,
                    'conflict_id' => $conflict->id,
                ];

                continue;
            }

            $product->ean = $row->gtin;
            $product->saveQuietly();

            $result->updated[] = [
                'row' => $rowNumber,
                'product_id' => $productId,
                'gtin' => $row->gtin,
            ];
        }

        return $result;
    }
}
