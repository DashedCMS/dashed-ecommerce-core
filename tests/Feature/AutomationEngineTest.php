<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Queue;
use Dashed\DashedCore\Classes\Sites;
use Dashed\DashedEcommerceCore\Models\Order;
use Dashed\DashedEcommerceCore\Models\AutomationRule;
use Dashed\DashedEcommerceCore\Models\AutomationRuleRun;
use Dashed\DashedMobileApi\MobileApiRegistry;
use Dashed\DashedEcommerceCore\Jobs\RunAutomationRuleJob;
use Dashed\DashedEcommerceCore\Events\Orders\OrderCreatedEvent;
use Dashed\DashedEcommerceCore\Support\Automation\AutomationEngine;
use Dashed\DashedEcommerceCore\Events\Orders\OrderFulfillmentStatusChangedEvent;

/**
 * De engine: matchen (AutomationEngine::rulesFor + ConditionEvaluator),
 * uitvoeren (AutomationEngine::run, via RunAutomationRuleJob) en de twee
 * lus-beveiligingslagen. Dit package wordt via de root dashed-cms-app
 * gedraaid (zie de test-harness in de taakbrief), waar Laravel's package-
 * discovery de échte DashedEcommerceCoreEventServiceProvider (met de erin
 * geregistreerde AutomationTriggerSubscriber) automatisch boot — dus geen
 * handmatige Event::subscribe() hier nodig; dat zou 'm dubbel registreren.
 */

function automationSite(): string
{
    return Sites::getActive();
}

function makeAutomationOrder(array $attributes = []): Order
{
    return Order::create(array_merge([
        'email' => 'klant@example.com',
        'status' => 'concept',
    ], $attributes));
}

function makeAutomationRule(array $attributes = []): AutomationRule
{
    return AutomationRule::create(array_merge([
        'site_id' => automationSite(),
        'name' => 'Regel',
        'trigger' => 'order.paid',
        'conditions' => [],
        'actions' => [],
        'is_active' => true,
    ], $attributes));
}

function registerSpyAction(string $key, callable $handle, bool $automatable = true): void
{
    app(MobileApiRegistry::class)->registerOrderActions([
        ['key' => $key, 'automatable' => $automatable, 'visible' => fn () => false, 'handle' => $handle],
    ]);
}

// -- AutomationEngine::rulesFor() --------------------------------------------------

it('rulesFor only returns active rules matching both the site and the trigger', function () {
    $site = automationSite();

    $match = makeAutomationRule(['trigger' => 'order.paid', 'site_id' => $site, 'is_active' => true]);
    makeAutomationRule(['trigger' => 'order.paid', 'site_id' => $site, 'is_active' => false]);
    makeAutomationRule(['trigger' => 'order.cancelled', 'site_id' => $site, 'is_active' => true]);
    makeAutomationRule(['trigger' => 'order.paid', 'site_id' => 'a-different-site', 'is_active' => true]);

    $rules = AutomationEngine::rulesFor('order.paid', $site);

    expect($rules->pluck('id')->all())->toBe([$match->id]);
});

// -- (a) subscriber: alleen actieve regels van de juiste site+trigger vuren -------

it('dispatches RunAutomationRuleJob on the ecommerce queue only for active rules whose trigger, site and conditions all match', function () {
    Queue::fake();
    $site = automationSite();

    $matching = makeAutomationRule(['trigger' => 'order.created', 'site_id' => $site, 'conditions' => []]);
    makeAutomationRule(['trigger' => 'order.created', 'site_id' => $site, 'is_active' => false]);
    makeAutomationRule(['trigger' => 'order.cancelled', 'site_id' => $site]);
    makeAutomationRule([
        'trigger' => 'order.created',
        'site_id' => $site,
        'conditions' => [['field' => 'total', 'operator' => 'gt', 'value' => 999999]],
    ]);

    $order = makeAutomationOrder(['total' => 50]);

    event(new OrderCreatedEvent($order));

    Queue::assertPushed(RunAutomationRuleJob::class, 1);
    Queue::assertPushed(RunAutomationRuleJob::class, fn ($job) => $job->rule->is($matching) && $job->subject->is($order));
    Queue::assertPushedOn('ecommerce', RunAutomationRuleJob::class);
});

