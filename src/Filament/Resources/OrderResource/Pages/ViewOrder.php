<?php

namespace Dashed\DashedEcommerceCore\Filament\Resources\OrderResource\Pages;

use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ViewRecord;
use Illuminate\Support\Facades\Auth;
use Dashed\DashedEcommerceCore\Models\Order;
use Dashed\DashedEcommerceCore\Models\POSCart;
use Dashed\DashedEcommerceCore\Classes\ConceptOrderService;
use Dashed\DashedEcommerceCore\Models\Printer;
use Dashed\DashedEcommerceCore\Models\PrintJob;
use Dashed\DashedEcommerceCore\Enums\PrinterType;
use Dashed\DashedEcommerceCore\Enums\PrintJobType;
use Dashed\DashedEcommerceCore\Enums\PrintJobStatus;
use Dashed\DashedEcommerceCore\Filament\Resources\OrderResource;
use Dashed\DashedEcommerceCore\Filament\Resources\OrderResource\Actions\SendPaymentLinkAction;
use Dashed\DashedEcommerceCore\Filament\Resources\OrderResource\Actions\RegenerateInvoiceAction;
use Dashed\DashedEcommerceCore\Filament\Resources\OrderResource\Actions\RegisterManualPaymentAction;

class ViewOrder extends ViewRecord
{
    protected static string $resource = OrderResource::class;

    protected string $view = 'dashed-ecommerce-core::orders.view-order';

    protected $listeners = [
        'refreshPage' => 'renderPage',
        'notify' => 'message',
    ];

    public function getTitle(): string
    {
        return "Bestelling {$this->record->invoice_id} van {$this->record->name}";
    }

    /** Is er een actieve printer die verzendlabels kan printen (wachtrij)? */
    private function labelPrinterAvailable(): bool
    {
        return Printer::active()
            ->whereIn('type', [PrinterType::ShippingLabel->value, PrinterType::Both->value])
            ->exists();
    }

    /** Is er een actieve printer die pakbonnen kan printen (wachtrij)? */
    private function packingSlipPrinterAvailable(): bool
    {
        return Printer::active()
            ->whereIn('type', [PrinterType::PackingSlip->value, PrinterType::Both->value])
            ->exists();
    }

    /**
     * Zet één print-job van dit type in de wachtrij, tenzij er voor deze order al
     * een onafgeronde (pending/claimed/printing) job van dit type staat. Voorkomt
     * dubbele jobs. Geeft terug of er een nieuwe job is aangemaakt.
     */
    private function queueJobOnce(PrintJobType $type): bool
    {
        $exists = PrintJob::where('order_id', $this->record->id)
            ->where('type', $type->value)
            ->whereIn('status', [
                PrintJobStatus::Pending->value,
                PrintJobStatus::Claimed->value,
                PrintJobStatus::Printing->value,
            ])
            ->exists();

        if ($exists) {
            return false;
        }

        PrintJob::create([
            'type' => $type,
            'order_id' => $this->record->id,
            'status' => PrintJobStatus::Pending,
        ]);

        return true;
    }

    /**
     * Heeft deze order een verzendlabel bij minstens één geregistreerde
     * verzendkoppeling? Provider-agnostisch via het ShippingLabelProvider-contract.
     */
    private function orderHasLabel(): bool
    {
        foreach (ecommerce()->shippingLabelProviders() as $provider) {
            if ($provider->hasLabelsForOrder($this->record)) {
                return true;
            }
        }

        return false;
    }

