<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Queue;
use Dashed\DashedEcommerceCore\Models\Product;
use Dashed\DashedEcommerceCore\Models\ProductGroup;
use Dashed\DashedEcommerceCore\Models\AutomationRule;
use Dashed\DashedEcommerceCore\Models\AutomationRuleRun;
use Dashed\DashedEcommerceCore\Support\Automation\StockRuleScanner;

/**
 * Task 5 (B3): StockRuleScanner::lowCandidates()/backCandidates() selecteren
 * producten voor de voorraad-triggers stock.low/stock.back, site-bewust en
 * met dedup/reset gespiegeld op TimeRuleScanner's whereNotExists-patroon.
 *
 * Dedup/reset leunt op één marker-kolom die Product zelf onderhoudt
 * (`Product::booted()`'s created- en saved-hooks): `automation_stock_zero_at`
 * — gezet bij een echte wasChanged('stock')-transitie naar 0, én bij aanmaak
 * van een product dat al met stock 0 start (bv. pre-order). Zie
 * StockRuleScanner voor de volledige uitleg waarom dit niet puur uit de
 * run-historie + huidige voorraad af te leiden was.
 *
 * Er is geen Product::factory() (zie LowStockCommandTest) — producten
 * worden aangemaakt met Product::create() binnen withoutEvents() (voorkomt
 * de MySQL-specifieke GREATEST()-call in de saved-listener op sqlite);
 * stock-transities daarna lopen bewust WEL door de echte saved-events, want
 * de marker-hook zit daarin. Queue::fake() voorkomt dat diezelfde
 * saved-listener ook UpdateProductInformationJob synchroon (QUEUE_CONNECTION
 * sync) laat draaien — die job roept ongeacht testdata een MySQL-only
 * GREATEST() aan en breekt op sqlite (zelfde, hier niet te verhelpen
 * pre-existing probleem als in BackInStockServiceTest).
 */
beforeEach(function () {
    Queue::fake();
});

function stockGroup(string $siteId): ProductGroup
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

function stockProduct(string $siteId, array $overrides = []): Product
{
    $group = stockGroup($siteId);

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
        'price' => 10.00,
        'current_price' => 10.00,
    ], $overrides)));
}

/**
 * Voor de "aangemaakt met stock 0"-regressietest: hier moeten de échte
 * created/saved-events lopen (niet withoutEvents()), want de
 * automation_stock_zero_at-marker voor een INSERT zit in de created-hook.
 * Queue::fake() (beforeEach) voorkomt dat UpdateProductInformationJob
 * synchroon draait en op sqlite stukloopt op de MySQL-only GREATEST()-call.
 */
function stockProductWithRealEvents(string $siteId, array $overrides = []): Product
{
    $group = stockGroup($siteId);

    return Product::create(array_merge([
        'name' => ['en' => 'Vaas ' . uniqid()],
        'slug' => ['en' => 'vaas-' . uniqid()],
        'site_ids' => [$siteId],
        'product_group_id' => $group->id,
        'use_stock' => true,
        'low_stock_notification_limit' => 5,
        'stock' => 0,
        'total_stock' => 0,
        'in_stock' => false,
        'stock_status' => 'out_of_stock',
        'price' => 10.00,
        'current_price' => 10.00,
    ], $overrides));
}

function stockRule(string $trigger, string $siteId = 'main'): AutomationRule
{
    return AutomationRule::create([
        'site_id' => $siteId,
        'name' => 'R',
        'trigger' => $trigger,
        'conditions' => [],
        'actions' => [],
        'is_active' => true,
    ]);
}

function markStockRunSuccessful(
    AutomationRule $rule,
    Product $product,
    string $trigger,
    ?\Carbon\Carbon $createdAt = null
): AutomationRuleRun {
    $run = AutomationRuleRun::create([
        'rule_id' => $rule->id,
        'site_id' => $rule->site_id,
        'subject_type' => Product::class,
        'subject_id' => $product->id,
        'trigger' => $trigger,
        'status' => AutomationRuleRun::STATUS_SUCCESS,
        'results' => [],
    ]);

    // Timestamp-kolommen (zowel hier als de automation_stock_*_at-markers)
    // zijn seconde-precisie; forceer expliciet een eerder moment zodat een
    // test die "run vóór de 0-episode" bedoelt niet toevallig in dezelfde
    // seconde als now() belandt (dat zou de >=-vergelijking laten kantelen).
    if ($createdAt) {
        $run->forceFill(['created_at' => $createdAt])->saveQuietly();
    }

    return $run->fresh();
}

