<?php

namespace Qubiqx\QcommerceEcommerceCore\Filament\Resources\OrderResource\Pages;

use Filament\Forms\Components\Select;
use Filament\Forms\Components\Toggle;
use Filament\Forms\Components\Section;
use Filament\Forms\Contracts\HasForms;
use Filament\Forms\Components\TextInput;
use Filament\Pages\Actions\Action;
use Filament\Resources\Pages\ViewRecord;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Concerns\InteractsWithForms;
use Qubiqx\QcommerceEcommerceCore\Classes\Orders;
use Qubiqx\QcommerceEcommerceCore\Classes\CurrencyHelper;
use Qubiqx\QcommerceEcommerceCore\Filament\Resources\OrderResource;

class CancelOrder extends ViewRecord implements HasForms
{
    use InteractsWithForms;

    protected static string $resource = OrderResource::class;

    protected static string $view = 'qcommerce-ecommerce-core::orders.cancel-order';

//    protected $listeners = [
//        'refreshPage' => 'render',
//        'notify' => 'message',
//    ];

    protected function getTitle(): string
    {
        return "Bestelling {$this->record->invoice_id} annuleren van {$this->record->name}";
    }

    protected function getActions(): array
    {
        return [
            Action::make('Terug naar bestelling')
                ->button()
                ->url(route('filament.resources.orders.view', [$this->record])),
        ];
    }

//    public function mount($record): void
//    {
//        $this->record = $this->getRecord($record);
//        foreach ($this->record->orderProducts as $orderProduct) {
//            $this->data["order_product_$orderProduct->id_quantity"] = 0;
//        }
//    }

    protected function getFormSchema(): array
    {
        $orderProductSchema = [];
        foreach ($this->record->orderProducts as $orderProduct) {
            $orderProductSchema[] = TextInput::make("order_product_$orderProduct->id")
                ->label("$orderProduct->name retourneren")
                ->type('number')
                ->minValue(0)
                ->maxValue($orderProduct->quantity)
                ->rules([
                    'numeric',
                    'max:' . $orderProduct->quantity,
                    'min:0',
                ])
                ->helperText("{$orderProduct->quantity}x besteld voor " . CurrencyHelper::formatPrice($orderProduct->price));
        }

        return [
            Section::make('Annuleren')
                ->schema([
                    Placeholder::make('')
                        ->content('Klik op onderstaande knop om deze bestelling te annuleren.'),
                ])
                ->hidden($this->record->order_origin == 'own'),
            Section::make('Retour aanmaken')
                ->schema([
                    Placeholder::make('')
                        ->content('Kies de hoeveelheid van de producten, of de klant een mail moet krijgen, of er een creditfactuur gemaakt moet worden, of de gekochten producten geretourneerd moeten worden en of de voorraad teruggeboekt moet worden. Afhankelijk van de gekozen opties wordt er een credit bestelling aangemaakt of wordt deze bestelling simpelweg op geannuleerd gezet.'),
                ])
                ->hidden($this->record->order_origin != 'own'),
            Section::make('Bestelde producten')
                ->schema(array_merge($orderProductSchema, [
                    TextInput::make('extra_order_line_name')
                        ->required()
                        ->rules([
                            'required',
                        ])
                        ->hidden(fn ($get) => ! $get('extra_order_line')),
                    TextInput::make('extra_order_line_price')
                        ->required()
                        ->rules([
                            'numeric',
                            'required',
                            'min:0.01',
                            'max:100000',
                        ])
                        ->hidden(fn ($get) => ! $get('extra_order_line')),
                ]))
                ->columns([
                    'default' => 1,
                    'lg' => 3,
                ])
                ->hidden($this->record->order_origin != 'own'),
            Section::make('Overige opties')
                ->schema([
                    Select::make('fulfillment_status')
                        ->label('Verander fulfillment status naar')
                        ->options(Orders::getFulfillmentStatusses()),
                    Toggle::make('send_customer_email')
                        ->label('Moet de klant een mail krijgen van deze annulering/retournering?'),
                    Toggle::make('create_credit_invoice')
                        ->label('Moet er een creditfactuur gemaakt worden?'),
                    Toggle::make('products_must_be_returned')
                        ->label('Moeten de producten geretourneerd worden? (Hiermee wordt er automatisch een
                                    creditfactuur gemaakt)'),
                    Toggle::make('restock')
                        ->label('Moet de voorraad weer terug geboekt worden?'),
                    Toggle::make('refund_discount_costs')
                        ->label('Korting terugvorderen? (' . CurrencyHelper::formatPrice($this->record->discount) . ') (Dit geldt alleen voor vaste korting, ex. €40,-, procentuele korting is op product niveau en wordt altijd terug gevorderd)'),
                    Toggle::make('extra_order_line')
                        ->label('Extra regel voor de factuur (Gebruik voor bijv. apart gekochte producten, of een
                                    aparte teruggave van een bedrag. Wil je alleen deze regel, zet de retour producten
                                    dan op 0 hierboven)')
                        ->reactive(),
                ])
                ->hidden($this->record->order_origin != 'own'),
        ];
    }

    public function submit()
    {
        if ($this->record->order_origin == 'own') {
            $sendCustomerEmail = $this->form->getState()['send_customer_email'];
            $createCreditInvoice = $this->form->getState()['create_credit_invoice'];
            $productsMustBeReturned = $this->form->getState()['products_must_be_returned'];
            if ($productsMustBeReturned) {
                $createCreditInvoice = true;
            }
            $restock = $this->form->getState()['restock'];
//            $refundShippingCosts = $request->refundShippingCosts;
//            $refundPaymentCosts = $request->refundPaymentCosts;
            $refundDiscountCosts = $this->form->getState()['refund_discount_costs'];

            $cancelledProductsQuantity = 0;
            $orderProducts = $this->record->orderProducts;
            foreach ($orderProducts as $orderProduct) {
                $cancelledProductsQuantity += $this->form->getState()["order_product_$orderProduct->id"] ?? 0;
                $orderProduct->refundQuantity = $this->form->getState()["order_product_$orderProduct->id"] ?? 0;
            }

            $extraOrderLine = $this->form->getState()['extra_order_line'];
            $extraOrderLineName = $this->form->getState()['extra_order_line_name'] ?? '';
            $extraOrderLinePrice = $this->form->getState()['extra_order_line_price'] ?? '';

            if (! $extraOrderLine && $cancelledProductsQuantity == 0) {
                $this->notify('danger', 'Je moet tenminste 1 product laten retourneren.');

                return;
            }

            if ($productsMustBeReturned || $this->record->invoice_id != 'PROFORMA') {
                $createCreditInvoice = true;
            }

            if (! $createCreditInvoice) {
                $this->record->changeStatus('cancelled', $sendCustomerEmail);

                $this->notify('success', 'Bestelling gemarkeerd als geannuleerd');

                return redirect(route('filament.resources.orders.view', [$this->record]));
            } else {
                $newOrder = $this->record->markAsCancelledWithCredit($sendCustomerEmail, $createCreditInvoice, $productsMustBeReturned, $restock, $refundDiscountCosts, $extraOrderLineName, $extraOrderLinePrice, $orderProducts, $this->form->getState()['fulfillment_status']);
                $this->notify('success', 'Bestelling gemarkeerd als geannuleerd');

                return redirect(route('filament.resources.orders.view', [$newOrder]));
            }
        } else {
            $this->record->changeStatus('cancelled');
            $this->notify('success', 'Bestelling gemarkeerd als geannuleerd');

            return redirect(route('filament.resources.orders.view', [$this->record]));
        }
    }
}
