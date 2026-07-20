<?php

declare(strict_types=1);

namespace Dashed\DashedEcommerceCore\Support;

use Dashed\DashedEcommerceCore\Classes\Orders;
use Dashed\DashedEcommerceCore\Enums\PrintJobType;
use Dashed\DashedEcommerceCore\Enums\PrinterType;
use Dashed\DashedEcommerceCore\Enums\PrintJobStatus;
use Dashed\DashedEcommerceCore\Models\Order;
use Dashed\DashedEcommerceCore\Models\OrderLog;
use Dashed\DashedEcommerceCore\Models\PrintJob;
use Dashed\DashedEcommerceCore\Models\Printer;
use Dashed\DashedMobileApi\MobileApiRegistry;
use Dashed\DashedEcommerceCore\Filament\Resources\OrderResource\Actions\SendPaymentLinkAction;
use Dashed\DashedEcommerceCore\Filament\Resources\OrderResource\Actions\RegisterManualPaymentAction;

/**
 * Registreert de core order-acties van de Filament ViewOrder-pagina als
 * mobiele order-acties, zodat de app ze dynamisch kan tonen en uitvoeren.
 * Handlers hergebruiken de bestaande Order-methodes/services, zodat het gedrag
 * exact gelijk is aan het CMS.
 */