it('exposes old_status/new_status from the event in the condition context for order.fulfillment_changed', function () {
    Queue::fake();
    $site = automationSite();

    $rule = makeAutomationRule([
        'trigger' => 'order.fulfillment_changed',
        'site_id' => $site,
        'conditions' => [['field' => 'new_status', 'operator' => 'eq', 'value' => 'packed']],
    ]);
    makeAutomationRule([
        'trigger' => 'order.fulfillment_changed',
        'site_id' => $site,
        'conditions' => [['field' => 'new_status', 'operator' => 'eq', 'value' => 'shipped']],
    ]);

    $order = makeAutomationOrder(['fulfillment_status' => 'unhandled']);

    event(new OrderFulfillmentStatusChangedEvent($order, 'unhandled', 'packed'));

    Queue::assertPushed(RunAutomationRuleJob::class, 1);
    Queue::assertPushed(RunAutomationRuleJob::class, fn ($job) => $job->rule->is($rule));
});

// -- (b) acties draaien op volgorde -------------------------------------------------

it('runs the actions of a matched rule in order', function () {
    $calls = [];
    registerSpyAction('spy_one', function () use (&$calls) {
        $calls[] = 'one';
    });
    registerSpyAction('spy_two', function () use (&$calls) {
        $calls[] = 'two';
    });

    $order = makeAutomationOrder();
    $rule = makeAutomationRule(['actions' => [['key' => 'spy_two'], ['key' => 'spy_one']]]);

    $run = AutomationEngine::run($rule, $order);

    expect($calls)->toBe(['two', 'one'])
        ->and($run->status)->toBe(AutomationRuleRun::STATUS_SUCCESS)
        ->and($run->results)->toBe([
            ['key' => 'spy_two', 'ok' => true, 'message' => null],
            ['key' => 'spy_one', 'ok' => true, 'message' => null],
        ]);
});

// -- (c) stopt bij de eerste fout ---------------------------------------------------

it('stops at the first failing action and logs status failed with the results up to that point', function () {
    $neverCalled = false;
    registerSpyAction('spy_ok', function () {});
    registerSpyAction('spy_fail', function () {
        throw new RuntimeException('kapot');
    });
    registerSpyAction('spy_never', function () use (&$neverCalled) {
        $neverCalled = true;
    });

    $order = makeAutomationOrder();
    $rule = makeAutomationRule(['actions' => [['key' => 'spy_ok'], ['key' => 'spy_fail'], ['key' => 'spy_never']]]);

    $run = AutomationEngine::run($rule, $order);

    expect($neverCalled)->toBeFalse()
        ->and($run->status)->toBe(AutomationRuleRun::STATUS_FAILED)
        ->and($run->error)->toBe('kapot')
        ->and($run->results)->toBe([
            ['key' => 'spy_ok', 'ok' => true, 'message' => null],
            ['key' => 'spy_fail', 'ok' => false, 'message' => 'kapot'],
        ]);
});

// -- (d) lus-beveiliging laag 1: zelfde-proces-events --------------------------------

it('layer 1: an action that raises a trigger event in the same process does not start a second run', function () {
    Queue::fake();
    $site = automationSite();

    // Regel B luistert precies op de trigger die regel A's actie veroorzaakt.
    makeAutomationRule(['trigger' => 'order.fulfillment_changed', 'site_id' => $site, 'conditions' => []]);
    $ruleA = makeAutomationRule(['trigger' => 'order.paid', 'site_id' => $site, 'actions' => [['key' => 'mark_packed']]]);

    $order = makeAutomationOrder(['fulfillment_status' => 'unhandled']);

    AutomationEngine::run($ruleA, $order);

    // mark_packed roept intern Order::changeFulfillmentStatus() aan, wat
    // synchroon OrderFulfillmentStatusChangedEvent dispatcht — zonder laag 1
    // zou dat de subscriber triggeren en een tweede job wegzetten.
    Queue::assertNotPushed(RunAutomationRuleJob::class);
});

