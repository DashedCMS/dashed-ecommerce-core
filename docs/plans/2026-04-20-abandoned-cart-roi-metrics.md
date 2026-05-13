# Abandoned Cart ROI Metrics Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Voeg ROI metrics toe per abandoned cart flow-step (omzet, conversion rate, gemiddelde conversietijd) en breid de flow-stats widget uit met recovery rate en gemiddelde conversietijd.

**Architecture:** Logica leeft op `AbandonedCartFlowStep` en `AbandonedCartFlow` als pure computed methods zodat deze testbaar zijn. Filament columns en widget stats roepen deze methods aan. Geen wijzigingen aan database schema, alles werkt op bestaande velden `sent_at`, `converted_at`, `order_id` op `AbandonedCartEmail` en `total` op `Order`.

**Tech Stack:** Laravel 12, PHP 8.4, Filament 4, Pest 3, Orchestra Testbench, spatie/laravel-translatable.

---

## File Structure

**Modify:**
- `packages/dashed/dashed-ecommerce-core/src/Models/AbandonedCartFlowStep.php` — computed methods: `revenueSum()`, `conversionRateFromSent()`, `averageConversionHours()`
- `packages/dashed/dashed-ecommerce-core/src/Models/AbandonedCartFlow.php` — aggregate methods: `recoveryRate()`, `averageConversionHours()`, `revenueSum()`
- `packages/dashed/dashed-ecommerce-core/src/Filament/Resources/AbandonedCartFlowResource/RelationManagers/FlowStepsRelationManager.php` — twee nieuwe kolommen (omzet, conversieratio)
- `packages/dashed/dashed-ecommerce-core/src/Filament/Resources/AbandonedCartFlowResource/Widgets/AbandonedCartFlowStats.php` — twee nieuwe stats (recovery rate, gemiddelde conversietijd)

**Create:**
- `packages/dashed/dashed-ecommerce-core/tests/AbandonedCartRoiMetricsTest.php` — Pest tests voor alle computed methods

---

## Testing Notes

- Gebruikt `RefreshDatabase` en Orchestra Testbench. SQLite in-memory.
- Factories zijn niet gegarandeerd aanwezig; gebruik directe `Model::create()` met expliciete velden.
- Cart, Order en AbandonedCartEmail records worden handmatig aangemaakt per test.

---

## Task 1: AbandonedCartFlowStep::revenueSum()

**Files:**
- Modify: `packages/dashed/dashed-ecommerce-core/src/Models/AbandonedCartFlowStep.php`
- Test: `packages/dashed/dashed-ecommerce-core/tests/AbandonedCartRoiMetricsTest.php`

- [ ] **Step 1: Write the failing test**

Write bovenaan het testbestand:

