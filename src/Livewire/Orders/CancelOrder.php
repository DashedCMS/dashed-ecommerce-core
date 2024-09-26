<?php

namespace Dashed\DashedEcommerceCore\Livewire\Orders;

use Livewire\Component;
use Filament\Actions\Action;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Toggle;
use Filament\Forms\Components\Section;
use Filament\Forms\Contracts\HasForms;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Actions\Contracts\HasActions;
use Filament\Forms\Components\Placeholder;
use Dashed\DashedEcommerceCore\Models\Order;
use Dashed\DashedEcommerceCore\Classes\Orders;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Actions\Concerns\InteractsWithActions;
use Dashed\DashedEcommerceCore\Classes\CurrencyHelper;

class CancelOrder extends Component implements HasForms, HasActions
{
    use InteractsWithForms;
    use InteractsWithActions;

    public Order $order;
    public bool $isPos = false;
    public string $buttonText = '';
    public string $buttonClass = '';

    public function mount(Order $order, bool $isPos = false, ?string $buttonText = null, ?string $buttonClass = null)
    {
        $this->order = $order;
        $this->buttonText = $buttonText;
        $this->buttonClass = $buttonClass;
        $this->isPos = $isPos;
    }

    public function action(): Action
    {
        return Action::make('action')
            ->label($this->buttonText ?: 'Annuleer bestelling')
            ->extraAttributes([
                'class' => $this->buttonClass,
            ])
            ->color('primary')
            ->fillForm([
                'fulfillment_status' => $this->order->fulfillment_status,
            ])
            ->form(function () {
                $orderProductSchema = [];
                foreach ($this->order->orderProducts as $orderProduct) {
                    $orderProductSchema[] = TextInput::make("order_product_$orderProduct->id")
                        ->label("$orderProduct->name retourneren")
                        ->numeric()
                        ->minValue(0)
                        ->maxValue($orderProduct->quantity)
                        ->helperText("{$orderProduct->quantity}x besteld voor " . CurrencyHelper::formatPrice($orderProduct->price));
                }

                return [
                    Section::make('Annuleren')
                        ->schema([
                            Placeholder::make('')
                                ->content('Klik op onderstaande knop om deze bestelling te annuleren.'),
                        ])
                        ->hidden(in_array($this->order->order_origin, ['own', 'pos'])),
                    Section::make('Retour aanmaken')
                        ->schema([
                            Placeholder::make('')
                                ->content('Kies de hoeveelheid van de producten, of de klant een mail moet krijgen, of er een creditfactuur gemaakt moet worden, of de gekochten producten geretourneerd moeten worden en of de voorraad teruggeboekt moet worden. Afhankelijk van de gekozen opties wordt er een credit bestelling aangemaakt of wordt deze bestelling simpelweg op geannuleerd gezet.'),
                        ])
                        ->hidden(! in_array($this->order->order_origin, ['own', 'pos'])),
                    Section::make('Bestelde producten')
                        ->schema(array_merge($orderProductSchema, [
                            TextInput::make('extra_order_line_name')
                                ->required()
                                ->hidden(fn ($get) => ! $get('extra_order_line')),
                            TextInput::make('extra_order_line_price')
                                ->required()
                                ->numeric()
                                ->minValue(0.01)
                                ->maxValue(100000)
                                ->hidden(fn ($get) => ! $get('extra_order_line')),
                        ]))
                        ->columns([
                            'default' => 1,
                            'lg' => 3,
                        ])
                        ->hidden(! in_array($this->order->order_origin, ['own', 'pos'])),
                    Section::make('Overige opties')
                        ->schema([
                            Select::make('fulfillment_status')
                                ->label('Verander fulfillment status naar')
                                ->required()
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
                                ->label('Korting terugvorderen? (' . CurrencyHelper::formatPrice($this->order->discount) . ') (Dit geldt alleen voor vaste korting, ex. €40,-, procentuele korting is op product niveau en wordt altijd terug gevorderd)'),
                            Toggle::make('extra_order_line')
                                ->label('Extra regel voor de factuur (Gebruik voor bijv. apart gekochte producten, of een
                                    aparte teruggave van een bedrag. Wil je alleen deze regel, zet de retour producten
                                    dan op 0 hierboven)')
                                ->reactive(),
                        ])
                        ->hidden(! in_array($this->order->order_origin, ['own', 'pos'])),
                ];
            })
            ->action(function ($data) {
                if (in_array($this->order->order_origin, ['own', 'pos'])) {
                    $sendCustomerEmail = $data['send_customer_email'];
                    $createCreditInvoice = $data['create_credit_invoice'];
                    $productsMustBeReturned = $data['products_must_be_returned'];
                    if ($productsMustBeReturned) {
                        $createCreditInvoice = true;
                    }
                    $restock = $data['restock'];
                    $refundDiscountCosts = $data['refund_discount_costs'];

                    $cancelledProductsQuantity = 0;
                    $orderProducts = $this->order->orderProducts;
                    foreach ($orderProducts as $orderProduct) {
                        $cancelledProductsQuantity += $data["order_product_$orderProduct->id"] ?? 0;
                        $orderProduct->refundQuantity = $data["order_product_$orderProduct->id"] ?? 0;
                    }

                    $extraOrderLine = $data['extra_order_line'];
                    $extraOrderLineName = $data['extra_order_line_name'] ?? '';
                    $extraOrderLinePrice = $data['extra_order_line_price'] ?? '';

                    if (! $extraOrderLine && $cancelledProductsQuantity == 0) {
                        Notification::make()
                            ->title('Je moet tenminste 1 product laten retourneren.')
                            ->danger()
                            ->send();

                        return;
                    }

                    if ($productsMustBeReturned || $this->order->invoice_id != 'PROFORMA') {
                        $createCreditInvoice = true;
                    }

                    if (! $createCreditInvoice) {
                        $this->order->changeStatus('cancelled', $sendCustomerEmail);

                        Notification::make()
                            ->title('Bestelling gemarkeerd als geannuleerd')
                            ->success()
                            ->send();

                        if ($this->isPos) {
                            $this->closeActionModal();
                        } else {
                            return redirect(route('filament.dashed.resources.orders.view', [$this->order]));
                        }
                    } else {
                        $newOrder = $this->order->markAsCancelledWithCredit($sendCustomerEmail, $createCreditInvoice, $productsMustBeReturned, $restock, $refundDiscountCosts, $extraOrderLineName, $extraOrderLinePrice, $orderProducts, $data['fulfillment_status']);

                        Notification::make()
                            ->title('Bestelling gemarkeerd als geannuleerd')
                            ->success()
                            ->send();

                        if ($this->isPos) {
                            $newOrder->printReceipt();
                            $this->closeActionModal();
                        } else {
                            return redirect(route('filament.dashed.resources.orders.view', [$newOrder]));
                        }
                    }
                } else {
                    $this->order->changeStatus('cancelled');

                    Notification::make()
                        ->title('Bestelling gemarkeerd als geannuleerd')
                        ->success()
                        ->send();

                    if ($this->isPos) {
                        $this->closeActionModal();
                    } else {
                        return redirect(route('filament.dashed.resources.orders.view', [$this->order]));
                    }
                }
            });
    }

    public function render()
    {
        return view('dashed-ecommerce-core::orders.components.plain-action');
    }
}