it('suppressed() is false again after run() finishes, even though it was true during the actions', function () {
    $order = makeAutomationOrder();
    $rule = makeAutomationRule(['actions' => []]);

    expect(AutomationEngine::suppressed())->toBeFalse();
    AutomationEngine::run($rule, $order);
    expect(AutomationEngine::suppressed())->toBeFalse();
});

// -- (e) lus-beveiliging laag 2: venster per (regel, onderwerp) ----------------------

it('layer 2: the same rule for the same subject does not rerun within the window, even when the static flag is explicitly not set (cross-process simulation), and logs no extra row', function () {
    $order = makeAutomationOrder();
    $rule = makeAutomationRule(['actions' => []]);

    AutomationEngine::run($rule, $order);
    expect(AutomationRuleRun::count())->toBe(1);

    // Geen enkele nesting hier — suppressed() staat gegarandeerd op false,
    // exact het cross-process-scenario dat laag 1 niet kan dekken.
    expect(AutomationEngine::suppressed())->toBeFalse();

    $second = AutomationEngine::run($rule, $order);

    expect($second)->toBeNull()
        ->and(AutomationRuleRun::count())->toBe(1);
});

// -- (f) buiten het venster draait hij wél weer --------------------------------------

it('reruns the same rule for the same subject once outside the rerun window', function () {
    $order = makeAutomationOrder();
    $rule = makeAutomationRule(['actions' => []]);

    $first = AutomationEngine::run($rule, $order);
    // Het venster meet vanaf voltooiing (updated_at), niet vanaf de claim
    // (created_at) — zie IMPORTANT 1 hieronder. Beide terugzetten simuleert
    // "een tijdje geleden geclaimd én afgerond", het normale geval.
    AutomationRuleRun::whereKey($first->id)->update([
        'created_at' => now()->subMinutes(AutomationEngine::RERUN_WINDOW_MINUTES + 1),
        'updated_at' => now()->subMinutes(AutomationEngine::RERUN_WINDOW_MINUTES + 1),
    ]);

    $second = AutomationEngine::run($rule, $order);

    expect($second)->not->toBeNull()
        ->and(AutomationRuleRun::count())->toBe(2);
});

// -- (g) onbekende trigger/actie-key: overgeslagen, gelogd, geen exception ----------

it('skips and logs a rule whose action key is unknown, without throwing and without executing it', function () {
    $order = makeAutomationOrder();
    $rule = makeAutomationRule(['actions' => [['key' => 'does-not-exist']]]);

    $run = AutomationEngine::run($rule, $order);

    expect($run)->not->toBeNull()
        ->and($run->status)->toBe(AutomationRuleRun::STATUS_FAILED)
        ->and($run->results)->toBe([]);
});

it('skips and logs a rule whose trigger key is unknown, without throwing', function () {
    $order = makeAutomationOrder();
    $rule = makeAutomationRule(['trigger' => 'does.not.exist', 'actions' => []]);

    $run = AutomationEngine::run($rule, $order);

    expect($run)->not->toBeNull()
        ->and($run->status)->toBe(AutomationRuleRun::STATUS_FAILED)
        ->and($run->trigger)->toBe('does.not.exist');
});

// -- CRITICAL: claim-vóór-uitvoeren — de rem bestaat tijdens actie-uitvoering -------