```php
<?php

use Dashed\DashedEcommerceCore\Models\AbandonedCartEmail;
use Dashed\DashedEcommerceCore\Models\AbandonedCartFlow;
use Dashed\DashedEcommerceCore\Models\AbandonedCartFlowStep;
use Dashed\DashedEcommerceCore\Models\Cart;
use Dashed\DashedEcommerceCore\Models\Order;

function makeFlowStep(array $overrides = []): AbandonedCartFlowStep
{
    $flow = AbandonedCartFlow::create([
        'name' => 'Test flow',
        'is_active' => true,
        'discount_prefix' => 'TST',
    ]);

    return AbandonedCartFlowStep::create(array_merge([
        'flow_id' => $flow->id,
        'sort_order' => 1,
        'delay_value' => 1,
        'delay_unit' => 'hours',
        'subject' => ['nl' => 'Onderwerp'],
        'blocks' => [],
        'incentive_enabled' => false,
        'incentive_type' => 'percentage',
        'incentive_value' => 0,
        'incentive_valid_days' => 0,
        'enabled' => true,
    ], $overrides));
}

function makeCart(): Cart
{
    return Cart::create(['locale' => 'nl']);
}

function makeConvertedEmail(AbandonedCartFlowStep $step, float $orderTotal): AbandonedCartEmail
{
    $cart = makeCart();
    $order = Order::create([
        'cart_id' => $cart->id,
        'total' => $orderTotal,
        'status' => 'paid',
        'hash' => bin2hex(random_bytes(8)),
    ]);

    return AbandonedCartEmail::create([
        'cart_id' => $cart->id,
        'flow_step_id' => $step->id,
        'email' => 'klant@example.com',
        'email_number' => 1,
        'status' => 'sent',
        'scheduled_at' => now()->subHours(2),
        'sent_at' => now()->subHour(),
        'converted_at' => now(),
        'order_id' => $order->id,
    ]);
}

it('sums revenue across converted emails for a flow step', function () {
    $step = makeFlowStep();
    makeConvertedEmail($step, 49.95);
    makeConvertedEmail($step, 120.00);

    AbandonedCartEmail::create([
        'cart_id' => makeCart()->id,
        'flow_step_id' => $step->id,
        'email' => 'ander@example.com',
        'email_number' => 1,
        'status' => 'pending',
        'scheduled_at' => now(),
    ]);

    expect($step->revenueSum())->toEqualWithDelta(169.95, 0.001);
});

it('returns zero revenue when no conversions exist', function () {
    $step = makeFlowStep();

    expect($step->revenueSum())->toBe(0.0);
});
```

- [ ] **Step 2: Run test to verify it fails**

Run: `cd packages/dashed/dashed-ecommerce-core && vendor/bin/pest --filter=revenue`
Expected: FAIL met `Call to undefined method ... revenueSum`.

Als Pest niet beschikbaar is op package-niveau, run vanaf repo-root: `./vendor/bin/pest packages/dashed/dashed-ecommerce-core/tests --filter=revenue`.

- [ ] **Step 3: Write minimal implementation**

Open `packages/dashed/dashed-ecommerce-core/src/Models/AbandonedCartFlowStep.php` en voeg toe onder de bestaande relaties (voor de `getDelayLabelAttribute` accessor):

```php
public function revenueSum(): float
{
    return (float) $this->emails()
        ->whereNotNull('converted_at')
        ->whereNotNull('order_id')
        ->join('dashed__orders', 'dashed__orders.id', '=', 'dashed__abandoned_cart_emails.order_id')
        ->sum('dashed__orders.total');
}
```

- [ ] **Step 4: Run test to verify it passes**

Run: `vendor/bin/pest --filter=revenue`
Expected: PASS (2 assertions).

- [ ] **Step 5: Commit**

```bash
cd packages/dashed/dashed-ecommerce-core
git add src/Models/AbandonedCartFlowStep.php tests/AbandonedCartRoiMetricsTest.php
git commit -m "feat(abandoned-cart): add revenueSum() to flow step"
```

(Commit alleen na expliciete goedkeuring van de gebruiker.)

---

## Task 2: AbandonedCartFlowStep::conversionRateFromSent()

**Files:**
- Modify: `packages/dashed/dashed-ecommerce-core/src/Models/AbandonedCartFlowStep.php`
- Test: `packages/dashed/dashed-ecommerce-core/tests/AbandonedCartRoiMetricsTest.php`

- [ ] **Step 1: Write the failing test**

Voeg toe aan het testbestand:

```php
it('calculates conversion rate based on sent emails', function () {
    $step = makeFlowStep();

    // 3 sent, 1 converted = 33.3%
    makeConvertedEmail($step, 50.00);

    AbandonedCartEmail::create([
        'cart_id' => makeCart()->id,
        'flow_step_id' => $step->id,
        'email' => 'a@example.com',
        'email_number' => 1,
        'status' => 'sent',
        'scheduled_at' => now()->subHour(),
        'sent_at' => now()->subMinutes(30),
    ]);
    AbandonedCartEmail::create([
        'cart_id' => makeCart()->id,
        'flow_step_id' => $step->id,
        'email' => 'b@example.com',
        'email_number' => 1,
        'status' => 'sent',
        'scheduled_at' => now()->subHour(),
        'sent_at' => now()->subMinutes(15),
    ]);

    expect($step->conversionRateFromSent())->toEqualWithDelta(33.3, 0.1);
});

it('returns zero conversion rate when no emails sent', function () {
    $step = makeFlowStep();

    expect($step->conversionRateFromSent())->toBe(0.0);
});
```

