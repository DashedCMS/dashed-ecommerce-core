<?php

declare(strict_types=1);

namespace Dashed\DashedEcommerceCore\Support\Automation;

use Throwable;
use Illuminate\Cache\NoLock;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Contracts\Cache\Lock as CacheLock;
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
 *     ontstaan (bv. een actie die zelf een trigger-event afvuurt). Bij een
 *     geneste run() (bv. de actie roept run() zelf opnieuw aan) wordt de
 *     vorige waarde bewaard en hersteld, zodat de geneste aanroep de
 *     buitenste niet voor de rest van zíjn acties doof maakt.
 *  2. Een geclaimde rij per (regel, onderwerp) in
 *     dashed__automation_rule_runs. Dit is de laag die ook cross-process
 *     werkt: de queue-worker die een actie als create_label uitvoert kan
 *     zelf werk in een wachtrij zetten dat pas later, in een ánder
 *     worker-proces, een trigger-event afvuurt — daar staat de statische
 *     vlag niet meer. Cruciaal: de rij wordt geclaimd (status 'running')
 *     vóórdat er ook maar één actie draait, niet pas erna — anders bestaat
 *     de rem niet tijdens exact het venster waarin hij het hardst nodig is
 *     (bv. terwijl create_label's vervoerder-HTTP-call in flight staat).
 *     Check-en-claim gebeurt atomisch via een cache-lock, zodat twee
 *     workers die tegelijk de check doen niet allebei kunnen winnen. Een
 *     door laag 2 overgeslagen run wordt niet gelogd (het is een rem, geen
 *     gebeurtenis) — een geclaimde ('running') rij telt wél als gebeurtenis
 *     en wordt dus wel gelogd.
 *
 * BELANGRIJK — laag 2 leunt volledig op de cache-lock uit `Cache::lock()`
 * voor haar atomiciteit (zie claim()/claimInvalid()/withClaimLock()). Met
 * een cache-driver zonder échte (gedistribueerde) lock-implementatie —
 * `CACHE_STORE=null` (→ `Illuminate\Cache\NoLock`, accepteert `acquire()`
 * altijd) of de `array`-driver (alleen in-process) — geldt de garantie
 * "twee workers kunnen nooit allebei claimen" dus niet. Er is geen
 * DB-unique-constraint als backstop. Productie draait op een echte
 * gedistribueerde store (bv. redis/memcached/database); voor een `NoLock`
 * logt withClaimLock() eenmalig per proces een waarschuwing (zie
 * warnIfNoopLock()) zodat dit niet stilzwijgend misgaat.
 */
class AutomationEngine
{
    /**
     * Hoe lang (in minuten) dezelfde regel niet nogmaals voor hetzelfde
     * onderwerp mag draaien nadat hij is afgerond (success/failed) — laag 2
     * van de lus-beveiliging.
     */
    public const RERUN_WINDOW_MINUTES = 5;

    /**
     * Hoe lang (in minuten) een 'running'-rij nog als "in uitvoering" telt
     * voordat hij als stale wordt genegeerd. Gekozen ruim boven de
     * realistische uitvoeringsduur van een actie-keten: create_label's
     * gedeelde Veloyd-HTTP-client timeout ligt met retries op ~60s (zie
     * project-memory "Veloyd HTTP client retry hang"), en een regel kan
     * meerdere van zulke acties na elkaar draaien. 10 minuten geeft
     * ruimschoots (5-10x) marge boven dat worst-case, zonder dat een regel
     * na een dode worker urenlang bevroren blijft.
     *
     * Dit is een harde grens: een actie-keten die langer draait dan dit
     * aantal minuten wordt door de engine als dood beschouwd, ook als hij in
     * werkelijkheid nog gewoon bezig is. De 'running'-rij wordt dan door de
     * eerstvolgende claim-poging op 'failed' (timeout) gezet — zie
     * failStaleRunningRows() — en een nieuwe run mag starten. Een regel mag
     * dus nooit een actie-keten bevatten die redelijkerwijs langer dan dit
     * aantal minuten kan duren.
     */
    public const STALE_RUNNING_MINUTES = 10;

    /**
     * TTL van de claim-lock zelf (seconden). Die hoeft alleen de korte
     * check-en-claim-sectie (één SELECT + één INSERT) atomisch te maken,
     * niet de volledige actie-uitvoering — vandaar kort. Puur een
     * veiligheidsnet mocht release() onverwacht niet lopen.
     */
    private const CLAIM_LOCK_SECONDS = 10;

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
     * resultaat. Geeft normaliter geen exception door aan de aanroeper voor
     * fouten tijdens de actie-uitvoering zelf (elke actie wordt individueel
     * gevangen in runActions(), en een onverwachte throw daarbuiten wordt
     * hieronder alsnog opgevangen) — een falende regel mag de order-flow
     * nooit breken. Dat vangnet dekt echter niet claim()/claimInvalid() en
     * finishRun() zelf: een cache- of DB-storing daarin (bv. de connectie is
     * weg) kan wél alsnog propageren naar de aanroeper. Geeft de aangemaakte
     * AutomationRuleRun terug, of null wanneer laag 2 de run heeft geremd
     * (dan is er bewust niets gelogd).
     */
    public static function run(AutomationRule $rule, Model $subject): ?AutomationRuleRun
    {
        $registry = app(MobileApiRegistry::class);

        // Validatie eerst, vóór de laag-2-claim: een regel met een onbekende
        // trigger/actie of een niet-automatiseerbare actie wordt nooit
        // uitgevoerd, dus hoeft nooit een 'running'-rij te claimen. Het
        // hergebruik-venster wordt hier wél toegepast (via claimInvalid(),
        // net als claim()) — anders logt een kapotte regel op elke trigger
        // opnieuw een 'failed'-rij, onbegrensd.
        $invalidReason = self::validationError($registry, $rule);
        if ($invalidReason !== null) {
            return self::claimInvalid($rule, $subject, $invalidReason);
        }

        $run = self::claim($rule, $subject);
        if ($run === null) {
            return null;
        }

        $actions = collect($rule->actions ?? []);

        $wasSuppressed = self::$suppressed;
        self::$suppressed = true;

        // $results wordt by-reference doorgegeven aan runActions() en dus
        // ook bijgewerkt als er daarbinnen iets ontsnapt vóórdat de methode
        // normaal teruggeeft (zie de docblock bij runActions()). Zo verliezen
        // we bij een onverwachte throw niet het bewijs van acties die al wél
        // liepen — bv. een label dat al gekocht is.
        $results = [];

        try {
            $error = self::runActions($registry, $actions, $subject, $results);
        } catch (Throwable $e) {
            // Zou niet moeten gebeuren (elke actie wordt individueel
            // gevangen in runActions()), maar als er hier toch iets
            // ontsnapt mag de geclaimde rij nooit op 'running' blijven
            // staan tot de stale-bound dat oplost.
            $error = $e->getMessage();
        } finally {
            self::$suppressed = $wasSuppressed;
        }

        return self::finishRun(
            $run,
            $error === null ? AutomationRuleRun::STATUS_SUCCESS : AutomationRuleRun::STATUS_FAILED,
            $results,
            $error,
        );
    }

    /**
     * Onbekende trigger, onbekende actie-key, of een actie die wel bestaat
     * maar niet `automatable => true` is (bv. `cancel`, `payment_link`) —
     * die laatste worden alleen als handmatige Filament-actie getoond, nooit
     * via een regel uitgevoerd. Er is nog geen regel-authoring-UI, dus dit
     * is de laatste verdedigingslinie tegen een (per ongeluk of kwaadwillig)
     * opgeslagen regel met bv. {key: 'cancel'}.
     */
    private static function validationError(MobileApiRegistry $registry, AutomationRule $rule): ?string
    {
        if ($registry->automationTrigger($rule->trigger) === null) {
            return "Onbekende trigger: {$rule->trigger}";
        }

        $actions = collect($rule->actions ?? []);

        foreach ($actions as $action) {
            $key = is_array($action) ? (string) ($action['key'] ?? '') : '';
            $definition = is_array($action) ? $registry->orderAction($key) : null;

            if ($definition === null) {
                return "Onbekende actie: {$key}";
            }

            if (($definition['automatable'] ?? false) !== true) {
                return "Actie niet automatiseerbaar: {$key}";
            }
        }

        return null;
    }

    /**
     * @param  Collection<int, array<string, mixed>>  $actions
     * @param  array<int, array{key: string, ok: bool, message: ?string}>  $results
     *         By-reference accumulator. De aanroeper (run()) leest 'm ook
     *         terug wanneer hier iets ontsnapt dat buiten de per-actie
     *         try/catch hieronder ligt (bv. een registry-lookup die faalt) —
     *         omdat het dezelfde array is, blijft wat er al in stond intact,
     *         in plaats van verloren te gaan zoals bij een lokale variabele.
     * @return ?string
     */
    private static function runActions(MobileApiRegistry $registry, Collection $actions, Model $subject, array &$results): ?string
    {
        $error = null;

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

        return $error;
    }

    /**
     * Claimt atomisch een run voor (regel, onderwerp): check ("draait/liep
     * deze recent al?") en claim (de 'running'-rij aanmaken) gebeuren binnen
     * één cache-lock, zodat twee processen die exact tegelijk binnenkomen
     * niet allebei kunnen winnen. Alleen de winnaar krijgt een rij terug —
     * de rest krijgt null, precies zoals laag 2 dat altijd al deed.
     *
     * Ruimt onderweg ook stale 'running'-rijen op (zie failStaleRunningRows())
     * — dat gebeurt pas ná de recentlyRan()-check, met opzet: die check moet
     * de stale rij nog als "niet-blokkerend maar wel aanwezig" zien, anders
     * zou het zojuist-op-'failed'-gezette (dus vers 'updated_at') resultaat
     * van de opruiming zelf de nieuwe claim binnen het hergebruik-venster
     * blokkeren.
     */
    private static function claim(AutomationRule $rule, Model $subject): ?AutomationRuleRun
    {
        return self::withClaimLock($rule, $subject, function () use ($rule, $subject) {
            if (self::recentlyRan($rule, $subject)) {
                return null;
            }

            self::failStaleRunningRows($rule, $subject);

            return self::logRun($rule, $subject, AutomationRuleRun::STATUS_RUNNING, [], null);
        });
    }

    /**
     * Zelfde claim-atomiciteit als claim(), maar voor een regel die de
     * validatie niet doorstaat: er wordt nooit een 'running'-rij aangemaakt
     * (er draait niets), enkel — hooguit één keer per hergebruik-venster —
     * een 'failed'-rij met de validatiefout. Zonder deze suppressie zou een
     * kapotte regel op élke trigger een nieuwe rij loggen, onbegrensd.
     */
    private static function claimInvalid(AutomationRule $rule, Model $subject, string $invalidReason): ?AutomationRuleRun
    {
        return self::withClaimLock($rule, $subject, function () use ($rule, $subject, $invalidReason) {
            if (self::recentlyRan($rule, $subject)) {
                return null;
            }

            return self::logRun($rule, $subject, AutomationRuleRun::STATUS_FAILED, [], $invalidReason);
        });
    }

    /**
     * Gedeelde atomiciteits-envelop voor claim() en claimInvalid(): pakt de
     * per-(regel, onderwerp) cache-lock en geeft terug wat $body opleverde,
     * of null als $body null teruggaf of de lock niet te pakken was.
     *
     * Bewust non-blocking (Lock::get(), geen block()): de sectie binnen de
     * lock is telkens een enkele SELECT (+ eventueel een UPDATE) + INSERT,
     * dus vrijwel altijd meteen vrij. Lukt het toch niet (iemand anders
     * claimt op exact hetzelfde moment), dan is het resultaat sowieso "een
     * ander proces is hiermee bezig" — net als een geslaagde claim door de
     * ander, dus null teruggeven is hier correct, niet een noodgreep.
     */
    private static function withClaimLock(AutomationRule $rule, Model $subject, callable $body): ?AutomationRuleRun
    {
        $lock = Cache::lock(self::claimLockKey($rule, $subject), self::CLAIM_LOCK_SECONDS);

        self::warnIfNoopLock($lock);

        $claimed = $lock->get($body);

        return $claimed instanceof AutomationRuleRun ? $claimed : null;
    }

    private static bool $warnedNoopLock = false;

    /**
     * Laag 2's atomiciteit staat of valt met een échte gedistribueerde
     * cache-lock (zie de class-docblock). Met `CACHE_STORE=null` geeft
     * `Cache::lock()` een `Illuminate\Cache\NoLock` terug, waarvan
     * `acquire()` altíjd true is — de lock houdt dan niets tegen, zonder dat
     * daar verder enig signaal van is. Waarschuw daarom één keer per
     * PHP-proces (niet per aanroep, om zelf geen logruis toe te voegen)
     * zodat een verkeerd ingestelde `CACHE_STORE` in productie niet
     * stilzwijgend de rem uitschakelt.
     */
    private static function warnIfNoopLock(CacheLock $lock): void
    {
        if (self::$warnedNoopLock || ! $lock instanceof NoLock) {
            return;
        }

        self::$warnedNoopLock = true;

        Log::warning(
            'AutomationEngine: de actieve cache-driver (CACHE_STORE=null) geeft geen '
            . 'echte gedistribueerde lock (Illuminate\Cache\NoLock accepteert acquire() altijd). '
            . 'Laag 2 van de lus-beveiliging (dubbele-uitvoering-rem) is hierdoor niet atomisch '
            . 'gegarandeerd — configureer een echte cache-store (redis/memcached/database) in productie.'
        );
    }

    /**
     * Markeert 'running'-rijen voor (regel, onderwerp) die de stale-bound
     * (STALE_RUNNING_MINUTES) gepasseerd zijn als 'failed' met een
     * time-out-melding, zodat ze niet voor altijd als "in uitvoering" blijven
     * staan (bv. na een gecrashte worker) — anders leest elke UI die runs
     * toont zo'n rij permanent als "bezig". Draait binnen dezelfde
     * claim-lock als de rest van claim(), dus atomisch t.o.v. andere
     * claimers op (regel, onderwerp).
     */
    private static function failStaleRunningRows(AutomationRule $rule, Model $subject): void
    {
        AutomationRuleRun::query()
            ->where('rule_id', $rule->id)
            ->where('subject_type', $subject->getMorphClass())
            ->where('subject_id', $subject->getKey())
            ->where('status', AutomationRuleRun::STATUS_RUNNING)
            ->where('created_at', '<', now()->subMinutes(self::STALE_RUNNING_MINUTES))
            ->update([
                'status' => AutomationRuleRun::STATUS_FAILED,
                'error' => sprintf(
                    'Time-out: deze run bleef langer dan %d minuten op \'running\' staan '
                        . '(stale-grens overschreden, worker vermoedelijk gecrasht).',
                    self::STALE_RUNNING_MINUTES,
                ),
            ]);
    }

    /** Publiek zodat tests dezelfde lock kunnen pakken om de atomiciteit te bewijzen. */
    public static function claimLockKey(AutomationRule $rule, Model $subject): string
    {
        return sprintf(
            'dashed-ecommerce-core:automation-claim:%d:%s:%s',
            $rule->id,
            $subject->getMorphClass(),
            (string) $subject->getKey(),
        );
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

    /** @param  array<int, array{key: string, ok: bool, message: ?string}>  $results */
    private static function finishRun(AutomationRuleRun $run, string $status, array $results, ?string $error): AutomationRuleRun
    {
        $run->forceFill([
            'status' => $status,
            'results' => $results,
            'error' => $error,
        ])->save();

        return $run;
    }

    /**
     * True wanneer er voor (regel, onderwerp) al een run bestaat die nog
     * telt als "bezet":
     *  - een 'running'-rij die niet stale is (nog binnen STALE_RUNNING_MINUTES,
     *    gemeten vanaf de claim-timestamp `created_at` — dat ís het moment
     *    waarop deze laag moet beginnen tellen: de rij wordt geclaimd vóórdat
     *    er een actie draait) — dekt de volledige duur van de
     *    actie-uitvoering, inclusief de vervoerder-HTTP-call van
     *    create_label;
     *  - een afgeronde ('success'/'failed') rij binnen RERUN_WINDOW_MINUTES,
     *    gemeten vanaf `updated_at` — dat is het moment waarop finishRun()
     *    'm afrondde, dus het venster gaat pas ná voltooiing lopen (zie de
     *    class-docblock: "nadat hij is afgerond"). Meten vanaf `created_at`
     *    (de claim-tijd) zou het venster tijdens de actie-uitvoering zélf
     *    laten verstrijken — bij een actie-keten langer dan
     *    RERUN_WINDOW_MINUTES (create_label alleen al kan ~60s duren) geeft
     *    dat nul cooldown ná afloop, precies het duplicate-postage-scenario
     *    dat deze laag moet voorkomen.
     */
    private static function recentlyRan(AutomationRule $rule, Model $subject): bool
    {
        return AutomationRuleRun::query()
            ->where('rule_id', $rule->id)
            ->where('subject_type', $subject->getMorphClass())
            ->where('subject_id', $subject->getKey())
            ->where(function ($query) {
                $query
                    ->where(function ($running) {
                        $running
                            ->where('status', AutomationRuleRun::STATUS_RUNNING)
                            ->where('created_at', '>=', now()->subMinutes(self::STALE_RUNNING_MINUTES));
                    })
                    ->orWhere(function ($finished) {
                        $finished
                            ->whereIn('status', [AutomationRuleRun::STATUS_SUCCESS, AutomationRuleRun::STATUS_FAILED])
                            ->where('updated_at', '>=', now()->subMinutes(self::RERUN_WINDOW_MINUTES));
                    });
            })
            ->exists();
    }
}