it('CRITICAL: an action that re-enters AutomationEngine::run() for the same (rule, subject) does not execute a second time', function () {
    $calls = 0;
    $rule = null;

    // Depth-capped stand-in voor een gelijktijdige worker B die run() voor
    // exact dezelfde (regel, onderwerp) opnieuw aanroept — een getrouwe
    // reproductie van de reviewer's probe. Bewust begrensd (i.p.v.
    // onbegrensd): tegen de kapotte (pre-fix) engine loopt dit anders
    // eindeloos door (het geheugen raakte in de review-probe daadwerkelijk
    // op) — begrensd faalt de test hier netjes op de assertion in plaats van
    // een fatal memory-error.
    registerSpyAction('reentrant', function ($subject) use (&$calls, &$rule) {
        $calls++;
        if ($calls < 4) {
            AutomationEngine::run($rule, $subject);
        }
    });

    $order = makeAutomationOrder();
    $rule = makeAutomationRule(['actions' => [['key' => 'reentrant']]]);

    AutomationEngine::run($rule, $order);

    // Vóór de fix bestaat de guard-rij pas na afloop van de actie-lus, dus
    // de geneste run() ziet niets en voert de actie opnieuw uit (calls > 1).
    // Na de fix is de rij al 'running' vóórdat de actie draait, dus de
    // geneste run() wordt geclaimd door recentlyRan() en voert niets uit.
    expect($calls)->toBe(1)
        ->and(AutomationRuleRun::where('rule_id', $rule->id)->count())->toBe(1);
});

// -- atomaire claim: check-en-claim mag niet door een race heen kunnen --------------

it('atomic claim: a lock held by a racing process blocks run() from claiming, instead of creating a second running row', function () {
    $order = makeAutomationOrder();
    $rule = makeAutomationRule(['actions' => []]);

    // "Worker B" wint de race en houdt de claim-lock vast — dezelfde lock
    // die AutomationEngine::claim() zelf pakt, met een eigen (andere) owner,
    // zoals een writer/proces zou doen.
    $lock = Cache::lock(AutomationEngine::claimLockKey($rule, $order), 10);
    expect($lock->get())->toBeTrue();

    // "Worker A" (run()) race in terwijl B de lock vasthoudt: hij mag niet
    // alsnog claimen. Zonder een échte cross-process-lock zou dit gewoon
    // doorlopen en een rij aanmaken.
    $result = AutomationEngine::run($rule, $order);

    expect($result)->toBeNull()
        ->and(AutomationRuleRun::count())->toBe(0);

    $lock->release();

    // Met de lock vrij kan een normale run wél claimen en doorlopen.
    $normal = AutomationEngine::run($rule, $order);

    expect($normal)->not->toBeNull()
        ->and(AutomationRuleRun::count())->toBe(1);
});

// -- stale claim: een dode worker mag de regel niet permanent bevriezen -------------

it('a running row older than the stale bound no longer blocks a new run', function () {
    $order = makeAutomationOrder();
    $rule = makeAutomationRule(['actions' => []]);

    $stale = AutomationRuleRun::create([
        'rule_id' => $rule->id,
        'site_id' => $rule->site_id,
        'subject_type' => $order->getMorphClass(),
        'subject_id' => $order->getKey(),
        'trigger' => $rule->trigger,
        'status' => AutomationRuleRun::STATUS_RUNNING,
        'results' => [],
        'error' => null,
    ]);
    $stale->forceFill([
        'created_at' => now()->subMinutes(AutomationEngine::STALE_RUNNING_MINUTES + 1),
    ])->save();

    $run = AutomationEngine::run($rule, $order);

    expect($run)->not->toBeNull()
        ->and($run->id)->not->toBe($stale->id)
        ->and($run->status)->toBe(AutomationRuleRun::STATUS_SUCCESS);
});

