<?php

declare(strict_types=1);

namespace Dashed\DashedEcommerceCore\Commands;

use Throwable;
use Illuminate\Console\Command;
use Dashed\DashedCore\Classes\Sites;
use Dashed\DashedEcommerceCore\Models\Product;

class CheckLowStock extends Command
{
    protected $signature = 'dashed:check-low-stock';

    protected $description = 'Controleert per site welke producten op of onder hun voorraaddrempel zitten en stuurt daarover een push (max 1x/24u per product). Herstelt het alert-vlaggetje zodra de voorraad weer boven de drempel komt.';

    public function handle(): int
    {
        $center = '\Dashed\DashedMobileApi\Support\NotificationCenter';
        $hasCenter = class_exists($center);

        $alertedTotal = 0;
        $recoveredTotal = 0;

        foreach (Sites::getSites() as $site) {
            $siteId = $site['id'] ?? null;
            if (! $siteId) {
                continue;
            }

            // Recovery: producten met een actief alert-vlaggetje die weer boven
            // de drempel zitten → vlaggetje wissen zodat ze later opnieuw kunnen
            // alerten. Doet dit ongeacht low_stock_notification, zodat een
            // uitgezette melding het oude vlaggetje niet voor altijd blijft houden.
            $recoveredTotal += Product::thisSite($siteId)
                ->whereNotNull('low_stock_alerted_at')
                ->where(function ($q): void {
                    $q->whereNull('low_stock_notification_limit')
                        ->orWhere('use_stock', false)
                        ->orWhere('low_stock_notification', false)
                        ->orWhereColumn('stock', '>', 'low_stock_notification_limit');
                })
                ->update(['low_stock_alerted_at' => null]);

            // Producten die op of onder de drempel zitten en nog niet (recent)
            // gealarmeerd zijn.
            $threshold = now()->subDay();
            $products = Product::thisSite($siteId)
                ->where('use_stock', true)
                ->where('low_stock_notification', true)
                ->whereNotNull('low_stock_notification_limit')
                ->whereColumn('stock', '<=', 'low_stock_notification_limit')
                ->where(function ($q) use ($threshold): void {
                    $q->whereNull('low_stock_alerted_at')
                        ->orWhere('low_stock_alerted_at', '<', $threshold);
                })
                ->get();

            if ($products->isEmpty()) {
                continue;
            }

            $this->sendAlert($center, $hasCenter, (string) $siteId, $products);

            Product::whereIn('id', $products->pluck('id'))
                ->update(['low_stock_alerted_at' => now()]);

            $alertedTotal += $products->count();
        }

        $this->info("Lage voorraad: {$alertedTotal} gealarmeerd, {$recoveredTotal} hersteld.");

        return self::SUCCESS;
    }

    /**
     * @param \Illuminate\Database\Eloquent\Collection<int, Product> $products
     */
    private function sendAlert(string $center, bool $hasCenter, string $siteId, $products): void
    {
        if (! $hasCenter) {
            return;
        }

        $count = $products->count();
        if ($count === 1) {
            $product = $products->first();
            $title = 'Lage voorraad';
            $body = "{$product->name} heeft nog {$product->stock} op voorraad.";
        } else {
            $title = 'Lage voorraad';
            $names = $products->take(3)->map(fn ($p) => $p->name)->implode(', ');
            $body = "{$count} producten bijna op ({$names}" . ($count > 3 ? ', …' : '') . ').';
        }

        try {
            app($center)->push()
                ->type('stock.low')
                ->site($siteId)
                ->title($title)
                ->body($body)
                ->route('/voorraad?lowStock=1')
                ->data([
                    'type' => 'stock.low',
                    'count' => $count,
                    'product_ids' => $products->pluck('id')->values()->all(),
                ])
                ->toAbility('products.write')
                ->send();
        } catch (Throwable $e) {
            report($e);
        }
    }
}