- [ ] **Step 2: Run test to verify it fails**

Run: `vendor/bin/pest --filter=conversion_rate`
Expected: FAIL met `undefined method conversionRateFromSent`.

- [ ] **Step 3: Write minimal implementation**

Voeg toe onder `revenueSum()`:

```php
public function conversionRateFromSent(): float
{
    $sent = $this->emails()->whereNotNull('sent_at')->count();

    if ($sent === 0) {
        return 0.0;
    }

    $converted = $this->emails()->whereNotNull('converted_at')->count();

    return round(($converted / $sent) * 100, 1);
}
```

- [ ] **Step 4: Run test to verify it passes**

Run: `vendor/bin/pest --filter=conversion_rate`
Expected: PASS (2 assertions).

- [ ] **Step 5: Commit**

```bash
git add src/Models/AbandonedCartFlowStep.php tests/AbandonedCartRoiMetricsTest.php
git commit -m "feat(abandoned-cart): add conversionRateFromSent() to flow step"
```

---

## Task 3: AbandonedCartFlowStep::averageConversionHours()

**Files:**
- Modify: `packages/dashed/dashed-ecommerce-core/src/Models/AbandonedCartFlowStep.php`
- Test: `packages/dashed/dashed-ecommerce-core/tests/AbandonedCartRoiMetricsTest.php`

- [ ] **Step 1: Write the failing test**

```php
it('returns average hours between sent and converted', function () {
    $step = makeFlowStep();

    $cartA = makeCart();
    $orderA = Order::create([
        'cart_id' => $cartA->id,
        'total' => 100.0,
        'status' => 'paid',
        'hash' => bin2hex(random_bytes(8)),
    ]);
    AbandonedCartEmail::create([
        'cart_id' => $cartA->id,
        'flow_step_id' => $step->id,
        'email' => 'x@example.com',
        'email_number' => 1,
        'status' => 'sent',
        'scheduled_at' => now()->subHours(10),
        'sent_at' => now()->subHours(8),
        'converted_at' => now()->subHours(6),
        'order_id' => $orderA->id,
    ]);

    $cartB = makeCart();
    $orderB = Order::create([
        'cart_id' => $cartB->id,
        'total' => 50.0,
        'status' => 'paid',
        'hash' => bin2hex(random_bytes(8)),
    ]);
    AbandonedCartEmail::create([
        'cart_id' => $cartB->id,
        'flow_step_id' => $step->id,
        'email' => 'y@example.com',
        'email_number' => 1,
        'status' => 'sent',
        'scheduled_at' => now()->subHours(10),
        'sent_at' => now()->subHours(4),
        'converted_at' => now(),
        'order_id' => $orderB->id,
    ]);

    expect($step->averageConversionHours())->toEqualWithDelta(3.0, 0.1);
});

it('returns null when no conversions exist', function () {
    $step = makeFlowStep();

    expect($step->averageConversionHours())->toBeNull();
});
```

- [ ] **Step 2: Run test to verify it fails**

Run: `vendor/bin/pest --filter=average_hours`
Expected: FAIL met `undefined method averageConversionHours`.

- [ ] **Step 3: Write minimal implementation**

Voeg toe onder `conversionRateFromSent()`:

```php
public function averageConversionHours(): ?float
{
    $rows = $this->emails()
        ->whereNotNull('sent_at')
        ->whereNotNull('converted_at')
        ->get(['sent_at', 'converted_at']);

    if ($rows->isEmpty()) {
        return null;
    }

    $hours = $rows->map(fn ($row) => $row->sent_at->floatDiffInHours($row->converted_at));

    return round($hours->avg(), 1);
}
```

- [ ] **Step 4: Run test to verify it passes**

