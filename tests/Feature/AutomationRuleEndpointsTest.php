<?php

declare(strict_types=1);

use Dashed\DashedCore\Models\User;
use Dashed\DashedEcommerceCore\Models\Order;
use Dashed\DashedEcommerceCore\Models\AutomationRule;
use Dashed\DashedEcommerceCore\Models\AutomationRuleRun;

/**
 * Task 6: de mobile-api-endpoints waarmee de app automatiseringsregels uitleest,
 * aan/uit zet en het uitvoerlog bekijkt. De regel-inhoud (naam, voorwaarden,
 * acties) bouw je in het CMS — de app mag uitsluitend de schakelaar omzetten.
 */
function makeRule(array $overrides = []): AutomationRule
{
    return AutomationRule::create(array_merge([
        'site_id' => 'site',
        'name' => 'Bij betaling',
        'trigger' => 'order.paid',
        'conditions' => [],
        'actions' => [['key' => 'mark_packed', 'params' => []]],
        'is_active' => true,
    ], $overrides));
}

function actingAsAdmin(): User
{
    $user = User::factory()->create(['role' => 'admin']);
    test()->actingAs($user, 'sanctum');

    return $user;
}

it('lists only rules of the active site, with a label from the trigger registry', function () {
    actingAsAdmin();

    $mine = makeRule(['name' => 'Van deze site']);
    makeRule(['site_id' => 'andere-site', 'name' => 'Van een andere site']);

    $res = $this->getJson('/api/v1/automation-rules', ['X-Site-Id' => 'site']);

    $res->assertOk();

    $data = collect($res->json('data'));

    expect($data)->toHaveCount(1);

    $rule = $data->first();

    expect($rule['id'])->toBe($mine->id)
        ->and($rule['name'])->toBe('Van deze site')
        ->and($rule['trigger'])->toBe('order.paid')
        // uit OrderAutomationTriggers, niet de ruwe key
        ->and($rule['trigger_label'])->toBe('Bestelling betaald')
        ->and($rule['is_active'])->toBeTrue()
        ->and($rule['actions_count'])->toBe(1);
});

it('falls back to the trigger key as label when the trigger is unknown', function () {
    actingAsAdmin();

    makeRule(['trigger' => 'order.does_not_exist']);

    $res = $this->getJson('/api/v1/automation-rules', ['X-Site-Id' => 'site']);

    $res->assertOk();

    expect($res->json('data.0.trigger_label'))->toBe('order.does_not_exist');
});

it('reports last_run_at as the moment of the most recent run', function () {
    actingAsAdmin();

    $rule = makeRule();

    AutomationRuleRun::create([
        'rule_id' => $rule->id,
        'site_id' => 'site',
        'subject_type' => Order::class,
        'subject_id' => 1,
        'trigger' => 'order.paid',
        'status' => AutomationRuleRun::STATUS_SUCCESS,
        'results' => [],
        'error' => null,
    ]);

    $res = $this->getJson('/api/v1/automation-rules', ['X-Site-Id' => 'site']);

    $res->assertOk();
    expect($res->json('data.0.last_run_at'))->not->toBeNull();
});

it('reports last_run_at as null for a rule that never ran', function () {
    actingAsAdmin();
    makeRule();

    $res = $this->getJson('/api/v1/automation-rules', ['X-Site-Id' => 'site']);

    $res->assertOk();
    expect($res->json('data.0.last_run_at'))->toBeNull();
});

it('switches a rule off and wraps the updated rule in a data key', function () {
    actingAsAdmin();

    $rule = makeRule(['is_active' => true]);

    $res = $this->putJson("/api/v1/automation-rules/{$rule->id}", [
        'is_active' => false,
    ], ['X-Site-Id' => 'site']);

    $res->assertOk();

    expect($res->json('data.id'))->toBe($rule->id)
        ->and($res->json('data.is_active'))->toBeFalse()
        ->and($rule->fresh()->is_active)->toBeFalse();
});

/**
 * De kern van de whitelist: dashed-core zet een globale Model::unguard(), dus
 * `$fillable` beschermt hier niets. Alleen `is_active` mag door.
 */