it('a running row within the stale bound still blocks a new run', function () {
    $order = makeAutomationOrder();
    $rule = makeAutomationRule(['actions' => []]);

    AutomationRuleRun::create([
        'rule_id' => $rule->id,
        'site_id' => $rule->site_id,
        'subject_type' => $order->getMorphClass(),
        'subject_id' => $order->getKey(),
        'trigger' => $rule->trigger,
        'status' => AutomationRuleRun::STATUS_RUNNING,
        'results' => [],
        'error' => null,
    ]);

    $run = AutomationEngine::run($rule, $order);

    expect($run)->toBeNull()
        ->and(AutomationRuleRun::count())->toBe(1);
});

// -- exception-pad: de geclaimde rij mag nooit op 'running' blijven staan -----------

it('a throwing action leaves the claimed row failed, never stuck at running', function () {
    registerSpyAction('reentrant_throws', function () {
        throw new RuntimeException('kapot tijdens uitvoering');
    });

    $order = makeAutomationOrder();
    $rule = makeAutomationRule(['actions' => [['key' => 'reentrant_throws']]]);

    $run = AutomationEngine::run($rule, $order);

    expect($run->status)->toBe(AutomationRuleRun::STATUS_FAILED)
        ->and(AutomationRuleRun::where('status', AutomationRuleRun::STATUS_RUNNING)->count())->toBe(0)
        ->and(AutomationRuleRun::count())->toBe(1);
});

// -- IMPORTANT 1: automatable wordt nu ook op het uitvoeringspad gehandhaafd --------

it('skips and logs a rule whose action is not automatable (e.g. cancel), and never executes it', function () {
    $executed = false;
    registerSpyAction('cancel', function () use (&$executed) {
        $executed = true;
    }, automatable: false);

    $order = makeAutomationOrder();
    $rule = makeAutomationRule(['actions' => [['key' => 'cancel']]]);

    $run = AutomationEngine::run($rule, $order);

    expect($executed)->toBeFalse()
        ->and($run)->not->toBeNull()
        ->and($run->status)->toBe(AutomationRuleRun::STATUS_FAILED)
        ->and($run->results)->toBe([]);
});

// -- IMPORTANT 1 (re-review): het venster moet vanaf voltooiing meten, niet vanaf de claim --

it('IMPORTANT 1: a run whose row was claimed longer ago than the rerun window but only finished just now does not rerun', function () {
    $order = makeAutomationOrder();
    $rule = makeAutomationRule(['actions' => []]);

    $first = AutomationEngine::run($rule, $order);

    // Simuleert een actie-keten die langer duurde dan het hergebruik-venster:
    // geclaimd (created_at) ruim buiten RERUN_WINDOW_MINUTES, maar zojuist
    // afgerond — finishRun() zet updated_at bij het opslaan altijd op "nu",
    // dus die blijft hier binnen het venster staan.
    AutomationRuleRun::whereKey($first->id)->update([
        'created_at' => now()->subMinutes(AutomationEngine::RERUN_WINDOW_MINUTES + 1),
    ]);
    expect(AutomationRuleRun::find($first->id)->updated_at->diffInSeconds(now()))->toBeLessThan(5);

    // Vóór de fix meet recentlyRan() het venster vanaf created_at (de
    // claim-tijd), die hier al buiten het venster ligt, dus zou dit ten
    // onrechte een tweede run toestaan — precies het duplicate-postage-lek
    // dat het venster moet dichten.
    $second = AutomationEngine::run($rule, $order);

    expect($second)->toBeNull()
        ->and(AutomationRuleRun::count())->toBe(1);
});

// -- IMPORTANT 2: een stale 'running'-rij wordt niet enkel genegeerd, maar ook opgeruimd -----