Run: `vendor/bin/pest --filter=average_hours`
Expected: PASS (2 assertions).

- [ ] **Step 5: Commit**

```bash
git add src/Models/AbandonedCartFlowStep.php tests/AbandonedCartRoiMetricsTest.php
git commit -m "feat(abandoned-cart): add averageConversionHours() to flow step"
```

---

## Task 4: AbandonedCartFlow aggregate methods

**Files:**
- Modify: `packages/dashed/dashed-ecommerce-core/src/Models/AbandonedCartFlow.php`
- Test: `packages/dashed/dashed-ecommerce-core/tests/AbandonedCartRoiMetricsTest.php`

- [ ] **Step 1: Write the failing test**

```php
it('aggregates recovery rate across all steps in a flow', function () {
    $flow = AbandonedCartFlow::create([
        'name' => 'Flow A',
        'is_active' => true,
        'discount_prefix' => 'FLW',
    ]);
    $step1 = AbandonedCartFlowStep::create([
        'flow_id' => $flow->id,
        'sort_order' => 1,
        'delay_value' => 1,
        'delay_unit' => 'hours',
        'subject' => ['nl' => 'Step 1'],
        'blocks' => [],
        'incentive_enabled' => false,
        'incentive_type' => 'percentage',
        'incentive_value' => 0,
        'incentive_valid_days' => 0,
        'enabled' => true,
    ]);
    $step2 = AbandonedCartFlowStep::create([
        'flow_id' => $flow->id,
        'sort_order' => 2,
        'delay_value' => 24,
        'delay_unit' => 'hours',
        'subject' => ['nl' => 'Step 2'],
        'blocks' => [],
        'incentive_enabled' => false,
        'incentive_type' => 'percentage',
        'incentive_value' => 0,
        'incentive_valid_days' => 0,
        'enabled' => true,
    ]);

    // Step 1: 2 sent, 1 converted. Step 2: 2 sent, 1 converted. Total 4 sent, 2 converted = 50%.
    makeConvertedEmail($step1, 40.0);
    AbandonedCartEmail::create([
        'cart_id' => makeCart()->id,
        'flow_step_id' => $step1->id,
        'email' => 'a@example.com',
        'email_number' => 1,
        'status' => 'sent',
        'scheduled_at' => now()->subHour(),
        'sent_at' => now()->subMinutes(30),
    ]);
    makeConvertedEmail($step2, 60.0);
    AbandonedCartEmail::create([
        'cart_id' => makeCart()->id,
        'flow_step_id' => $step2->id,
        'email' => 'b@example.com',
        'email_number' => 1,
        'status' => 'sent',
        'scheduled_at' => now()->subHour(),
        'sent_at' => now()->subMinutes(15),
    ]);

    expect($flow->recoveryRate())->toEqualWithDelta(50.0, 0.1)
        ->and($flow->revenueSum())->toEqualWithDelta(100.0, 0.001);
});
```

- [ ] **Step 2: Run test to verify it fails**

Run: `vendor/bin/pest --filter=aggregates_recovery`
Expected: FAIL met `undefined method recoveryRate`.

- [ ] **Step 3: Write minimal implementation**

Voeg toe aan `AbandonedCartFlow.php` onder de bestaande relaties:

```php
public function recoveryRate(): float
{
    $stepIds = $this->steps()->pluck('id');

    if ($stepIds->isEmpty()) {
        return 0.0;
    }

    $sent = \Dashed\DashedEcommerceCore\Models\AbandonedCartEmail::query()
        ->whereIn('flow_step_id', $stepIds)
        ->whereNotNull('sent_at')
        ->count();

    if ($sent === 0) {
        return 0.0;
    }

    $converted = \Dashed\DashedEcommerceCore\Models\AbandonedCartEmail::query()
        ->whereIn('flow_step_id', $stepIds)
        ->whereNotNull('converted_at')
        ->count();

    return round(($converted / $sent) * 100, 1);
}

public function revenueSum(): float
{
    $stepIds = $this->steps()->pluck('id');

    if ($stepIds->isEmpty()) {
        return 0.0;
    }

    return (float) \Dashed\DashedEcommerceCore\Models\AbandonedCartEmail::query()
        ->whereIn('flow_step_id', $stepIds)
        ->whereNotNull('converted_at')
        ->whereNotNull('order_id')
        ->join('dashed__orders', 'dashed__orders.id', '=', 'dashed__abandoned_cart_emails.order_id')
        ->sum('dashed__orders.total');
}

public function averageConversionHours(): ?float
{
    $stepIds = $this->steps()->pluck('id');

    if ($stepIds->isEmpty()) {
        return null;
    }

    $rows = \Dashed\DashedEcommerceCore\Models\AbandonedCartEmail::query()
        ->whereIn('flow_step_id', $stepIds)
        ->whereNotNull('sent_at')
        ->whereNotNull('converted_at')
        ->get(['sent_at', 'converted_at']);

    if ($rows->isEmpty()) {
        return null;
    }

    $hours = $rows->map(fn ($row) => $row->sent_at->floatDiffInHours($row->converted_at));

    return round($hours->avg(), 1);
}
```

- [ ] **Step 4: Run test to verify it passes**

Run: `vendor/bin/pest --filter=aggregates_recovery`
Expected: PASS (2 assertions).

- [ ] **Step 5: Commit**

```bash
git add src/Models/AbandonedCartFlow.php tests/AbandonedCartRoiMetricsTest.php
git commit -m "feat(abandoned-cart): add flow-level recoveryRate/revenueSum/averageConversionHours"
```

---

## Task 5: Revenue en conversie-ratio kolommen in FlowStepsRelationManager

**Files:**
- Modify: `packages/dashed/dashed-ecommerce-core/src/Filament/Resources/AbandonedCartFlowResource/RelationManagers/FlowStepsRelationManager.php`

- [ ] **Step 1: Locate the existing `converted_count` column**

Run: `vendor/bin/pest` is niet relevant hier (UI wijziging). Open het bestand en vind de `converted_count` TextColumn. De nieuwe kolommen komen er direct na.

- [ ] **Step 2: Add revenue column**

Voeg direct na de `converted_count` kolom toe:

```php
\Filament\Tables\Columns\TextColumn::make('revenue')
    ->label('Omzet')
    ->badge()
    ->color('success')
    ->state(fn ($record) => '€ ' . number_format($record->revenueSum(), 2, ',', '.')),
```

- [ ] **Step 3: Add conversion rate column**

Voeg direct daarna toe:

```php
\Filament\Tables\Columns\TextColumn::make('conversion_rate')
    ->label('Conversieratio')
    ->badge()
    ->color(function ($state) {
        $rate = (float) str_replace(['%', ','], ['', '.'], $state);
        return match (true) {
            $rate >= 10.0 => 'success',
            $rate >= 3.0 => 'warning',
            default => 'gray',
        };
    })
    ->state(fn ($record) => number_format($record->conversionRateFromSent(), 1, ',', '.') . '%'),
```

- [ ] **Step 4: Smoke-test in de browser**

Start `composer dev` in de root, log in in de Filament admin, open een abandoned cart flow. Bevestig dat de twee nieuwe kolommen (Omzet, Conversieratio) verschijnen, badges juist kleuren, en geen errors in de console of laravel.log staan.

- [ ] **Step 5: Commit**

```bash
git add src/Filament/Resources/AbandonedCartFlowResource/RelationManagers/FlowStepsRelationManager.php
git commit -m "feat(abandoned-cart): show revenue and conversion rate per flow step"
```

---

## Task 6: Recovery rate en conversietijd stats in AbandonedCartFlowStats widget

**Files:**
- Modify: `packages/dashed/dashed-ecommerce-core/src/Filament/Resources/AbandonedCartFlowResource/Widgets/AbandonedCartFlowStats.php`

- [ ] **Step 1: Vind huidige `getStats()` return array**

