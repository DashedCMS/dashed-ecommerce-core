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

    /**
     * Alle gekozen opties per extra, ook bij een extra met meerdere opties.
     * De kassa bewaart één optie als [extraId => optieId] en meerdere als
     * [extraId => [optieId, ...]]; de lijstvorm van een orderregel komt hier
     * net als in optionMap() ook doorheen.
     *
     * @return array<int, array<int, int>> [productExtraId => [optieId, ...]]
     */
    public static function selections(array $extra): array
    {
        $selections = [];
        $looseOptionIds = [];

        foreach ($extra as $productExtraId => $value) {
            if (is_array($value) && array_key_exists('id', $value)) {
                // Lijstvorm van een orderregel; een invulveld of upload heeft
                // een id als 'product-extra-12' en valt hier weg.
                if (is_numeric($value['id'])) {
                    $looseOptionIds[] = (int) $value['id'];
                }

                continue;
            }

            if (! is_numeric($productExtraId)) {
                continue;
            }

            foreach (is_array($value) ? $value : [$value] as $optionId) {
                if (is_scalar($optionId) && is_numeric($optionId) && (int) $optionId > 0) {
                    $selections[(int) $productExtraId][] = (int) $optionId;
                }
            }
        }

        if ($looseOptionIds) {
            $extraIds = ProductExtraOption::withTrashed()
                ->whereIn('id', $looseOptionIds)
                ->pluck('product_extra_id', 'id');

            foreach ($looseOptionIds as $optionId) {
                if ($extraIds->has($optionId)) {
                    $selections[(int) $extraIds[$optionId]][] = $optionId;
                }
            }
        }

        return array_map(fn (array $ids) => array_values(array_unique($ids)), $selections);
    }

    /**
     * Opslagvorm op een kassaregel: één optie als getal, meerdere als lijst.
     *
     * @return array<int, int|array<int, int>>
     */
    public static function storageMap(array $extra): array
    {
        return array_map(
            fn (array $ids) => count($ids) === 1 ? $ids[0] : $ids,
            self::selections($extra),
        );
    }

    /**
     * Ingevulde teksten van een kassaregel ([extraId => tekst]). Ze staan in
     * 'extra_inputs'; een regel uit een oudere "wagen naar kassa" draagt ze
     * nog onder options.options['product-extra-input-{extraId}'].
     *
     * @return array<int, string>
     */
    public static function textInputs(array $posItem): array
    {
        $inputs = [];

        foreach ((array) data_get($posItem, 'options.options', []) as $key => $option) {
            if (is_array($option) && preg_match('/^product-extra-input-(\d+)$/', (string) $key, $m)) {
                $value = is_scalar($option['value'] ?? null) ? trim((string) $option['value']) : '';
                if ($value !== '') {
                    $inputs[(int) $m[1]] = $value;
                }
            }
        }

        foreach ((array) ($posItem['extra_inputs'] ?? []) as $extraId => $value) {
            if (! is_numeric($extraId)) {
                continue;
            }

            $value = is_scalar($value) ? trim((string) $value) : '';
            if ($value !== '') {
                $inputs[(int) $extraId] = $value;
            } else {
                unset($inputs[(int) $extraId]);
            }
        }

        return $inputs;
    }

    /**
     * Teksten uit de product_extras van een orderregel
     * (id 'product-extra-input-{extraId}', zie Checkout).
     *
     * @return array<int, string>
     */
    public static function textInputsFromOrderExtras(array $productExtras): array
    {
        $inputs = [];

        foreach ($productExtras as $row) {
            if (! is_array($row) || ! preg_match('/^product-extra-input-(\d+)$/', (string) ($row['id'] ?? ''), $m)) {
                continue;
            }

            $value = is_scalar($row['value'] ?? null) ? trim((string) $row['value']) : '';
            if ($value !== '') {
                $inputs[(int) $m[1]] = $value;
            }
        }

        return $inputs;
    }
}
