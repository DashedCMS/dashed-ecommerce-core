<?php

namespace Dashed\DashedEcommerceCore\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Support\Facades\Log;
use Illuminate\Queue\SerializesModels;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Dashed\DashedEcommerceCore\Models\Product;

/**
 * Bulk-bewerking voor product prijzen. Verhoogt (of verlaagt, bij negatief
 * bedrag) de prijs van alle producten met een vast euro-bedrag of met een
 * percentage. Optioneel wordt ook new_price (aanbiedingsprijs) meegenomen.
 *
 * Draait via de queue zodat de admin-pagina niet hangt bij grote catalogi.
 */
class BulkUpdateProductPrices implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public int $tries = 1;

    public int $timeout = 1800;

    public function __construct(
        public readonly string $mode,
        public readonly float $amount,
        public readonly bool $includeDiscountPrice = false,
    ) {
    }

    public function handle(): void
    {
        if (! in_array($this->mode, ['euro', 'percent'], true)) {
            Log::warning('BulkUpdateProductPrices: ongeldige mode', ['mode' => $this->mode]);

            return;
        }

        $updated = 0;
        $skipped = 0;

        Product::query()
            ->select(['id', 'price', 'new_price'])
            ->orderBy('id')
            ->chunkById(500, function ($products) use (&$updated, &$skipped) {
                foreach ($products as $product) {
                    $touched = false;

                    if ($this->bumpField($product, 'price')) {
                        $touched = true;
                    }
                    if ($this->includeDiscountPrice && $this->bumpField($product, 'new_price')) {
                        $touched = true;
                    }

                    if ($touched) {
                        // saveQuietly: voorkomt model-events (search index,
                        // activity-log) per record - bij bulk willen we
                        // alleen de waarde wijzigen, niet N events triggeren.
                        $product->saveQuietly();
                        $updated++;
                    } else {
                        $skipped++;
                    }
                }
            });

        Log::info('BulkUpdateProductPrices: klaar', [
            'mode' => $this->mode,
            'amount' => $this->amount,
            'include_discount_price' => $this->includeDiscountPrice,
            'updated' => $updated,
            'skipped' => $skipped,
        ]);
    }

    private function bumpField(Product $product, string $field): bool
    {
        $current = $product->getRawOriginal($field);
        if ($current === null || $current === '') {
            return false;
        }

        $currentFloat = (float) $current;
        if ($currentFloat <= 0) {
            return false;
        }

        $new = $this->mode === 'percent'
            ? $currentFloat * (1 + ($this->amount / 100))
            : $currentFloat + $this->amount;

        // Onder de 0 voorkomen we want negatieve prijzen breken alles
        // downstream (cart, checkout, BTW). Houd op 0,01 als minimum.
        if ($new < 0.01) {
            $new = 0.01;
        }

        $rounded = round($new, 2);
        if ($rounded === round($currentFloat, 2)) {
            return false;
        }

        $product->{$field} = $rounded;

        return true;
    }
}
