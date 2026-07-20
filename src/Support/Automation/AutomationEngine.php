<?php

declare(strict_types=1);

namespace Dashed\DashedEcommerceCore\Support\Automation;

use Throwable;
use Illuminate\Support\Collection;
use Illuminate\Database\Eloquent\Model;
use Dashed\DashedMobileApi\MobileApiRegistry;
use Dashed\DashedEcommerceCore\Models\AutomationRule;
use Dashed\DashedEcommerceCore\Models\AutomationRuleRun;

/**
 * Matcht en voert automatiseringsregels uit. `run()` doet het echte werk
 * (acties op volgorde uitvoeren + één AutomationRuleRun loggen) en is
 * bewust los van de queue testbaar; RunAutomationRuleJob is een dunne
 * wrapper die alleen het dispatchen regelt.
 *
 * Lus-beveiliging, twee lagen (zie run() voor de precieze volgorde):
 *  1. Een statische vlag (`suppressed()`) die staat zolang de acties van
 *     déze aanroep draaien. Dekt alleen events die in hetzelfde PHP-proces
 *     ontstaan (bv. een actie die zelf een trigger-event afvuurt).
 *  2. Een venster-check per (regel, onderwerp) op basis van
 *     dashed__automation_rule_runs. Dit is de laag die ook cross-process
 *     werkt: de queue-worker die een actie als create_label uitvoert kan
 *     zelf werk in een wachtrij zetten dat pas later, in een ánder
 *     worker-proces, een trigger-event afvuurt — daar staat de statische
 *     vlag niet meer. Een door laag 2 overgeslagen run wordt niet gelogd
 *     (het is een rem, geen gebeurtenis).
 */
class AutomationEngine
{
    /**
     * Hoe lang (in minuten) dezelfde regel niet nogmaals voor hetzelfde
     * onderwerp mag draaien — laag 2 van de lus-beveiliging.
     */
    public const RERUN_WINDOW_MINUTES = 5;

    private static bool $suppressed = false;

    public static function suppressed(): bool
    {
        return self::$suppressed;
    }

    /**
     * Actieve regels voor deze trigger op deze site — ongefilterd op
     * voorwaarden. De aanroeper (AutomationTriggerSubscriber) matcht die
     * zelf per regel via ConditionEvaluator::matches().
     *
     * @return Collection<int, AutomationRule>
     */
    public static function rulesFor(string $triggerKey, string $siteId): Collection
    {
        return AutomationRule::query()
            ->active()
            ->forTrigger($triggerKey)
            ->where('site_id', $siteId)
            ->get();
    }

    /**
     * Voert één automatiseringsregel uit voor één onderwerp en logt het
     * resultaat. Geeft nooit een exception door aan de aanroeper: een
     * falende regel mag de order-flow nooit breken. Geeft de aangemaakte
     * AutomationRuleRun terug, of null wanneer laag 2 de run heeft geremd
     * (dan is er bewust niets gelogd).
     */
    public static function run(AutomationRule $rule, Model $subject): ?AutomationRuleRun
    {
        if (self::recentlyRan($rule, $subject)) {
            return null;
        }

        $registry = app(MobileApiRegistry::class);
        $trigger = $registry->automationTrigger($rule->trigger);
        $actions = collect($rule->actions ?? []);
        $unknownAction = $actions->first(function ($action) use ($registry) {
            if (! is_array($action)) {
                return true;
            }

            return $registry->orderAction((string) ($action['key'] ?? '')) === null;
        });

        // Een regel met een onbekende trigger of onbekende actie-key wordt
        // overgeslagen en gelogd, nooit uitgevoerd — bv. na een package-
        // downgrade die een trigger/actie liet vervallen.
        if ($trigger === null || $unknownAction !== null) {
            $reason = $trigger === null
                ? "Onbekende trigger: {$rule->trigger}"
                : 'Onbekende actie: '.(is_array($unknownAction) ? (string) ($unknownAction['key'] ?? '') : '?');

            return self::logRun($rule, $subject, AutomationRuleRun::STATUS_FAILED, [], $reason);
        }

        [$results, $error] = self::runActions($registry, $actions, $subject);

        return self::logRun(
            $rule,
            $subject,
            $error === null ? AutomationRuleRun::STATUS_SUCCESS : AutomationRuleRun::STATUS_FAILED,
            $results,
            $error,
        );
    }

    /**
     * @param  Collection<int, array<string, mixed>>  $actions
     * @return array{0: array<int, array{key: string, ok: bool, message: ?string}>, 1: ?string}
     */
    private static function runActions(MobileApiRegistry $registry, Collection $actions, Model $subject): array
    {
        $results = [];
        $error = null;

        // Laag 1 staat alleen tijdens het daadwerkelijk uitvoeren van de
        // acties — try/finally zodat een exception 'm nooit laat hangen.
        self::$suppressed = true;

        try {
            foreach ($actions as $action) {
                $key = (string) ($action['key'] ?? '');
                $handle = $registry->orderAction($key)['handle'] ?? null;

                try {
                    if (! is_callable($handle)) {
                        throw new \RuntimeException("Actie '{$key}' heeft geen handler.");
                    }

                    $handle($subject, $action['params'] ?? []);
                    $results[] = ['key' => $key, 'ok' => true, 'message' => null];
                } catch (Throwable $e) {
                    $results[] = ['key' => $key, 'ok' => false, 'message' => $e->getMessage()];
                    $error = $e->getMessage();
                    break;
                }
            }
        } finally {
            self::$suppressed = false;
        }

        return [$results, $error];
    }

    /** @param  array<int, array{key: string, ok: bool, message: ?string}>  $results */
    private static function logRun(AutomationRule $rule, Model $subject, string $status, array $results, ?string $error): AutomationRuleRun
    {
        return AutomationRuleRun::create([
            'rule_id' => $rule->id,
            'site_id' => $rule->site_id,
            'subject_type' => $subject->getMorphClass(),
            'subject_id' => $subject->getKey(),
            'trigger' => $rule->trigger,
            'status' => $status,
            'results' => $results,
            'error' => $error,
        ]);
    }

    private static function recentlyRan(AutomationRule $rule, Model $subject): bool
    {
        return AutomationRuleRun::query()
            ->where('rule_id', $rule->id)
            ->where('subject_type', $subject->getMorphClass())
            ->where('subject_id', $subject->getKey())
            ->where('created_at', '>=', now()->subMinutes(self::RERUN_WINDOW_MINUTES))
            ->exists();
    }
}
