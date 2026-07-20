<?php

declare(strict_types=1);

namespace Dashed\DashedEcommerceCore\Support\Automation;

use Dashed\DashedEcommerceCore\Models\Order;

/**
 * De ENE plek voor de verzendlabel-providerkeuze: welke provider (Veloyd,
 * dan MyParcel) is geconfigureerd voor de site van een order, en de
 * aanroep-met-fallback die daadwerkelijk een label aanmaakt (kost portokosten
 * bij de vervoerder — dit bepaalt wie er betaald wordt).
 *
 * Gedeeld door `OrderController::attemptCreateLabel()` (app: single- en
 * bulk-create-label-endpoints) en `MobileOrderActions`' `create_label`-
 * automatiseringshandler. Vóór deze extractie codeerden beide callers
 * onafhankelijk dezelfde precedence + "is dit geconfigureerd"-predicate, met
 * het risico dat ze bij een toekomstige wijziging aan één kant stil
 * uiteenlopen — een automatiseringsregel zou dan een ander label (bij een
 * andere vervoerder) kunnen aanmaken dan een CMS-aanroep voor dezelfde order.
 *
 * Provider-volgorde is vast: Veloyd eerst, dan MyParcel. Gooit een
 * geconfigureerde provider een exception (bv. een tijdelijke API-storing),
 * dan valt de aanroep terug op de volgende geconfigureerde provider — exact
 * zoals het CMS al deed. De losse handler-copy had die fallback niet: een
 * Veloyd-exception liet een automatiseringsregel falen terwijl het CMS voor
 * dezelfde order gewoon via MyParcel was doorgegaan.
 *
 * Haalt de provider-klassen via de container op (i.p.v. kale statische
 * aanroepen): functioneel identiek in productie (geen bindings geregistreerd,
 * dus gewoon `new Veloyd()` / `new MyParcel()`, beide zonder constructor-
 * afhankelijkheden), maar geeft tests een naadloze plek om de externe laag te
 * vervangen door een mock — nodig omdat dit pad écht een label bij de
 * vervoerder aanmaakt.
 */
class LabelCreator
{
    /**
     * Maak één verzendlabel aan via de eerst-beschikbare, geconfigureerde
     * provider. `$provider` forceert een specifieke provider ('veloyd' of
     * 'myparcel'); leeg = automatische keuze (met fallback bij een fout).
     *
     * @param  array<string, mixed>  $overrides
     * @return array{ok: bool, provider: ?string, message: string}
     */
    public static function attempt(Order $model, string $provider = '', array $overrides = []): array
    {
        $errors = [];

        if (($provider === '' || $provider === 'veloyd') && self::veloydConfigured($model)) {
            try {
                app(\Dashed\DashedEcommerceVeloyd\Classes\Veloyd::class)->createLabelForOrder($model, $overrides);

                return ['ok' => true, 'provider' => 'veloyd', 'message' => 'Verzendlabel aangemaakt via Veloyd.'];
            } catch (\Throwable $e) {
                report($e);
                $errors[] = 'Veloyd: ' . $e->getMessage();
            }
        }

        if (($provider === '' || $provider === 'myparcel') && self::myparcelConfigured($model)) {
            try {
                app(\Dashed\DashedEcommerceMyParcel\Classes\MyParcel::class)->createLabelForOrder($model, $overrides);

                return ['ok' => true, 'provider' => 'myparcel', 'message' => 'Verzendlabel aangemaakt via MyParcel.'];
            } catch (\Throwable $e) {
                report($e);
                $errors[] = 'MyParcel: ' . $e->getMessage();
            }
        }

        return [
            'ok' => false,
            'provider' => null,
            'message' => $errors ? implode(' ', $errors) : 'Geen verzendprovider geconfigureerd voor deze site.',
        ];
    }

    /**
     * Is Veloyd voor de site van deze order geconfigureerd?
     *
     * Leest de API-sleutel via de provider-class zelf (die met
     * `disableCache: true` leest), zodat deze check NOOIT op een verouderde
     * settings-cache draait.
     */
    public static function veloydConfigured(Order $model): bool
    {
        return class_exists(\Dashed\DashedEcommerceVeloyd\Classes\Veloyd::class)
            && app(\Dashed\DashedEcommerceVeloyd\Classes\Veloyd::class)->apiKey($model->site_id) !== '';
    }

    /** Idem voor MyParcel; `false` haalt de onbewerkte (niet-base64) sleutel op. */
    public static function myparcelConfigured(Order $model): bool
    {
        return class_exists(\Dashed\DashedEcommerceMyParcel\Classes\MyParcel::class)
            && app(\Dashed\DashedEcommerceMyParcel\Classes\MyParcel::class)->apiKey($model->site_id, false) !== '';
    }
}
