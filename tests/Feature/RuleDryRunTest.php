<?php

declare(strict_types=1);

use Dashed\DashedMobileApi\MobileApiRegistry;
use Dashed\DashedEcommerceCore\Models\Order;
use Dashed\DashedEcommerceCore\Models\AutomationRule;
use Dashed\DashedEcommerceCore\Support\Automation\RuleDryRun;
use Dashed\DashedEcommerceCore\Support\Automation\AutomationContext;

/**
 * Task 8: de droogloop ("test tegen bestelling"). RuleDryRun::for() hergebruikt
 * de bestaande ConditionEvaluator/AutomationContext om te bepalen of een regel
 * zou matchen op een echte order, en beschrijft welke acties zouden draaien —
 * maar voert ze nooit uit. De kern-eis (geen enkele actie-`handle` wordt
 * aangeroepen) wordt hier hard vastgelegd met een spy die faalt zodra hij
 * wordt aangeroepen, niet enkel met een boolean die je zou kunnen vergeten te
 * asserten.
 */
function dryRunOrder(array $attributes = []): Order
{
    return Order::create(array_merge([
        'email' => 'klant@example.com',
        'status' => 'paid',
        'total' => 100,
    ], $attributes));
}

function dryRunRule(array $attributes = []): AutomationRule
{
    return AutomationRule::create(array_merge([
        'site_id' => 'site',
        'name' => 'Droogloop-regel',
        'trigger' => 'order.paid',
        'conditions' => [],
        'actions' => [],
        'is_active' => true,
    ], $attributes));
}

/**
 * Registreert een order-actie waarvan de handler faalt zodra hij wordt
 * aangeroepen — de droogloop mag deze closure onder geen beding uitvoeren.
 * Zo'n throw propageert direct uit RuleDryRun::for() en faalt de test met een
 * duidelijke oorzaak, in plaats van stil te blijven als we zouden vergeten om
 * een los "is er aangeroepen"-vlaggetje te asserten.
 */
function registerNeverCalledAction(string $key, string $label = 'Spy-actie'): void
{
    app(MobileApiRegistry::class)->registerOrderActions([
        [
            'key' => $key,
            'label' => $label,
            'automatable' => true,
            'visible' => fn () => false,
            'handle' => function () use ($key): void {
                throw new RuntimeException("Droogloop riep de handle van '{$key}' aan — dit mag nooit gebeuren.");
            },
        ],
    ]);
}

it('describes a matched order as matched with the actions the rule would run, without invoking any handle', function () {
    registerNeverCalledAction('spy_one');
    registerNeverCalledAction('spy_two', 'Tweede spy-actie');

    $order = dryRunOrder(['total' => 150]);
    $rule = dryRunRule([
        'conditions' => [['field' => 'total', 'operator' => 'gt', 'value' => 100]],
        'actions' => [
            ['key' => 'spy_two', 'params' => ['foo' => 'bar']],
            ['key' => 'spy_one', 'params' => []],
        ],
    ]);

    $result = RuleDryRun::for($rule, $order);

    expect($result['matched'])->toBeTrue()
        ->and($result['context'])->toBe(AutomationContext::forOrder($order))
        ->and($result['actions'])->toBe([
            ['key' => 'spy_two', 'label' => 'Tweede spy-actie', 'params' => ['foo' => 'bar']],
            ['key' => 'spy_one', 'label' => 'Spy-actie', 'params' => []],
        ]);
});

it('describes a non-matching order as matched: false, with no actions and without invoking any handle', function () {
    registerNeverCalledAction('spy_never');

    $order = dryRunOrder(['total' => 10]);
    $rule = dryRunRule([
        'conditions' => [['field' => 'total', 'operator' => 'gt', 'value' => 100]],
        'actions' => [['key' => 'spy_never', 'params' => []]],
    ]);

    $result = RuleDryRun::for($rule, $order);

    expect($result['matched'])->toBeFalse()
        ->and($result['actions'])->toBe([])
        ->and($result['context'])->toBe(AutomationContext::forOrder($order));
});

it('never calls a single action handle even when the rule has multiple actions and matches', function () {
    $calls = 0;
    app(MobileApiRegistry::class)->registerOrderActions([
        [
            'key' => 'counting_spy',
            'label' => 'Tellende spy',
            'automatable' => true,
            'visible' => fn () => false,
            'handle' => function () use (&$calls): void {
                $calls++;
            },
        ],
    ]);

    $order = dryRunOrder(['total' => 200]);
    $rule = dryRunRule([
        'conditions' => [],
        'actions' => [
            ['key' => 'counting_spy', 'params' => []],
            ['key' => 'counting_spy', 'params' => ['x' => 1]],
        ],
    ]);

    $result = RuleDryRun::for($rule, $order);

    expect($calls)->toBe(0)
        ->and($result['matched'])->toBeTrue()
        ->and($result['actions'])->toHaveCount(2);
});

it('falls back to the raw key as label for an action key that is no longer registered, without crashing', function () {
    $order = dryRunOrder();
    $rule = dryRunRule([
        'conditions' => [],
        'actions' => [['key' => 'does-not-exist', 'params' => ['a' => 1]]],
    ]);

    $result = RuleDryRun::for($rule, $order);

    expect($result['matched'])->toBeTrue()
        ->and($result['actions'])->toBe([
            ['key' => 'does-not-exist', 'label' => 'does-not-exist', 'params' => ['a' => 1]],
        ]);
});
