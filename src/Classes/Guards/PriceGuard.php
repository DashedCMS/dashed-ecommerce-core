<?php

namespace Dashed\DashedEcommerceCore\Classes\Guards;

use Dashed\DashedCore\Classes\Sites;
use Dashed\DashedCore\Models\Customsetting;
use Dashed\DashedEcommerceCore\Models\Product;

/**
 * Vangrail vóór het opslaan van een prijs: `€2,99` in plaats van `€29,99` is
 * één typfout, en Bol en Channable pakken de feed meteen op. Een prijs die
 * meer dan de drempel (standaard 50%) omhoog of omlaag gaat, onder de
 * inkoopprijs zakt of er precies op uitkomt, vraagt om een bevestiging met oud
 * en nieuw naast elkaar. `EcommerceActionMonitor` meldt een prijswijziging
 * achteraf; dit houdt hem vooraf tegen.
 */
class PriceGuard
{
    public const DEFAULT_THRESHOLD = 50;

    /** Zoveel afwijkingen toont de bevestiging van een import. */
    public const PREVIEW_LIMIT = 10;

    public static function threshold(?string $siteId = null): int
    {
        return max(0, (int) Customsetting::get('price_guard_threshold', $siteId ?? Sites::getActive(), self::DEFAULT_THRESHOLD));
    }

    /**
     * Waarom deze prijs een bevestiging vraagt; leeg als hij gewoon door mag.
     *
     * @return array<int, string>
     */
    public static function reasons(mixed $oldPrice, mixed $newPrice, mixed $purchasePrice, ?string $siteId = null): array
    {
        $threshold = self::threshold($siteId);

        if ($threshold === 0 || ! is_numeric($newPrice)) {
            return [];
        }

        $new = round((float) $newPrice, 2);
        $old = is_numeric($oldPrice) ? round((float) $oldPrice, 2) : null;
        $purchase = is_numeric($purchasePrice) ? round((float) $purchasePrice, 2) : null;

        $reasons = [];

        if ($old !== null && $old > 0 && $new !== $old) {
            $change = ($new - $old) / $old * 100;

            if (abs($change) >= $threshold) {
                $reasons[] = $change < 0
                    ? __('De prijs gaat :procent% omlaag.', ['procent' => (int) round(abs($change))])
                    : __('De prijs gaat :procent% omhoog.', ['procent' => (int) round($change)]);
            }
        }

        if ($purchase !== null && $purchase > 0 && ($old === null || $new !== $old)) {
            if ($new < $purchase) {
                $reasons[] = __('De prijs ligt onder de inkoopprijs van :inkoop.', ['inkoop' => self::format($purchase)]);
            } elseif ($new === $purchase) {
                $reasons[] = __('De prijs is gelijk aan de inkoopprijs: er blijft geen marge over.');
            }
        }

        return $reasons;
    }

    /**
     * De afwijkingen in een bestand voor "Producten bewerken". Leest alleen de
     * product-id en de prijs, en vergelijkt met de database; de grootste
     * afwijkingen eerst.
     *
     * @param  array<int, array<int, mixed>>  $rows  inclusief kopregel
     * @return array{count: int, rows: array<int, array{id: int, name: string, old: ?float, new: float, reasons: array<int, string>}>}
     */
    public static function analyzeImport(array $rows): array
    {
        $header = array_shift($rows) ?? [];
        $idColumn = array_search('Product ID (niet wijzigen)', $header, true);
        $priceLabel = ecommerce()->builder('productPriceFields')['price']['label'] ?? 'price';
        $priceColumn = array_search($priceLabel, $header, true);

        if ($idColumn === false || $priceColumn === false) {
            return ['count' => 0, 'rows' => []];
        }

        $wanted = [];
        foreach ($rows as $row) {
            $id = $row[$idColumn] ?? null;
            $price = $row[$priceColumn] ?? null;

            // Een lege cel laat de prijs ongemoeid, net als in de import zelf.
            if (is_numeric($id) && $price !== null && $price !== '' && is_numeric($price)) {
                $wanted[(int) $id] = (float) $price;
            }
        }

        $found = [];
        foreach (array_chunk(array_keys($wanted), 500) as $ids) {
            foreach (Product::query()->whereIn('id', $ids)->get(['id', 'name', 'price', 'purchase_price']) as $product) {
                $reasons = self::reasons($product->getRawOriginal('price'), $wanted[$product->id], $product->purchase_price);

                if ($reasons) {
                    $old = is_numeric($product->getRawOriginal('price')) ? (float) $product->getRawOriginal('price') : null;
                    $found[] = [
                        'id' => $product->id,
                        'name' => (string) $product->name,
                        'old' => $old,
                        'new' => $wanted[$product->id],
                        'reasons' => $reasons,
                        'weight' => $old ? abs($wanted[$product->id] - $old) / $old : 1.0,
                    ];
                }
            }
        }

        usort($found, fn ($a, $b) => $b['weight'] <=> $a['weight']);

        return [
            'count' => count($found),
            'rows' => array_map(fn ($row) => array_diff_key($row, ['weight' => true]), $found),
        ];
    }

    public static function format(?float $price): string
    {
        return $price === null ? '-' : '€'.number_format($price, 2, ',', '.');
    }
}