it('ignores every field other than is_active on update', function () {
    actingAsAdmin();

    $rule = makeRule([
        'name' => 'Originele naam',
        'trigger' => 'order.paid',
        'conditions' => [['field' => 'total', 'operator' => '>', 'value' => 100]],
        'actions' => [['key' => 'mark_packed', 'params' => []]],
        'is_active' => true,
    ]);

    $res = $this->putJson("/api/v1/automation-rules/{$rule->id}", [
        'is_active' => false,
        'name' => 'Gekaapte naam',
        'trigger' => 'order.cancelled',
        'conditions' => [],
        'actions' => [['key' => 'cancel', 'params' => []]],
        'site_id' => 'andere-site',
        'id' => 99999,
    ], ['X-Site-Id' => 'site']);

    $res->assertOk();

    $fresh = $rule->fresh();

    // Alleen de schakelaar is om.
    expect($fresh->is_active)->toBeFalse()
        // Al het andere staat er nog precies zo bij.
        ->and($fresh->name)->toBe('Originele naam')
        ->and($fresh->trigger)->toBe('order.paid')
        ->and($fresh->conditions)->toBe([['field' => 'total', 'operator' => '>', 'value' => 100]])
        ->and($fresh->actions)->toBe([['key' => 'mark_packed', 'params' => []]])
        ->and($fresh->site_id)->toBe('site')
        ->and($fresh->id)->toBe($rule->id);
});

it('refuses to update a rule of another site', function () {
    actingAsAdmin();

    $rule = makeRule(['site_id' => 'andere-site']);

    $this->putJson("/api/v1/automation-rules/{$rule->id}", [
        'is_active' => false,
    ], ['X-Site-Id' => 'site'])->assertNotFound();

    expect($rule->fresh()->is_active)->toBeTrue();
});

it('requires is_active to be present and boolean', function () {
    actingAsAdmin();

    $rule = makeRule();

    $this->putJson("/api/v1/automation-rules/{$rule->id}", [
        'name' => 'Alleen een naam',
    ], ['X-Site-Id' => 'site'])->assertStatus(422);

    $this->putJson("/api/v1/automation-rules/{$rule->id}", [
        'is_active' => 'misschien',
    ], ['X-Site-Id' => 'site'])->assertStatus(422);
});

it('lists runs newest first, paginated, with rule name and subject', function () {
    actingAsAdmin();

    $rule = makeRule(['name' => 'Bij betaling']);
    $order = Order::create(['email' => 'klant@example.com', 'status' => 'paid', 'site_id' => 'site']);

    $older = AutomationRuleRun::create([
        'rule_id' => $rule->id,
        'site_id' => 'site',
        'subject_type' => Order::class,
        'subject_id' => $order->id,
        'trigger' => 'order.paid',
        'status' => AutomationRuleRun::STATUS_SUCCESS,
        'results' => ['mark_packed' => 'ok'],
        'error' => null,
    ]);
    $older->forceFill(['created_at' => now()->subHour()])->save();

    $newer = AutomationRuleRun::create([
        'rule_id' => $rule->id,
        'site_id' => 'site',
        'subject_type' => Order::class,
        'subject_id' => $order->id,
        'trigger' => 'order.paid',
        'status' => AutomationRuleRun::STATUS_FAILED,
        'results' => [],
        'error' => 'Het ging mis',
    ]);

    $res = $this->getJson('/api/v1/automation-rule-runs', ['X-Site-Id' => 'site']);

    $res->assertOk();

    $data = collect($res->json('data'));

    expect($data)->toHaveCount(2)
        // nieuwste eerst
        ->and($data->first()['id'])->toBe($newer->id)
        ->and($data->last()['id'])->toBe($older->id);

    $first = $data->first();

    expect($first['rule_id'])->toBe($rule->id)
        ->and($first['rule_name'])->toBe('Bij betaling')
        ->and($first['trigger'])->toBe('order.paid')
        ->and($first['status'])->toBe('failed')
        ->and($first['error'])->toBe('Het ging mis')
        ->and($first['results'])->toBe([])
        ->and($first['created_at'])->not->toBeNull()
        ->and($first['subject']['type'])->toBe('order')
        ->and($first['subject']['id'])->toBe($order->id)
        ->and($first['subject']['label'])->toBeString()->not->toBeEmpty();

    // gepagineerd → meta aanwezig
    expect($res->json('meta.per_page'))->not->toBeNull();
});