De widget gebruikt momenteel `$this->record` op de Edit page. Bestaande stats: Verzonden, Geklikt (klikratio), Conversies (conversieratio op basis van klikken), Omzet, Knop kliks, Product kliks.

- [ ] **Step 2: Voeg recovery rate stat toe**

Voeg toe aan de return array van `getStats()` (direct na Conversies of Omzet, naar voorkeur):

```php
\Filament\Widgets\StatsOverviewWidget\Stat::make(
    'Recovery rate',
    number_format($this->record->recoveryRate(), 1, ',', '.') . '%'
)
    ->description('Conversies gedeeld door verzonden mails')
    ->icon('heroicon-o-arrow-trending-up')
    ->color('success'),
```

- [ ] **Step 3: Voeg gemiddelde conversietijd stat toe**

Voeg daarna toe:

```php
\Filament\Widgets\StatsOverviewWidget\Stat::make(
    'Gem. conversietijd',
    (function () {
        $hours = $this->record->averageConversionHours();
        if ($hours === null) {
            return '-';
        }
        if ($hours < 1.0) {
            return round($hours * 60) . ' min';
        }
        return number_format($hours, 1, ',', '.') . ' uur';
    })()
)
    ->description('Gemiddelde tijd tussen mail en order')
    ->icon('heroicon-o-clock')
    ->color('info'),
```

- [ ] **Step 4: Smoke-test in de browser**

Herstart `composer dev` indien nodig. Open een abandoned cart flow edit pagina. Controleer dat de twee nieuwe stats zichtbaar zijn, waarden kloppen tegen de verwachte dataset in dev, en geen errors voorkomen.

- [ ] **Step 5: Commit**

```bash
git add src/Filament/Resources/AbandonedCartFlowResource/Widgets/AbandonedCartFlowStats.php
git commit -m "feat(abandoned-cart): add recovery rate and avg conversion time stats"
```

---

## Task 7: Package version bump

**Files:**
- Modify: `packages/dashed/dashed-ecommerce-core/composer.json`
- Modify: root `composer.lock` (impliciet na `composer update`)

- [ ] **Step 1: Verhoog patch version in composer.json**

Open `packages/dashed/dashed-ecommerce-core/composer.json` en pas de version bump toe. Vorige release was v4.1.3 volgens memory 6052. Nieuwe minor is gerechtvaardigd (nieuwe feature, geen breaking change), dus `4.2.0`.

Indien er geen `version` veld in composer.json staat, skip deze stap en ga direct door naar de git tag.

- [ ] **Step 2: Vraag expliciete goedkeuring voor push en tag**

Per userfeedback-memory (feedback_no_auto_commit, feedback_tag_on_push): nooit automatisch committen of pushen. Stop hier en rapporteer aan de gebruiker:

> Fase 2 klaar, 6 commits lokaal. Mag ik pushen naar master en v4.2.0 taggen op `packages/dashed/dashed-ecommerce-core`?

- [ ] **Step 3: Na goedkeuring uitvoeren**

```bash
cd packages/dashed/dashed-ecommerce-core
git push origin master
git tag v4.2.0
git push origin v4.2.0
```

- [ ] **Step 4: Update root composer.lock**

```bash
cd ../../..
composer update dashed/dashed-ecommerce-core --no-scripts
```

Bevestig dat de lock file nu op v4.2.0 staat.

---

## Self-review notes

Scope van dit plan: alleen Fase 2 (ROI metrics). Fase 3 (popup funnel + A/B) komt in een apart plan.

Alle methoden staan getest via Pest units. UI wijzigingen worden handmatig gevalideerd in de browser omdat Filament kolomrendering met livewire test setup zwaar is relatief aan de wijziging.

Bekende trade-off: de huidige FlowStepsRelationManager columns gebruiken closures met per-row queries (bestaand N+1 patroon uit v4.1.3). Deze twee nieuwe kolommen volgen hetzelfde patroon. Als performance een issue wordt, dient het hele FlowStepsRelationManager patroon herschreven met een aggregated subquery, maar dat valt buiten scope van deze feature.
