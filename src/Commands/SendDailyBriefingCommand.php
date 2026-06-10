<?php

declare(strict_types=1);

namespace Dashed\DashedEcommerceCore\Commands;

use Throwable;
use Illuminate\Console\Command;
use Dashed\DashedCore\Classes\Sites;
use Dashed\DashedEcommerceCore\Models\Order;
use Dashed\DashedEcommerceCore\Models\Product;

class SendDailyBriefingCommand extends Command
{
    protected $signature = 'dashed-ecommerce-core:send-daily-briefing';

    protected $description = 'Stuurt \'s ochtends een dagstart-push per site: omzet/bestellingen van gisteren + wat er vandaag te doen is (nog te verzenden, lage voorraad). Alleen naar app-gebruikers die "Dagstart" aan hebben staan.';

    public function handle(): int
    {
        $center = '\Dashed\DashedMobileApi\Support\NotificationCenter';
        if (! class_exists($center)) {
            $this->warn('NotificationCenter niet beschikbaar; dagstart overgeslagen.');

            return self::SUCCESS;
        }

        $start = now()->subDay()->startOfDay();
        $end = now()->subDay()->endOfDay();
        $pushed = 0;

        foreach (Sites::getSites() as $site) {
            $siteId = $site['id'] ?? null;
            if (! $siteId) {
                continue;
            }

            // Gisteren: betaalde omzet + aantal bestellingen.
            $paid = Order::query()
                ->where('site_id', $siteId)
                ->isPaid()
                ->whereBetween('created_at', [$start, $end])
                ->get(['id', 'total']);
            $revenue = round((float) $paid->sum('total'), 2);
            $orders = $paid->count();

            // Vandaag te doen: openstaande verzend-achterstand (betaald + onafgehandeld).
            $unhandled = Order::query()
                ->where('site_id', $siteId)
                ->where('fulfillment_status', 'unhandled')
                ->isPaid()
                ->count();

            // Producten op of onder de voorraaddrempel (of <= 0), zelfde definitie
            // als de voorraad-cockpit in de app.
            $lowStock = Product::thisSite($siteId)
                ->where('use_stock', true)
                ->where(function ($q): void {
                    $q->whereColumn('stock', '<=', 'low_stock_notification_limit')
                        ->orWhere('stock', '<=', 0);
                })
                ->count();

            // Niets te melden (stille dag, geen achterstand) → geen push.
            if ($revenue <= 0 && $orders === 0 && $unhandled === 0 && $lowStock === 0) {
                continue;
            }

            $revenueLabel = '€ ' . number_format($revenue, 2, ',', '.');
            $body = "Gisteren: {$revenueLabel} · {$orders} " . ($orders === 1 ? 'bestelling' : 'bestellingen') . '.';

            $todo = [];
            if ($unhandled > 0) {
                $todo[] = "{$unhandled} te verzenden";
            }
            if ($lowStock > 0) {
                $todo[] = "{$lowStock} lage voorraad";
            }
            if ($todo) {
                $body .= ' Vandaag: ' . implode(' · ', $todo) . '.';
            }

            try {
                app($center)->push()
                    ->type('daily.briefing')
                    ->site((string) $siteId)
                    ->title('Goedemorgen 👋')
                    ->body($body)
                    ->route('/dashboard')
                    ->data([
                        'type' => 'briefing',
                        'revenue' => $revenue,
                        'orders' => $orders,
                        'unhandled' => $unhandled,
                        'low_stock' => $lowStock,
                    ])
                    ->send();
                $pushed++;
            } catch (Throwable $e) {
                report($e);
            }
        }

        $this->info("Dagstart-pushes verstuurd: {$pushed}.");

        return self::SUCCESS;
    }
}