class MobileOrderActions
{
    public static function register(MobileApiRegistry $registry): void
    {
        if (! method_exists($registry, 'registerOrderActions')) {
            return;
        }

        $registry->registerOrderActions([
            [
                'key' => 'send_confirmation',
                'label' => 'Stuur e-mailbevestiging',
                'group' => 'Communicatie',
                'icon' => 'mail-outline',
                // Met een order in scope (automatiseringsregel) kan het e-mailadres
                // uit de order zelf worden opgelost, ook al is dit veld niet
                // 'sequenceable' (dat vereist een waarde zónder order).
                'automatable' => true,
                'fields' => [
                    ['name' => 'email', 'label' => 'E-mailadres', 'type' => 'email', 'required' => true, 'default' => fn (Order $o) => $o->email],
                ],
                'visible' => fn (Order $o) => true,
                'handle' => function (Order $o, array $data): void {
                    Orders::sendNotification($o, $data['email'] ?? $o->email, auth()->user());
                },
            ],
            [
                'key' => 'track_and_trace',
                'label' => 'Track & trace toevoegen',
                'group' => 'Verzending',
                'icon' => 'navigate-outline',
                // De track & trace-code is een externe, niet-voorspelbare waarde
                // (komt van de vervoerder) — een automatiseringsregel kan 'm niet
                // van tevoren weten, dus niet geschikt om onbemand te draaien.
                'automatable' => false,
                'fields' => [
                    ['name' => 'delivery_company', 'label' => 'Vervoersbedrijf', 'type' => 'text', 'required' => false],
                    ['name' => 'code', 'label' => 'Track & trace code', 'type' => 'text', 'required' => true],
                    ['name' => 'link', 'label' => 'Link', 'type' => 'text', 'required' => false],
                ],
                'visible' => fn (Order $o) => true,
                'handle' => function (Order $o, array $data): void {
                    $o->trackAndTraces()->create([
                        'supplier' => 'Handmatig',
                        'delivery_company' => $data['delivery_company'] ?? null,
                        'code' => $data['code'] ?? '',
                        'url' => $data['link'] ?? null,
                    ]);
                    OrderLog::createLog(orderId: $o->id, tag: 'order.track-and-trace-added', note: 'Track & trace toegevoegd via de app.');
                },
            ],
            [
                'key' => 'retour_status',
                'label' => 'Verander retourstatus',
                'group' => 'Status',
                'icon' => 'refresh-outline',
                'automatable' => false,
                'fields' => [
                    ['name' => 'retourStatus', 'label' => 'Retourstatus', 'type' => 'select', 'options' => Orders::getReturnStatusses(), 'required' => true, 'default' => fn (Order $o) => $o->retour_status],
                ],
                'visible' => fn (Order $o) => true,
                'handle' => function (Order $o, array $data): void {
                    $o->retour_status = $data['retourStatus'];
                    $o->save();
                    OrderLog::createLog(orderId: $o->id, tag: 'order.retour-status-changed', note: 'Retourstatus gewijzigd via de app.');
                },
            ],
            [
                'key' => 'cancel',
                'label' => 'Annuleer bestelling',
                'group' => 'Status',
                'icon' => 'close-circle-outline',
                'destructive' => true,
                'confirm' => 'Weet je zeker dat je deze bestelling wilt annuleren?',
                // Onomkeerbaar — nooit onbemand.
                'automatable' => false,
                'fields' => [
                    ['name' => 'send_email', 'label' => 'Klant e-mailen over annulering', 'type' => 'checkbox', 'default' => false],
                ],
                'visible' => fn (Order $o) => $o->status !== 'cancelled',
                'handle' => function (Order $o, array $data): void {
                    $o->changeStatus('cancelled', (bool) ($data['send_email'] ?? false));
                },
            ],
            [
                'key' => 'send_to_fulfillment',
                'label' => 'Stuur naar fulfillment-partijen',
                'group' => 'Verzending',
                'icon' => 'cube-outline',
                'confirm' => 'Alle gekoppelde producten naar de fulfillment-partijen sturen?',
                'automatable' => true,
                'visible' => fn (Order $o) => $o->orderProducts()->whereNotNull('fulfillment_provider')->exists(),
                'handle' => function (Order $o, array $data): void {
                    foreach ($o->orderProducts()->whereNotNull('fulfillment_provider')->get() as $orderProduct) {
                        $orderProduct->send_to_fulfiller = 1;
                        $orderProduct->save();
                    }
                    OrderLog::createLog(orderId: $o->id, tag: 'order.sent-to-fulfillment', note: 'Naar fulfillment-partijen gestuurd via de app.');
                },
            ],
            [
                'key' => 'regenerate_invoice',
                'label' => 'Factuur regenereren',
                'group' => 'Documenten',
                'icon' => 'document-text-outline',
                'confirm' => 'De factuur opnieuw genereren?',
                'automatable' => true,
                'visible' => fn (Order $o) => true,
                'handle' => function (Order $o, array $data): void {
                    $o->createNormalInvoice();
                },
            ],
            [
                'key' => 'manual_payment',
                'label' => 'Handmatige betaling registreren',
                'group' => 'Betaling',
                'icon' => 'cash-outline',
                // Registreert geld dat iemand echt heeft ontvangen — nooit onbemand.
                'automatable' => false,
                'fields' => [
                    ['name' => 'amount', 'label' => 'Bedrag (€)', 'type' => 'number', 'required' => true, 'default' => fn (Order $o) => round((float) $o->outstandingAmount(), 2)],
                    ['name' => 'payment_method', 'label' => 'Betaalmethode', 'type' => 'select', 'options' => ['cash' => 'Contant', 'pin' => 'Pin', 'bank_transfer' => 'Overboeking'], 'required' => true, 'default' => 'cash'],
                    ['name' => 'note', 'label' => 'Opmerking', 'type' => 'textarea', 'required' => false],
                ],
                'visible' => fn (Order $o) => $o->outstandingAmount() > 0,
                'handle' => function (Order $o, array $data): void {
                    (new RegisterManualPaymentAction())->handle($o, $data);
                },
            ],
            [
                'key' => 'payment_link',
                'label' => 'Betaallink sturen',
                'group' => 'Betaling',
                'icon' => 'link-outline',
                'automatable' => false,
                'fields' => [
                    ['name' => 'amount', 'label' => 'Bedrag (€)', 'type' => 'number', 'required' => true, 'default' => fn (Order $o) => round((float) $o->outstandingAmount(), 2)],
                    ['name' => 'email', 'label' => 'E-mailadres', 'type' => 'email', 'required' => true, 'default' => fn (Order $o) => $o->email],
                ],
                'visible' => fn (Order $o) => $o->outstandingAmount() > 0,
                'handle' => function (Order $o, array $data): void {
                    (new SendPaymentLinkAction())->handle($o, $data);
                },
            ],
            // Onderstaande zeven zijn step-definities voor de configureerbare
            // "Afronden"-knop. Ze worden normaliter uitgevoerd door de app zelf
            // (via de bestaande catalog-endpoints). Ze blijven verborgen voor de
            // losse per-order actielijst (`visible => fn () => false`) en worden
            // alleen gelezen door het catalog-endpoint dat 'visible' negeert.
            //
            // Elke stap krijgt nu ook een `handle`-closure: een automatiseringsregel
            // draait server-side (geen app in de loop) en heeft de order al in
            // scope, dus deze acties zijn `automatable`. De closures hergebruiken
            // dezelfde onderliggende Order-methodes/services als het CMS
            // (OrderController::packed/changeFulfillment/markAsPaid/print en de
            // label-providers), niet de controller zelf.
            [
                'key' => 'mark_packed',
                'label' => 'Markeer als ingepakt',
                'group' => 'Verzending',
                'icon' => 'archive-outline',
                'sequenceable' => true,
                'automatable' => true,
                'visible' => fn () => false,
                'handle' => function (Order $o, array $data): void {
                    // Zelfde kern als OrderController::packed() (packed=true-pad):
                    // packed_at zetten, fulfilment-status meesynchroniseren, loggen.
                    $o->forceFill(['packed_at' => now()])->save();
                    $o->changeFulfillmentStatus('packed');
                    OrderLog::createLog(orderId: $o->id, tag: 'order.packed');
                },
            ],
            [
                'key' => 'create_label',
                'label' => 'Verzendlabel aanmaken',
                'group' => 'Verzending',
                'icon' => 'pricetag-outline',
                'sequenceable' => true,
                'automatable' => true,
                'visible' => fn () => false,
                'handle' => function (Order $o, array $data): void {
                    self::createLabel($o);
                },
            ],
            [
                'key' => 'print_label',
                'label' => 'Verzendlabel printen',
                'group' => 'Verzending',
                'icon' => 'print-outline',
                'sequenceable' => true,
                'automatable' => true,
                'visible' => fn () => false,
                'handle' => function (Order $o, array $data): void {
                    self::queuePrintJob($o, PrintJobType::ShippingLabel);
                },
            ],
            [
                'key' => 'print_packing_slip',
                'label' => 'Pakbon printen',
                'group' => 'Documenten',
                'icon' => 'document-outline',
                'sequenceable' => true,
                'automatable' => true,
                'visible' => fn () => false,
                'handle' => function (Order $o, array $data): void {
                    self::queuePrintJob($o, PrintJobType::PackingSlip);
                },
            ],
            [
                'key' => 'print_invoice',
                'label' => 'Factuur printen',
                'group' => 'Documenten',
                'icon' => 'receipt-outline',
                'sequenceable' => true,
                'automatable' => true,
                'visible' => fn () => false,
                'handle' => function (Order $o, array $data): void {
                    self::queuePrintJob($o, PrintJobType::Invoice);
                },
            ],
            [
                'key' => 'set_fulfillment_status',
                'label' => 'Fulfilment-status wijzigen',
                'group' => 'Status',
                'icon' => 'flag-outline',
                'sequenceable' => true,
                'automatable' => true,
                'fields' => [
                    ['name' => 'status', 'label' => 'Fulfilment-status', 'type' => 'select', 'required' => true, 'options' => Orders::getFulfillmentStatusses()],
                ],
                'visible' => fn () => false,
                'handle' => function (Order $o, array $data): void {
                    // Zelfde als OrderController::changeFulfillment().
                    $o->changeFulfillmentStatus($data['status']);
                },
            ],
            [
                'key' => 'mark_paid',
                'label' => 'Markeer als betaald',
                'group' => 'Betaling',
                'icon' => 'checkmark-circle-outline',
                'sequenceable' => true,
                'automatable' => true,
                'visible' => fn () => false,
                'handle' => function (Order $o, array $data): void {
                    // Zelfde als OrderController::markAsPaid().
                    $o->markAsPaid();
                },
            ],
            [
                'key' => 'notify_app',
                'label' => 'Stuur app-melding',
                'group' => 'Communicatie',
                'icon' => 'notifications-outline',
                'automatable' => true,
                'fields' => [
                    ['name' => 'title', 'label' => 'Titel', 'type' => 'text', 'required' => true],
                    ['name' => 'body', 'label' => 'Bericht', 'type' => 'text', 'required' => false],
                    [
                        'name' => 'ability',
                        'label' => 'Recht (wie ontvangt de melding)',
                        'type' => 'select',
                        'required' => false,
                        'options' => array_combine($registry->abilities(), $registry->abilities()),
                        'default' => 'orders.read',
                    ],
                ],
                'visible' => fn (Order $o) => true,
                'handle' => function (Order $o, array $data): void {
                    if (! class_exists(\Dashed\DashedMobileApi\Support\NotificationCenter::class)) {
                        return;
                    }

                    app(\Dashed\DashedMobileApi\Support\NotificationCenter::class)->push()
                        ->title((string) ($data['title'] ?? ''))
                        ->body((string) ($data['body'] ?? ''))
                        ->route("/order/{$o->id}")
                        ->toAbility((string) ($data['ability'] ?? 'orders.read'))
                        ->send();
                },
            ],
        ]);
    }