    protected function getActions(): array
    {
        $invoiceUrl = $this->record->downloadInvoiceUrl();
        $packingSlipUrl = $this->record->downloadPackingslipUrl();

        $previousOrder = $this->record->fulfillment_status == 'unhandled' ? Order::where('id', '<', $this->record->id)
            ->orderBy('id', 'desc')
            ->where('fulfillment_status', 'unhandled')
            ->isPaid()
            ->first() : '';
        $nextOrder = $this->record->fulfillment_status == 'unhandled' ? Order::where('id', '>', $this->record->id)
            ->orderBy('id', 'asc')
            ->where('fulfillment_status', 'unhandled')
            ->isPaid()
            ->first() : '';

        return array_merge([
            Action::make('Vorige bestelling')
                ->hiddenLabel()
                ->icon('heroicon-s-arrow-left')
                ->url(fn () => $previousOrder ? route('filament.dashed.resources.orders.view', ['record' => $previousOrder->id]) : '')
                ->visible((bool)$previousOrder)
                ->tooltip('Bekijk de vorige onverwerkte bestelling'),
            Action::make('Volgende bestelling')
                ->hiddenLabel()
                ->icon('heroicon-s-arrow-right')
                ->url(fn () => $nextOrder ? route('filament.dashed.resources.orders.view', ['record' => $nextOrder->id]) : '')
                ->visible((bool)$nextOrder)
                ->tooltip('Bekijk de volgende onverwerkte bestelling'),
            Action::make('viewInWebsite')
                ->hiddenLabel()
                ->icon('heroicon-s-globe-alt')
                ->url($this->record->getUrl())
                ->tooltip('Bekijk bestelling in de webshop')
                ->openUrlInNewTab(),
            Action::make('openProformaCheckout')
                ->label('Proforma-afrekenlink')
                ->icon('heroicon-o-link')
                ->color('warning')
                ->tooltip('Open de afrekenpagina die naar de klant is gestuurd')
                ->url(fn () => $this->record->hash ? route('dashed.frontend.proforma-checkout', ['orderHash' => $this->record->hash]) : null)
                ->openUrlInNewTab()
                ->visible(fn (): bool => (bool) $this->record->is_proforma && $this->record->isConcept() && (bool) $this->record->hash),
            Action::make('edit')
                ->hiddenLabel()
                ->icon('heroicon-s-pencil-square')
                ->tooltip('Bewerk bestelling')
                ->url(route('filament.dashed.resources.orders.edit', ['record' => $this->record])),
            ActionGroup::make([
                Action::make('Factuur')
                    ->tooltip('Download de factuur als PDF')
                    ->icon('heroicon-s-arrow-down-tray')
                    ->url($invoiceUrl)
                    ->openUrlInNewTab()
                    ->visible((bool)$invoiceUrl),
                Action::make('Pakbon')
                    ->icon('heroicon-s-arrow-down-tray')
                    ->tooltip('Download de pakbon als PDF')
                    ->url($packingSlipUrl)
                    ->openUrlInNewTab()
                    ->visible((bool)$packingSlipUrl),
                RegenerateInvoiceAction::make($this->record),
                Action::make('reprintDocuments')
                    ->label('Pakbon + label printen')
                    ->icon('heroicon-s-printer')
                    ->tooltip('Stuur pakbon én label (opnieuw) naar de printers — zonder dubbele wachtrij-jobs')
                    ->visible(fn (): bool => $this->packingSlipPrinterAvailable()
                        || ($this->labelPrinterAvailable() && $this->orderHasLabel()))
                    ->requiresConfirmation()
                    ->modalHeading('Opnieuw printen')
                    ->modalDescription('Pakbon en/of verzendlabel worden naar de printers gestuurd. Staat er al een job in de wachtrij voor deze bestelling, dan wordt die niet gedupliceerd.')
                    ->action(function (): void {
                        $queued = [];

                        if ($this->packingSlipPrinterAvailable()
                            && $this->queueJobOnce(PrintJobType::PackingSlip)) {
                            $queued[] = 'pakbon';
                        }

                        if ($this->labelPrinterAvailable() && $this->orderHasLabel()
                            && $this->queueJobOnce(PrintJobType::ShippingLabel)) {
                            $queued[] = 'label';
                        }

                        if ($queued) {
                            Notification::make()
                                ->title('Naar de printer gestuurd: ' . implode(' + ', $queued))
                                ->success()
                                ->send();
                        } else {
                            Notification::make()
                                ->title('Niets toegevoegd')
                                ->body('Er staat al een job in de wachtrij, of er is geen geschikte printer/label.')
                                ->warning()
                                ->send();
                        }
                    }),
                Action::make('printLabelOnly')
                    ->label('Alleen label printen')
                    ->icon('heroicon-s-tag')
                    ->tooltip('Stuur enkel het verzendlabel naar de label-printer')
                    ->visible(fn (): bool => $this->labelPrinterAvailable() && $this->orderHasLabel())
                    ->requiresConfirmation()
                    ->modalHeading('Alleen label printen')
                    ->modalDescription('Alleen het verzendlabel wordt naar de label-printer gestuurd (geen pakbon).')
                    ->action(function (): void {
                        if ($this->queueJobOnce(PrintJobType::ShippingLabel)) {
                            Notification::make()->title('Label naar de printer gestuurd')->success()->send();
                        } else {
                            Notification::make()
                                ->title('Niets toegevoegd')
                                ->body('Er staat al een label-job in de wachtrij voor deze bestelling.')
                                ->warning()
                                ->send();
                        }
                    }),
                Action::make('printPackingSlipOnly')
                    ->label('Alleen pakbon printen')
                    ->icon('heroicon-s-document-text')
                    ->tooltip('Stuur enkel de pakbon naar de pakbon-printer')
                    ->visible(fn (): bool => $this->packingSlipPrinterAvailable())
                    ->requiresConfirmation()
                    ->modalHeading('Alleen pakbon printen')
                    ->modalDescription('Alleen de pakbon wordt naar de pakbon-printer gestuurd (geen label).')
                    ->action(function (): void {
                        if ($this->queueJobOnce(PrintJobType::PackingSlip)) {
                            Notification::make()->title('Pakbon naar de printer gestuurd')->success()->send();
                        } else {
                            Notification::make()
                                ->title('Niets toegevoegd')
                                ->body('Er staat al een pakbon-job in de wachtrij voor deze bestelling.')
                                ->warning()
                                ->send();
                        }
                    }),
            ])
                ->label('Documenten')
                ->icon('heroicon-o-document-text')
                ->button(),
            Action::make('syncLabelStatuses')
                ->label('Labelstatussen bijwerken')
                ->icon('heroicon-o-arrow-path')
                ->color('gray')
                ->tooltip('Haal de actuele bezorgstatus van de verzendlabels op bij de vervoerder(s)')
                ->visible(fn (): bool => $this->orderHasLabel())
                ->requiresConfirmation()
                ->modalHeading('Labelstatussen bijwerken')
                ->modalDescription('De huidige bezorgstatus van de verzendlabels van deze bestelling wordt op de achtergrond opgehaald bij de vervoerder(s).')
                ->modalSubmitActionLabel('Bijwerken')
                ->action(function (): void {
                    \Dashed\DashedEcommerceCore\Jobs\SyncOrderLabelStatusesJob::dispatch($this->record);

                    Notification::make()
                        ->title('Bijwerken gestart')
                        ->body('De labelstatussen worden op de achtergrond bijgewerkt.')
                        ->success()
                        ->send();
                }),
            ActionGroup::make([
                Action::make('markCancelledAsPaid')
                    ->label('Markeer als betaald')
                    ->icon('heroicon-o-check-circle')
                    ->color('success')
                    ->visible(fn () => $this->record->status === 'cancelled')
                    ->requiresConfirmation()
                    ->modalHeading('Geannuleerde bestelling op betaald zetten?')
                    ->modalDescription('De bestelling wordt alsnog op betaald gezet (factuur, voorraad en afhandeling worden verwerkt).')
                    ->action(function () {
                        $this->record->changeStatus('paid');

                        Notification::make()
                            ->title('Bestelling op betaald gezet')
                            ->success()
                            ->send();

                        $this->redirect(route('filament.dashed.resources.orders.view', ['record' => $this->record->id]));
                    }),
                RegisterManualPaymentAction::make($this->record),
                SendPaymentLinkAction::make($this->record),
            ])
                ->label('Betaling')
                ->icon('heroicon-o-banknotes')
                ->color('primary')
                ->button(),
            Action::make('editConceptInPos')
                ->label('Bewerken in kassa')
                ->icon('heroicon-o-pencil-square')
                ->visible(fn (): bool => $this->record->isConcept())
                ->requiresConfirmation(fn (): bool => $this->activeCartHasProducts())
                ->modalHeading('Kassa-winkelwagen vervangen?')
                ->modalDescription('Je kassa bevat al producten. Die worden vervangen door dit concept.')
                ->action(function () {
                    $cart = $this->activePosCart();
                    // loaded_concept_order_id vooraf zetten; hydrate() schrijft products
                    // en slaat alles in één keer op (geen tussentijdse lege-cart-staat).
                    $cart->loaded_concept_order_id = $this->record->id;
                    ConceptOrderService::hydrate($cart, $this->record);

                    $this->redirect(route('dashed.ecommerce.point-of-sale'));
                }),
            Action::make('copyToPos')
                ->label('Kopiëren naar kassa')
                ->icon('heroicon-o-document-duplicate')
                ->requiresConfirmation(fn (): bool => $this->activeCartHasProducts())
                ->modalHeading('Kassa-winkelwagen vervangen?')
                ->modalDescription('Je kassa bevat al producten. Die worden vervangen door een kopie van deze bestelling.')
                ->action(function () {
                    $cart = $this->activePosCart();
                    ConceptOrderService::copyIntoCart($cart, $this->record);

                    $this->redirect(route('dashed.ecommerce.point-of-sale'));
                }),
        ], ecommerce()->buttonActions('order'));
    }

    protected function activePosCart(): POSCart
    {
        $userId = Auth::id();

        $posCart = POSCart::where('user_id', $userId)->where('status', 'active')->first();

        if (! $posCart) {
            $posCart = new POSCart();
            $posCart->user_id = $userId;
            $posCart->status = 'active';
            $posCart->identifier = uniqid();
            $posCart->country = 'NL';
            $posCart->products = [];
            $posCart->save();
        }

        return $posCart;
    }

    protected function activeCartHasProducts(): bool
    {
        // Bewust GEEN cart aanmaken: dit draait bij het renderen van de actie, dus
        // een order bekijken mag geen lege kassa-winkelwagen in de database zetten.
        $cart = POSCart::where('user_id', Auth::id())->where('status', 'active')->first();

        return $cart && filled($cart->products ?? []);
    }

    public function renderPage()
    {
        $this->redirect(route('filament.dashed.resources.orders.view', [$this->record]));
    }

    public function message($notify)
    {
        Notification::make()
            ->title($notify['message'])
            ->{$notify['status']}()
            ->send();
    }
}