/**
 * 'running' is een lopende claim, geen eindstatus. De app moet die kunnen
 * onderscheiden van success/failed, dus hij moet ongewijzigd door het contract.
 */
it('exposes the running status verbatim in the run log', function () {
    actingAsAdmin();

    $rule = makeRule();
    $order = Order::create(['email' => 'klant@example.com', 'status' => 'paid', 'site_id' => 'site']);

    AutomationRuleRun::create([
        'rule_id' => $rule->id,
        'site_id' => 'site',
        'subject_type' => Order::class,
        'subject_id' => $order->id,
        'trigger' => 'order.paid',
        'status' => AutomationRuleRun::STATUS_RUNNING,
        'results' => [],
        'error' => null,
    ]);

    $res = $this->getJson('/api/v1/automation-rule-runs', ['X-Site-Id' => 'site']);

    $res->assertOk();

    expect($res->json('data.0.status'))->toBe('running')
        ->and($res->json('data.0.error'))->toBeNull();
});

it('filters runs on rule_id', function () {
    actingAsAdmin();

    $ruleA = makeRule(['name' => 'Regel A']);
    $ruleB = makeRule(['name' => 'Regel B']);
    $order = Order::create(['email' => 'klant@example.com', 'status' => 'paid', 'site_id' => 'site']);

    foreach ([$ruleA, $ruleB] as $rule) {
        AutomationRuleRun::create([
            'rule_id' => $rule->id,
            'site_id' => 'site',
            'subject_type' => Order::class,
            'subject_id' => $order->id,
            'trigger' => 'order.paid',
            'status' => AutomationRuleRun::STATUS_SUCCESS,
            'results' => [],
            'error' => null,
        ]);
    }

    $res = $this->getJson("/api/v1/automation-rule-runs?rule_id={$ruleA->id}", ['X-Site-Id' => 'site']);

    $res->assertOk();

    $data = collect($res->json('data'));

    expect($data)->toHaveCount(1)
        ->and($data->first()['rule_id'])->toBe($ruleA->id)
        ->and($data->first()['rule_name'])->toBe('Regel A');
});

it('only lists runs of the active site', function () {
    actingAsAdmin();

    $rule = makeRule();
    $order = Order::create(['email' => 'klant@example.com', 'status' => 'paid', 'site_id' => 'site']);

    AutomationRuleRun::create([
        'rule_id' => $rule->id,
        'site_id' => 'andere-site',
        'subject_type' => Order::class,
        'subject_id' => $order->id,
        'trigger' => 'order.paid',
        'status' => AutomationRuleRun::STATUS_SUCCESS,
        'results' => [],
        'error' => null,
    ]);

    $res = $this->getJson('/api/v1/automation-rule-runs', ['X-Site-Id' => 'site']);

    $res->assertOk();
    expect($res->json('data'))->toBeEmpty();
});

it('gates reading behind orders.read and the switch behind orders.write', function () {
    // Een niet-geprivilegieerde gebruiker (geen rollen) heeft geen orders.*.
    $this->actingAs(User::factory()->create(['role' => 'customer']), 'sanctum');

    $rule = makeRule(['is_active' => true]);

    $this->getJson('/api/v1/automation-rules', ['X-Site-Id' => 'site'])->assertStatus(403);
    $this->getJson('/api/v1/automation-rule-runs', ['X-Site-Id' => 'site'])->assertStatus(403);
    $this->putJson("/api/v1/automation-rules/{$rule->id}", [
        'is_active' => false,
    ], ['X-Site-Id' => 'site'])->assertStatus(403);

    expect($rule->fresh()->is_active)->toBeTrue();
});

it('requires authentication for all three endpoints', function () {
    $this->getJson('/api/v1/automation-rules', ['X-Site-Id' => 'site'])->assertUnauthorized();
    $this->putJson('/api/v1/automation-rules/1', ['is_active' => false], ['X-Site-Id' => 'site'])->assertUnauthorized();
    $this->getJson('/api/v1/automation-rule-runs', ['X-Site-Id' => 'site'])->assertUnauthorized();
});
