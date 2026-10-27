<?php

declare(strict_types=1);

namespace Dashed\DashedEcommerceCore\Commands;

use Throwable;
use Illuminate\Console\Command;
use Dashed\DashedEcommerceCore\Models\Order;
use Dashed\DashedEcommerceCore\Services\Giftcards\GiftcardIssuer;

/**
 * Vangnet voor de uitgifte van gekochte cadeaubonnen: de observer geeft ze
 * uit bij de overgang naar betaald, maar slaat een fout alleen op. Deze
 * ronde loopt de betaalde orders van de laatste dagen langs en vult aan wat
 * ontbreekt. GiftcardIssuer is idempotent, dus vaak draaien kan geen kwaad.
 */
class IssueMissingGiftcardsCommand extends Command
{
    protected $signature = 'dashed:issue-missing-giftcards {--days=30 : Hoeveel dagen terug kijken}';

    protected $description = 'Geeft ontbrekende gekochte cadeaubonnen uit voor recent betaalde orders';

    public function handle(GiftcardIssuer $issuer): int
    {
        $since = now()->subDays(max(1, (int) $this->option('days')));
        $issued = 0;
        $failed = 0;

        Order::query()
            ->where('status', 'paid')
            ->whereNull('credit_for_order_id')
            ->where('updated_at', '>=', $since)
            ->whereHas('orderProducts.product', fn ($query) => $query
                ->where('is_giftcard_product', 1)
                ->orWhereHas('productGroup', fn ($group) => $group->where('is_giftcard_product', 1)))
            ->orderBy('id')
            ->chunkById(100, function ($orders) use ($issuer, &$issued, &$failed) {
                foreach ($orders as $order) {
                    try {
                        $issued += $issuer->issueFor($order)->count();
                    } catch (Throwable $e) {
                        $failed++;
                        report($e);
                        $this->error('Order ' . $order->id . ': ' . $e->getMessage());
                    }
                }
            });

        $this->info($issued . ' cadeaubonnen uitgegeven');

        return $failed > 0 ? self::FAILURE : self::SUCCESS;
    }
}
