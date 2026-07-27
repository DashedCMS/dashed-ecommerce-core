<?php

declare(strict_types=1);

namespace Dashed\DashedEcommerceCore\Support\Automation;

use Illuminate\Support\Collection;
use Dashed\DashedEcommerceCore\Models\Product;
use Dashed\DashedEcommerceCore\Models\AutomationRule;
use Dashed\DashedEcommerceCore\Models\AutomationRuleRun;

/**
 * Kandidaat-selectie voor voorraad-automatiseringsregels (`stock.low` /
 * `stock.back`), site-bewust (`Product.site_ids` via `thisSite()`) en
 * gespiegeld op het dedup-patroon van TimeRuleScanner: "al gedraaid" is een
 * geslaagde AutomationRuleRun voor (regel, product) — een mislukte of
 * lopende run blokkeert dus niet.
 *
 * DEDUP/RESET — bewuste keuze: producten hebben geen voorraad-historie, dus
 * "stond dit product ooit op 0" (nodig om te weten wanneer stock.low weer
 * mag vuren, en wanneer stock.back kandidaat wordt) is niet betrouwbaar uit
 * enkel de run-historie + de huidige voorraad af te leiden. Daarom houdt
 * Product (zie `Product::booted()`'s `saved`-hook) twee marker-kolommen bij,
 * puur op basis van de wasChanged('stock')-transitie:
 * - `automation_stock_zero_at`: wanneer dit product voor het laatst een
 *   nieuwe 0-episode inging (van >0 naar 0).
 * - `automation_stock_recovered_at`: wanneer diezelfde episode voor het
 *   laatst herstelde (van 0 naar >0).
 *
 * Met die marker wordt dedup/reset een zuivere lees-query, precies zoals
 * TimeRuleScanner: deze klasse zelf schrijft niets weg.
 *
 * - `stock.low` mag weer vuren zodra er een geslaagde run bestaat vóór de
 *   laatste 0-episode (of nog geen 0-episode is geweest en er ook nog nooit
 *   een geslaagde run was) — d.w.z. geen geslaagde run ná
 *   `automation_stock_zero_at` (of, als dat nog nooit gezet is, blokkeert
 *   élke eerdere geslaagde run, want er is dan nog geen reset-moment
 *   geweest).
 * - `stock.back` is alleen kandidaat als het product ooit een 0-episode
 *   inging (`automation_stock_zero_at` niet null) én nu weer stock > 0
 *   heeft, zonder geslaagde `stock.back`-run sinds die 0-episode.
 */
class StockRuleScanner
{
    private const TRIGGER_LOW = 'stock.low';

    private const TRIGGER_BACK = 'stock.back';

    /**
     * Producten van de site van $rule met `use_stock` en een ingestelde
     * `low_stock_notification_limit` waarbij `stock` op of onder die drempel
     * zit, zonder geslaagde `stock.low`-run voor (regel, product) sinds de
     * laatste 0-episode van dat product.
     */
    public static function lowCandidates(AutomationRule $rule): Collection
    {
        $productsTable = (new Product())->getTable();
        $runsTable = (new AutomationRuleRun())->getTable();

        return Product::query()
            ->thisSite((string) $rule->site_id)
            ->where('use_stock', true)
            ->whereNotNull('low_stock_notification_limit')
            ->whereColumn('stock', '<=', 'low_stock_notification_limit')
            ->whereNotExists(function ($subQuery) use ($runsTable, $productsTable, $rule) {
                $subQuery->from($runsTable)
                    ->whereColumn("$runsTable.subject_id", "$productsTable.id")
                    ->where("$runsTable.subject_type", Product::class)
                    ->where("$runsTable.rule_id", $rule->id)
                    ->where("$runsTable.trigger", self::TRIGGER_LOW)
                    ->where("$runsTable.status", AutomationRuleRun::STATUS_SUCCESS)
                    ->whereRaw(
                        "$runsTable.created_at >= COALESCE($productsTable.automation_stock_zero_at, '1970-01-01 00:00:00')"
                    );
            })
            ->get();
    }

    /**
     * Producten van de site van $rule die van 0 naar >0 gingen: `stock > 0`
     * en een aantoonbare eerdere 0-episode (`automation_stock_zero_at` niet
     * null), zonder geslaagde `stock.back`-run voor (regel, product) sinds
     * die 0-episode.
     */
    public static function backCandidates(AutomationRule $rule): Collection
    {
        $productsTable = (new Product())->getTable();
        $runsTable = (new AutomationRuleRun())->getTable();

        return Product::query()
            ->thisSite((string) $rule->site_id)
            ->where('stock', '>', 0)
            ->whereNotNull('automation_stock_zero_at')
            ->whereNotExists(function ($subQuery) use ($runsTable, $productsTable, $rule) {
                $subQuery->from($runsTable)
                    ->whereColumn("$runsTable.subject_id", "$productsTable.id")
                    ->where("$runsTable.subject_type", Product::class)
                    ->where("$runsTable.rule_id", $rule->id)
                    ->where("$runsTable.trigger", self::TRIGGER_BACK)
                    ->where("$runsTable.status", AutomationRuleRun::STATUS_SUCCESS)
                    ->whereColumn("$runsTable.created_at", '>=', "$productsTable.automation_stock_zero_at");
            })
            ->get();
    }
}