it('IMPORTANT 2: a stale running row is marked failed (timed out) when the guard passes it, and the new run proceeds', function () {
    $order = makeAutomationOrder();
    $rule = makeAutomationRule(['actions' => []]);

    $stale = AutomationRuleRun::create([
        'rule_id' => $rule->id,
        'site_id' => $rule->site_id,
        'subject_type' => $order->getMorphClass(),
        'subject_id' => $order->getKey(),
        'trigger' => $rule->trigger,
        'status' => AutomationRuleRun::STATUS_RUNNING,
        'results' => [],
        'error' => null,
    ]);
    $stale->forceFill([
        'created_at' => now()->subMinutes(AutomationEngine::STALE_RUNNING_MINUTES + 1),
    ])->save();

    $run = AutomationEngine::run($rule, $order);

    expect($run)->not->toBeNull()
        ->and($run->id)->not->toBe($stale->id)
        ->and($run->status)->toBe(AutomationRuleRun::STATUS_SUCCESS)
        ->and(AutomationRuleRun::count())->toBe(2);

    $stale->refresh();
    expect($stale->status)->toBe(AutomationRuleRun::STATUS_FAILED)
        ->and($stale->error)->not->toBeNull()
        ->and($stale->error)->toContain((string) AutomationEngine::STALE_RUNNING_MINUTES);
});

// -- MINOR: resultaten die al binnen waren mogen niet verdwijnen bij een throw die runActions() zelf verlaat --

it('MINOR: preserves the results already collected when something escapes runActions() outside the per-action catch', function () {
    // Een registry-dubbelganger die de eerste lookup per key (gebruikt door
    // validationError()) gewoon doorlaat, maar de tweede lookup (gebruikt
    // door runActions() tijdens de echte uitvoering) voor 'boom_action' laat
    // ontsnappen — een staaf voor een onverwachte registry/DB-storing tussen
    // validatie en uitvoering, buiten de per-actie try/catch. Delegeert al
    // het overige (automationTrigger(), registerOrderActions()) naar de
    // echte registry, zodat de al-geboote trigger-registraties (bv.
    // 'order.paid') behouden blijven — enkel orderAction() wordt bespioneerd.
    $realRegistry = app(MobileApiRegistry::class);
    $registry = new class ($realRegistry) extends MobileApiRegistry {
        private array $callCounts = [];

        public function __construct(private MobileApiRegistry $delegate)
        {
        }

        public function registerOrderActions(array $actions): void
        {
            $this->delegate->registerOrderActions($actions);
        }

        public function automationTrigger(string $key): ?array
        {
            return $this->delegate->automationTrigger($key);
        }

        public function orderAction(string $key): ?array
        {
            $this->callCounts[$key] = ($this->callCounts[$key] ?? 0) + 1;

            if ($key === 'boom_action' && $this->callCounts[$key] >= 2) {
                throw new RuntimeException('registry lookup kapot');
            }

            return $this->delegate->orderAction($key);
        }
    };
    app()->instance(MobileApiRegistry::class, $registry);

    registerSpyAction('ok_action', function () {});
    registerSpyAction('boom_action', function () {});

    $order = makeAutomationOrder();
    $rule = makeAutomationRule(['actions' => [['key' => 'ok_action'], ['key' => 'boom_action']]]);

    $run = AutomationEngine::run($rule, $order);

    expect($run->status)->toBe(AutomationRuleRun::STATUS_FAILED)
        ->and($run->error)->toBe('registry lookup kapot')
        ->and($run->results)->toBe([
            ['key' => 'ok_action', 'ok' => true, 'message' => null],
        ]);
});

// -- MINOR: logruis — een kapotte regel logt hoogstens één rij per hergebruik-venster --------

it('MINOR: an invalid rule (unknown action key) logs once within the rerun window, not once per trigger', function () {
    $order = makeAutomationOrder();
    $rule = makeAutomationRule(['actions' => [['key' => 'does-not-exist']]]);

    $first = AutomationEngine::run($rule, $order);
    $second = AutomationEngine::run($rule, $order);
    $third = AutomationEngine::run($rule, $order);

    expect($first)->not->toBeNull()
        ->and($first->status)->toBe(AutomationRuleRun::STATUS_FAILED)
        ->and($second)->toBeNull()
        ->and($third)->toBeNull()
        ->and(AutomationRuleRun::count())->toBe(1);
});
