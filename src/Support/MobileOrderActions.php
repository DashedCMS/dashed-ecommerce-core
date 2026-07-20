<?php

declare(strict_types=1);

namespace Dashed\DashedEcommerceCore\Support;

use Dashed\DashedEcommerceCore\Classes\Orders;
use Dashed\DashedEcommerceCore\Models\Order;
use Dashed\DashedEcommerceCore\Models\OrderLog;
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
            // "Afronden"-knop. Ze worden uitgevoerd door de app zelf (via de
            // bestaande catalog-endpoints), dus bewust zonder 'handle'. Ze
            // blijven verborgen voor de losse per-order actielijst
            // (`visible => fn () => false`) en worden alleen gelezen door het
            // (latere) catalog-endpoint dat 'visible' negeert.
            [
                'key' => 'mark_packed',
                'label' => 'Markeer als ingepakt',
                'group' => 'Verzending',
                'icon' => 'archive-outline',
                'sequenceable' => true,
                'visible' => fn () => false,
            ],
            [
                'key' => 'create_label',
                'label' => 'Verzendlabel aanmaken',
                'group' => 'Verzending',
                'icon' => 'pricetag-outline',
                'sequenceable' => true,
                'visible' => fn () => false,
            ],
            [
                'key' => 'print_label',
                'label' => 'Verzendlabel printen',
                'group' => 'Verzending',
                'icon' => 'print-outline',
                'sequenceable' => true,
                'visible' => fn () => false,
            ],
            [
                'key' => 'print_packing_slip',
                'label' => 'Pakbon printen',
                'group' => 'Documenten',
                'icon' => 'document-outline',
                'sequenceable' => true,
                'visible' => fn () => false,
            ],
            [
                'key' => 'print_invoice',
                'label' => 'Factuur printen',
                'group' => 'Documenten',
                'icon' => 'receipt-outline',
                'sequenceable' => true,
                'visible' => fn () => false,
            ],
            [
                'key' => 'set_fulfillment_status',
                'label' => 'Fulfilment-status wijzigen',
                'group' => 'Status',
                'icon' => 'flag-outline',
                'sequenceable' => true,
                'fields' => [
                    ['name' => 'status', 'label' => 'Fulfilment-status', 'type' => 'select', 'required' => true, 'options' => Orders::getFulfillmentStatusses()],
                ],
                'visible' => fn () => false,
            ],
            [
                'key' => 'mark_paid',
                'label' => 'Markeer als betaald',
                'group' => 'Betaling',
                'icon' => 'checkmark-circle-outline',
                'sequenceable' => true,
                'visible' => fn () => false,
            ],
        ]);
    }
}