    /**
     * Maak een verzendlabel aan via de eerst-beschikbare geconfigureerde
     * provider (Veloyd, dan MyParcel). Delegeert naar `LabelCreator`, dezelfde
     * klasse die `OrderController::attemptCreateLabel()` gebruikt — zo kiest
     * een automatiseringsregel altijd exact dezelfde vervoerder als een
     * CMS-/app-aanroep, en valt (net als het CMS) terug op de volgende
     * geconfigureerde provider wanneer de eerste een exception gooit.
     */
    private static function createLabel(Order $o): void
    {
        \Dashed\DashedEcommerceCore\Support\Automation\LabelCreator::attempt($o);
    }

    /**
     * Zet een printjob van dit type in de wachtrij — dezelfde print-queue-laag
     * (`PrintJob`) als OrderController::print()/printDocuments() gebruiken.
     * Geen nieuwe job als er al een onafgeronde job van dit type voor de order
     * in de wachtrij staat (zelfde dedup als printDocuments()).
     *
     * Weigert (net als OrderController::print()/printDocuments()) wanneer er
     * geen actieve printer van het bijbehorende type geconfigureerd is: zonder
     * die check zou een automatiseringsregel een `PrintJob` wegzetten die door
     * geen enkele printer-worker geclaimd kan worden en voor altijd op
     * `Pending` blijft staan. De exception geeft de regel-run-log een heldere
     * reden i.p.v. stilzwijgend een onclaimbare job aan te maken.
     */
    private static function queuePrintJob(Order $o, PrintJobType $type): void
    {
        $printerType = self::printerTypeFor($type);

        if (! Printer::hasActiveForType($printerType)) {
            throw new \RuntimeException("Geen actieve {$type->label()}-printer geconfigureerd.");
        }

        $exists = PrintJob::where('order_id', $o->id)
            ->where('type', $type->value)
            ->whereIn('status', [
                PrintJobStatus::Pending->value,
                PrintJobStatus::Claimed->value,
                PrintJobStatus::Printing->value,
            ])
            ->exists();

        if ($exists) {
            return;
        }

        PrintJob::create([
            'type' => $type,
            'order_id' => $o->id,
            'status' => PrintJobStatus::Pending,
        ]);
    }

    /**
     * Welk printer-type hoort bij dit printjob-type — zelfde mapping als
     * OrderController::print(): facturen én pakbonnen gaan naar dezelfde A4-
     * document-printer (PrinterType::PackingSlip/Both), alleen verzendlabels
     * hebben een eigen printer-type.
     */
    private static function printerTypeFor(PrintJobType $type): PrinterType
    {
        return $type === PrintJobType::ShippingLabel
            ? PrinterType::ShippingLabel
            : PrinterType::PackingSlip;
    }
}