it('bevat een product onder de limiet als low-kandidaat', function () {
    $rule = stockRule('stock.low');
    $product = stockProduct('main', ['stock' => 2, 'low_stock_notification_limit' => 5]);

    $ids = StockRuleScanner::lowCandidates($rule)->pluck('id');

    expect($ids)->toContain($product->id);
});

it('vuurt stock.low niet opnieuw na een geslaagde run, ook als het product laag blijft', function () {
    $rule = stockRule('stock.low');
    $product = stockProduct('main', ['stock' => 2, 'low_stock_notification_limit' => 5]);

    markStockRunSuccessful($rule, $product, 'stock.low');

    $ids = StockRuleScanner::lowCandidates($rule)->pluck('id');

    expect($ids)->not->toContain($product->id);
});

it('reset stock.low en levert stock.back op zodra het product via 0 herstelt', function () {
    $lowRule = stockRule('stock.low');
    $backRule = stockRule('stock.back');
    $product = stockProduct('main', ['stock' => 2, 'low_stock_notification_limit' => 5]);

    markStockRunSuccessful($lowRule, $product, 'stock.low', now()->subMinutes(5));
    expect(StockRuleScanner::lowCandidates($lowRule)->pluck('id'))->not->toContain($product->id);

    // Voorraad zakt naar 0: nieuwe 0-episode (automation_stock_zero_at gezet).
    $product->update(['stock' => 0]);
    // Voorraad herstelt weer naar iets onder de limiet: episode "hersteld".
    $product->update(['stock' => 3]);

    expect(StockRuleScanner::backCandidates($backRule)->pluck('id'))
        ->toContain($product->id);

    expect(StockRuleScanner::lowCandidates($lowRule)->pluck('id'))
        ->toContain($product->id);
});

it('vuurt stock.back niet opnieuw na een geslaagde run voor dezelfde 0-episode', function () {
    $backRule = stockRule('stock.back');
    $product = stockProduct('main', ['stock' => 2, 'low_stock_notification_limit' => 5]);

    $product->update(['stock' => 0]);
    $product->update(['stock' => 3]);

    markStockRunSuccessful($backRule, $product, 'stock.back');

    expect(StockRuleScanner::backCandidates($backRule)->pluck('id'))
        ->not->toContain($product->id);
});

it('registreert een 0-episode voor een product dat al met stock 0 wordt aangemaakt (pre-order)', function () {
    $backRule = stockRule('stock.back');

    $product = stockProductWithRealEvents('main', ['stock' => 0]);

    // De created-hook moet de 0-episode meteen vastleggen, ook al is er nooit
    // een wasChanged('stock')-transitie geweest (dit was een INSERT).
    expect($product->fresh()->automation_stock_zero_at)->not->toBeNull();

    // Eerste échte heraanvulling: 0 -> N.
    $product->update(['stock' => 5]);

    expect(StockRuleScanner::backCandidates($backRule)->pluck('id'))
        ->toContain($product->id);
});

it('sluit een product van een andere site uit', function () {
    $rule = stockRule('stock.low', 'main');
    $otherSiteProduct = stockProduct('other', ['stock' => 2, 'low_stock_notification_limit' => 5]);

    $ids = StockRuleScanner::lowCandidates($rule)->pluck('id');

    expect($ids)->not->toContain($otherSiteProduct->id);
});

it('sluit een product zonder use_stock uit', function () {
    $rule = stockRule('stock.low');
    $product = stockProduct('main', ['stock' => 2, 'low_stock_notification_limit' => 5, 'use_stock' => false]);

    $ids = StockRuleScanner::lowCandidates($rule)->pluck('id');

    expect($ids)->not->toContain($product->id);
});
