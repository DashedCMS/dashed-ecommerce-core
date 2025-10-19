<?php

namespace Dashed\DashedEcommerceCore\Filament\Resources\OrderResource\Pages;

use Filament\Actions\Action;
use Filament\Resources\Pages\Page;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Toggle;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Contracts\HasSchemas;
use Dashed\DashedEcommerceCore\Models\Order;
use Filament\Infolists\Components\TextEntry;
use Dashed\DashedEcommerceCore\Classes\Orders;
use Filament\Schemas\Concerns\InteractsWithSchemas;
use Dashed\DashedEcommerceCore\Classes\CurrencyHelper;
use Dashed\DashedEcommerceCore\Filament\Resources\OrderResource;

class CancelOrder extends Page implements HasSchemas
{
    use InteractsWithSchemas;
    protected static string $resource = OrderResource::class;

    protected string $view = 'dashed-ecommerce-core::orders.cancel-order';

    public Order $order;

    //    protected $listeners = [
    //        'refreshPage' => 'render',
    //        'notify' => 'message',
    //    ];

    public function mount($record)
    {
        $this->order = Order::find($record);
    }

    public function getTitle(): string
    {
        return "Bestelling {$this->order->invoice_id} annuleren van {$this->order->name}";
    }

    protected function getActions(): array
    {
        return [
            Action::make('Terug naar bestelling')
                ->button()
                ->url(route('filament.dashed.resources.orders.view', [$this->order])),
        ];
    }

    //    public function mount($record): void
    //    {
    //        $this->order = $this->getRecord($record);
    //        foreach ($this->order->orderProducts as $orderProduct) {
    //            $this->data["order_product_$orderProduct->id_quantity"] = 0;
    //        }
    //    }

    protected function getFormSchema(): array
    {
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
            Section::make('Annuleren')->columnSpanFull()
                ->schema([
                    TextEntry::make('')
                        ->state('Klik op onderstaande knop om deze bestelling te annuleren.'),
                ])
                ->hidden($this->order->order_origin == 'own'),
            Section::make('Retour aanmaken')->columnSpanFull()
                ->schema([
                    TextEntry::make('')
                        ->state('Kies de hoeveelheid van de producten, of de klant een mail moet krijgen, of er een creditfactuur gemaakt moet worden, of de gekochten producten geretourneerd moeten worden en of de voorraad teruggeboekt moet worden. Afhankelijk van de gekozen opties wordt er een credit bestelling aangemaakt of wordt deze bestelling simpelweg op geannuleerd gezet.'),
                ])
                ->hidden($this->order->order_origin != 'own'),
            Section::make('Bestelde producten')->columnSpanFull()
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
                ->hidden($this->order->order_origin != 'own'),
            Section::make('Overige opties')->columnSpanFull()
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
                        ->label('Korting terugvorderen? (' . CurrencyHelper::formatPrice($this->order->discount) . ') (Dit geldt alleen voor vaste korting, ex. €40,-, procentuele korting is op product niveau en wordt altijd terug gevorderd)'),
                    Toggle::make('extra_order_line')
                        ->label('Extra regel voor de factuur (Gebruik voor bijv. apart gekochte producten, of een
                                    aparte teruggave van een bedrag. Wil je alleen deze regel, zet de retour producten
                                    dan op 0 hierboven)')
                        ->reactive(),
                ])
                ->hidden($this->order->order_origin != 'own'),
        ];
    }

    public function submit()
    {
        if ($this->order->order_origin == 'own') {
            $sendCustomerEmail = $this->form->getState()['send_customer_email'];
            $createCreditInvoice = $this->form->getState()['create_credit_invoice'];
            $productsMustBeReturned = $this->form->getState()['products_must_be_returned'];
            if ($productsMustBeReturned) {
                $createCreditInvoice = true;
            }
            $restock = $this->form->getState()['restock'];
            $refundDiscountCosts = $this->form->getState()['refund_discount_costs'];

            $cancelledProductsQuantity = 0;
            $orderProducts = $this->order->orderProducts;
            foreach ($orderProducts as $orderProduct) {
                $cancelledProductsQuantity += $this->form->getState()["order_product_$orderProduct->id"] ?? 0;
                $orderProduct->refundQuantity = $this->form->getState()["order_product_$orderProduct->id"] ?? 0;
            }

            $extraOrderLine = $this->form->getState()['extra_order_line'];
            $extraOrderLineName = $this->form->getState()['extra_order_line_name'] ?? '';
            $extraOrderLinePrice = $this->form->getState()['extra_order_line_price'] ?? '';

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

                return redirect(route('filament.dashed.resources.orders.view', [$this->order]));
            } else {
                $newOrder = $this->order->markAsCancelledWithCredit($sendCustomerEmail, $createCreditInvoice, $productsMustBeReturned, $restock, $refundDiscountCosts, $extraOrderLineName, $extraOrderLinePrice, $orderProducts, $this->form->getState()['fulfillment_status']);

                Notification::make()
                    ->title('Bestelling gemarkeerd als geannuleerd')
                    ->success()
                    ->send();

                return redirect(route('filament.dashed.resources.orders.view', [$newOrder]));
            }
        } else {
            $this->order->changeStatus('cancelled');

            Notification::make()
                ->title('Bestelling gemarkeerd als geannuleerd')
                ->success()
                ->send();

            return redirect(route('filament.dashed.resources.orders.view', [$this->order]));
        }
    }
}
