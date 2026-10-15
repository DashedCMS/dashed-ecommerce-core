<?php

namespace Dashed\DashedEcommerceCore\Classes;

use Dashed\DashedEcommerceCore\Models\ProductExtraOption;

/**
 * De kassa bewaart de extra's van een regel als map [extraId => optieId];
 * een orderregel bewaart ze als lijst van optierijen
 * ([['id' => optieId, 'name', 'value', 'price'], ...], zie Checkout).
 * Overal waar de kassa die map leest komt hij hierlangs, zodat een regel
 * die per ongeluk de lijstvorm draagt (een bestelling die naar de kassa is
 * gekopieerd) niet de hele kassa onderuit haalt.
 */
class PosCartExtras
{
    /**
     * @return array<int, int> [productExtraId => productExtraOptionId]
     */
    public static function optionMap(array $extra): array
    {
        $map = [];
        $looseOptionIds = [];

        foreach ($extra as $productExtraId => $value) {
            if (is_array($value)) {
                // Lijstvorm van een orderregel. Een invulveld of upload heeft
                // een id als 'product-extra-12' en geen optie; dat kan de
                // kassa niet aan en valt weg.
                if (is_numeric($value['id'] ?? null)) {
                    $looseOptionIds[] = (int) $value['id'];
                }

                continue;
            }

            if (is_scalar($value) && is_numeric($value) && (int) $value > 0) {
                $map[(int) $productExtraId] = (int) $value;
            }
        }

        if ($looseOptionIds) {
            $extraIds = ProductExtraOption::withTrashed()
                ->whereIn('id', $looseOptionIds)
                ->pluck('product_extra_id', 'id');

            foreach ($looseOptionIds as $optionId) {
                if ($extraIds->has($optionId)) {
                    $map[(int) $extraIds[$optionId]] = $optionId;
                }
            }
        }

        return $map;
    }
}
