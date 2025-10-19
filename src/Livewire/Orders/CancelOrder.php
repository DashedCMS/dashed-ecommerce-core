<?php

namespace Dashed\DashedEcommerceCore\Livewire\Orders;

use Livewire\Component;
use Filament\Actions\Action;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Toggle;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Schemas\Components\Section;
use Filament\Actions\Contracts\HasActions;
use Filament\Schemas\Contracts\HasSchemas;
use Dashed\DashedEcommerceCore\Models\Order;
use Filament\Infolists\Components\TextEntry;
use Dashed\DashedEcommerceCore\Classes\Orders;
use Filament\Actions\Concerns\InteractsWithActions;
use Filament\Schemas\Concerns\InteractsWithSchemas;
use Dashed\DashedEcommerceCore\Models\PaymentMethod;
use Dashed\DashedEcommerceCore\Classes\CurrencyHelper;

class CancelOrder extends Component implements HasSchemas, HasActions
{
    use InteractsWithSchemas;
    use InteractsWithActions;

    public Order $order;
    public bool $isPos = false;
    public ?string $buttonText = '';
    public ?string $buttonClass = '';

    public function mount(Order $order, bool $isPos = false, ?string $buttonText = '', ?string $buttonClass = '')
    {
        $this->order = $order;
        $this->buttonText = $buttonText;
        $this->buttonClass = $buttonClass;
        $this->isPos = $isPos;
    }

    public function action(): Action
    {
        $fillForm = [
            'fulfillment_status' => $this->order->fulfillment_status,
            'payment_method_id' => PaymentMethod::whereIn('type', ['pos', 'online'])->where('is_cash_payment', 1)->first()->id ?? null,
//            'payment_method_id' => PaymentMethod::whereIn('type', ['pos', 'online'])->where('psp', 'own')->where('is_cash_payment', 1)->first()->id ?? null,
        ];

        foreach ($this->order->orderProducts as $orderProduct) {
            $fillForm["order_product_$orderProduct->id"] = 0;
        }

        return Action::make('action')
            ->label($this->buttonText ?: 'Annuleer bestelling')
            ->extraAttributes([
                'class' => $this->buttonClass,
            ])
            ->color('primary')
            ->fillForm($fillForm)
            ->schema(function () {
                $orderProductSchema = [];
                foreach ($this->order->orderProducts as $orderProduct) {
                    $orderProductSchema[] = \LaraZeus\Quantity\Components\Quantity::make("order_product_$orderProduct->id")
                        ->label("$orderProduct->name retourneren")
                        ->numeric()
                        ->minValue(0)
                        ->default(0)
                        ->maxValue($orderProduct->quantity)
                        ->helperText("{$orderProduct->quantity}x besteld voor " . CurrencyHelper::formatPrice($orderProduct->price));
                }

                return [
                    Section::make('Annuleren')->columnSpanFull()
                        ->schema([
                            TextEntry::make('cancel')
                                ->state('Klik op onderstaande knop om deze bestelling te annuleren.'),
                        ]),
//                        ->hidden(in_array($this->order->order_origin, ['own', 'pos'])),
//                    Section::make('Retour aanmaken')->columnSpanFull()
//                        ->schema([
//                            TextEntry::make('')
//                                ->state('Kies de hoeveelheid van de producten, of de klant een mail moet krijgen, of er een creditfactuur gemaakt moet worden, of de gekochten producten geretourneerd moeten worden en of de voorraad teruggeboekt moet worden. Afhankelijk van de gekozen opties wordt er een credit bestelling aangemaakt of wordt deze bestelling simpelweg op geannuleerd gezet.'),
//                        ])
//                        ->hidden(!in_array($this->order->order_origin, ['own', 'pos'])),
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
                        ]),
//                        ->hidden(! in_array($this->order->order_origin, ['own', 'pos'])),
                    Section::make('Overige opties')->columnSpanFull()
                        ->schema([
                            Select::make('fulfillment_status')
                                ->label('Verander fulfillment status naar')
                                ->required()
                                ->options(Orders::getFulfillmentStatusses()),
                            Select::make('payment_method_id')
                                ->label('Betaalmethode voor terugbetaling')
                                ->options(PaymentMethod::whereIn('type', ['pos', 'online'])->pluck('name', 'id')->toArray()),
//                                ->options(PaymentMethod::whereIn('type', ['pos', 'online'])->where('psp', 'own')->pluck('name', 'id')->toArray()),
                            Toggle::make('send_customer_email')
                                ->label('Moet de klant een mail krijgen van deze annulering/retournering?'),
                            Toggle::make('products_must_be_returned')
                                ->label('Moet de klant de producten nog retourneren?'),
                            Toggle::make('restock')
                                ->label('Moet de voorraad weer terug geboekt worden?'),
                            Toggle::make('refund_discount_costs')
                                ->label('Korting terugvorderen? (' . CurrencyHelper::formatPrice($this->order->discount) . ') (Dit geldt alleen voor vaste korting, ex. €40,-, procentuele korting is op product niveau en wordt altijd terug gevorderd)'),
                            Toggle::make('extra_order_line')
                                ->label('Extra regel voor de factuur (Gebruik voor bijv. apart gekochte producten, of een
                                    aparte teruggave van een bedrag. Wil je alleen deze regel, zet de retour producten
                                    dan op 0 hierboven)')
                                ->reactive(),
                        ]),
//                        ->hidden(! in_array($this->order->order_origin, ['own', 'pos'])),
                ];
            })
            ->action(function ($data) {
                if ($this->order->invoice_id != 'PROFORMA') {
                    //                if (in_array($this->order->order_origin, ['own', 'pos']) && $this->order->invoice_id != 'PROFORMA') {
                    $sendCustomerEmail = $data['send_customer_email'];
                    $productsMustBeReturned = $data['products_must_be_returned'];
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

                    //                    if ($productsMustBeReturned) {
                    //                        $createCreditInvoice = true;
                    //                    }

                    //                    if (! $createCreditInvoice) {
                    //                        $this->order->changeStatus('cancelled', $sendCustomerEmail);
                    //
                    //                        Notification::make()
                    //                            ->title('Bestelling gemarkeerd als geannuleerd')
                    //                            ->success()
                    //                            ->send();
                    //
                    //                        if ($this->isPos) {
                    //                            $this->closeActionModal();
                    //                        } else {
                    //                            return redirect(route('filament.dashed.resources.orders.view', [$this->order]));
                    //                        }
                    //                    } else {
                    $newOrder = $this->order->markAsCancelledWithCredit($sendCustomerEmail, $productsMustBeReturned, $restock, $refundDiscountCosts, $extraOrderLineName, $extraOrderLinePrice, $orderProducts, $data['fulfillment_status'], $data['payment_method_id']);

                    Notification::make()
                        ->title('Bestelling gemarkeerd als geannuleerd')
                        ->success()
                        ->send();

                    return redirect(route('filament.dashed.resources.orders.view', [$newOrder]));
                    //                    }
                } else {
                    $this->order->changeStatus('cancelled');

                    Notification::make()
                        ->title('Bestelling gemarkeerd als geannuleerd')
                        ->success()
                        ->send();

                    return redirect(route('filament.dashed.resources.orders.view', [$this->order]));
                }
            });
    }

    public function render()
    {
        return view('dashed-ecommerce-core::orders.components.plain-action');
    }
}
