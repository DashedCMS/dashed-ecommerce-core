<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Artisan;
use Dashed\DashedEcommerceCore\Models\Product;
use Dashed\DashedEcommerceCore\Models\ProductGroup;
use Dashed\DashedEcommerceCore\Models\AutomationRule;
use Dashed\DashedEcommerceCore\Models\AutomationRuleRun;

/**
 * Task 6 (B3): RunTimeBasedAutomationRules::scanStockRules() bindt de
 * voorraad-bouwstenen samen — StockRuleScanner (Task 5: kandidaten +
 * dedup/reset, gedekt door StockRuleScannerTest), ConditionEvaluator
 * (voorwaarden op scan-moment, tegen AutomationContext::forProduct()) en
 * AutomationEngine (uitvoering + claim-before-execute). Er is geen
 * Product::factory() (zie StockRuleScannerTest), dus producten worden met
 * Product::withoutEvents() + Product::create() aangemaakt; Queue::fake()
 * voorkomt dat de (hier niet relevante) saved-listener alsnog
 * UpdateProductInformationJob synchroon laat draaien, wat op sqlite stukloopt
 * op een MySQL-only GREATEST()-call.
 *
 * Helper-namen zijn bewust uniek t.o.v. StockRuleScannerTest (die eigen
 * globale stockGroup()/stockProduct()/stockRule()-functies definieert) om
 * een "cannot redeclare function"-botsing te vermijden mocht de volledige
 * testsuite ooit beide bestanden in één proces laden.
 */
beforeEach(function () {
    Queue::fake();
});

function stockAutomationRunGroup(string $siteId): ProductGroup
{
    return ProductGroup::create([
        'name' => ['en' => 'Group ' . uniqid()],
        'slug' => ['en' => 'group-' . uniqid()],
        'short_description' => ['en' => ''],
        'description' => ['en' => ''],
        'content' => ['en' => ''],
        'search_terms' => ['en' => ''],
        'site_ids' => [$siteId],
    ]);
}

function stockAutomationRunProduct(string $siteId, array $overrides = []): Product
{
    $group = stockAutomationRunGroup($siteId);

    return Product::withoutEvents(fn () => Product::create(array_merge([
        'name' => ['en' => 'Vaas ' . uniqid()],
        'slug' => ['en' => 'vaas-' . uniqid()],
        'site_ids' => [$siteId],
        'product_group_id' => $group->id,
        'use_stock' => true,
        'low_stock_notification_limit' => 5,
        'stock' => 2,
        'total_stock' => 2,
        'in_stock' => true,
        'stock_status' => 'in_stock',
        'sku' => 'sku-' . uniqid(),
        'price' => 10.00,
        'current_price' => 10.00,
    ], $overrides)));
}

function stockAutomationRunRule(string $trigger, array $conditions = [], array $attributes = []): AutomationRule
{
    return AutomationRule::create(array_merge([
        'site_id' => 'main',
        'name' => 'Voorraad-regel',
        'trigger' => $trigger,
        'conditions' => $conditions,
        'actions' => [['key' => 'notify', 'params' => ['title' => 'Lage voorraad']]],
        'is_active' => true,
    ], $attributes));
}

it('draait een stock.low-regel één keer voor een product onder de limiet via de scan', function () {
    $rule = stockAutomationRunRule('stock.low'); // lege conditie = altijd raak
    $product = stockAutomationRunProduct('main', ['stock' => 2, 'low_stock_notification_limit' => 5]);

    Artisan::call('dashed:run-time-automations');

    $runs = AutomationRuleRun::where('rule_id', $rule->id)->where('subject_id', $product->id)->get();
    expect($runs)->toHaveCount(1)
        ->and($runs->first()->status)->toBe(AutomationRuleRun::STATUS_SUCCESS)
        ->and($runs->first()->subject_type)->toBe(Product::class);

    // Tweede scan: geen tweede run. Dit bewijst end-to-end "één run per
    // product" via het commando — niet specifiek de scanner-dedup: hier
    // blokkeert AutomationEngine's eigen 5-minuten-rerun-window (Automation-
    // Engine::RERUN_WINDOW_MINUTES) de tweede run al, ongeacht of
    // StockRuleScanner::lowCandidates() het product ook had uitgesloten. De
    // scanner-dedup zelf (whereNotExists op een geslaagde run sinds de
    // laatste 0-episode) wordt geïsoleerd bewezen in StockRuleScannerTest.
    Artisan::call('dashed:run-time-automations');
    expect(AutomationRuleRun::where('rule_id', $rule->id)->where('subject_id', $product->id)->count())->toBe(1);
});

it('draait niet als de voorwaarde op scan-moment niet matcht', function () {
    $rule = stockAutomationRunRule('stock.low', [
        ['field' => 'sku', 'operator' => 'eq', 'value' => 'een-andere-sku'],
    ]);
    $product = stockAutomationRunProduct('main', ['stock' => 2, 'low_stock_notification_limit' => 5, 'sku' => 'niet-die-sku']);

    Artisan::call('dashed:run-time-automations');

    expect(AutomationRuleRun::where('rule_id', $rule->id)->where('subject_id', $product->id)->count())->toBe(0);
});

it('draait niet voor een product dat niet onder de limiet zit', function () {
    $rule = stockAutomationRunRule('stock.low');
    $product = stockAutomationRunProduct('main', ['stock' => 20, 'low_stock_notification_limit' => 5]);

    Artisan::call('dashed:run-time-automations');

    expect(AutomationRuleRun::where('rule_id', $rule->id)->where('subject_id', $product->id)->count())->toBe(0);
});
