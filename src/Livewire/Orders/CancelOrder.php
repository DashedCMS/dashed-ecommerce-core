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
use Dashed\DashedCore\Models\Customsetting;
use Dashed\DashedEcommerceCore\Models\Order;
use Filament\Infolists\Components\TextEntry;
use Dashed\DashedEcommerceCore\Classes\Orders;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Actions\Concerns\InteractsWithActions;
use Filament\Schemas\Concerns\InteractsWithSchemas;
use Dashed\DashedEcommerceCore\Models\PaymentMethod;
use Dashed\DashedEcommerceCore\Classes\CurrencyHelper;
use Dashed\DashedEcommerceCore\Services\OrderReturn\FreeShippingReversal;
use Dashed\DashedEcommerceCore\Services\OrderReturn\CancellationReturnSettler;
use Dashed\DashedEcommerceCore\Services\OrderReturn\FreeShippingReversalProposal;

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

    /**
     * Voorselectie van "Betaalmethode voor terugbetaling": de methode waarmee
     * de bestelling het laatst betaald is, want daarlangs gaat het geld in de
     * regel ook terug. Stond hier altijd de contante methode voor, dan kwam
     * een terugbetaling via de betaalprovider als "Contant" op de creditorder
     * als niemand de keuze aanpaste. Zonder bruikbare betaling blijft de
     * contante methode de terugval.
     */
    public static function defaultRefundPaymentMethodId(Order $order): ?int
    {
        $paidWith = $order->orderPayments()
            ->where('status', 'paid')
            ->where('amount', '>', 0)
            ->whereNotNull('payment_method_id')
            ->orderByDesc('id')
            ->value('payment_method_id');

        $methods = PaymentMethod::whereIn('type', ['pos', 'online']);

        if ($paidWith && (clone $methods)->whereKey($paidWith)->exists()) {
            return (int) $paidWith;
        }

        return $methods->where('is_cash_payment', 1)->first()->id ?? null;
    }

    public function action(): Action
    {
        $fillForm = [
            'fulfillment_status' => Customsetting::get('order_cancel_default_fulfillment_status', null),
            'payment_method_id' => static::defaultRefundPaymentMethodId($this->order),
        ];

        $fillForm['charge_shipping'] = true;

        foreach ($this->order->orderProducts as $orderProduct) {
            $fillForm["order_product_$orderProduct->id"] = 0;
        }

        return Action::make('action')
            ->label($this->buttonText ?: __('Annuleren'))
            ->extraAttributes([
                'class' => $this->buttonClass,
            ])
            ->color('primary')
            ->fillForm($fillForm)
            ->schema(function () {
                $orderProductSchema = [];
                foreach ($this->order->orderProducts as $orderProduct) {
                    $orderProductSchema[] = \LaraZeus\Quantity\Components\Quantity::make("order_product_$orderProduct->id")
                        ->label(__(':naam retourneren', ['naam' => $orderProduct->name]))
                        ->numeric()
                        ->minValue(0)
                        ->default(0)
                        ->live(onBlur: true)
                        ->maxValue($orderProduct->quantity)
                        ->helperText(__(':aantal x besteld voor :prijs', ['aantal' => $orderProduct->quantity, 'prijs' => CurrencyHelper::formatPrice($orderProduct->price)]));
                }

                return [
                    Section::make(__('Annuleren'))->columnSpanFull()
                        ->description(__('Voor een bestelling die niet of niet volledig geleverd is. Komt er iets terug na levering, gebruik dan "Retour aanmelden" bovenaan: dan loopt de creditorder via de retour.'))
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
                    Section::make(__('Bestelde producten'))
                        ->columnSpanFull()
                        ->headerActions([
                            Action::make('returnAll')
                                ->label(__('Alles retourneren'))
                                ->action(function (Set $set) {
                                    foreach ($this->order->orderProducts as $orderProduct) {
                                        $set("order_product_$orderProduct->id", $orderProduct->quantity);
                                    }
                                }),
                        ])
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
                    Section::make(__('Overige opties'))->columnSpanFull()
                        ->schema([
                            Select::make('fulfillment_status')
                                ->label(__('Verander fulfillment status naar'))
                                ->required()
                                ->options(Orders::getFulfillmentStatusses()),
                            Select::make('payment_method_id')
                                ->label(__('Betaalmethode voor terugbetaling'))
                                ->options(PaymentMethod::whereIn('type', ['pos', 'online'])->pluck('name', 'id')->toArray()),
//                                ->options(PaymentMethod::whereIn('type', ['pos', 'online'])->where('psp', 'own')->pluck('name', 'id')->toArray()),
                            Toggle::make('send_customer_email')
                                ->label(__('Moet de klant een mail krijgen van deze annulering/retournering?')),
                            Toggle::make('products_must_be_returned')
                                ->label(__('Moet de klant de producten nog retourneren?')),
                            Toggle::make('restock')
                                ->label(__('Moet de voorraad weer terug geboekt worden?')),
                            Toggle::make('refund_discount_costs')
                                ->label(__('Korting terugvorderen? (:bedrag) (Dit geldt alleen voor vaste korting, ex. €40,-, procentuele korting is op product niveau en wordt altijd terug gevorderd)', ['bedrag' => CurrencyHelper::formatPrice($this->order->discount)])),
                            Toggle::make('charge_shipping')
                                ->label(__('Verzendkosten inhouden'))
                                ->helperText(function ($get) {
                                    $proposal = $this->reversalProposal($this->quantityState($get));

                                    return $proposal ? __('De bestelling zakt van :voor naar :na en komt onder gratis verzending vanaf :drempel. Voorstel: :bedrag (:methode) inhouden op de terugbetaling.', [
                                        'voor' => CurrencyHelper::formatPrice($proposal->valueBefore),
                                        'na' => CurrencyHelper::formatPrice($proposal->valueAfter),
                                        'drempel' => CurrencyHelper::formatPrice($proposal->threshold),
                                        'bedrag' => CurrencyHelper::formatPrice($proposal->amount),
                                        'methode' => $proposal->methodName,
                                    ]) : null;
                                })
                                ->visible(fn ($get) => $this->reversalProposal($this->quantityState($get)) !== null)
                                ->default(true)
                                ->reactive(),
                            TextInput::make('charge_shipping_amount')
                                ->label(__('Afwijkend bedrag (optioneel)'))
                                ->helperText(__('Leeg laten houdt het voorgestelde bedrag in. Een hoger bedrag wordt genegeerd.'))
                                ->numeric()
                                ->minValue(0.01)
                                ->visible(fn ($get) => $get('charge_shipping') && $this->reversalProposal($this->quantityState($get)) !== null),
                            Toggle::make('extra_order_line')
                                ->label(__('Extra regel voor de factuur (Gebruik voor bijv. apart gekochte producten, of een
                                    aparte teruggave van een bedrag. Wil je alleen deze regel, zet de retour producten
                                    dan op 0 hierboven)'))
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
                            ->title(__('Je moet tenminste 1 product laten retourneren.'))
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
                    $newOrder = $this->order->markAsCancelledWithCredit($sendCustomerEmail, $productsMustBeReturned, $restock, $refundDiscountCosts, $extraOrderLineName, $extraOrderLinePrice, $orderProducts, $data['fulfillment_status'], $data['payment_method_id'], freeShippingReversalAmount: $reversalAmount = $this->reversalAmountFor($data));

                    // Een open retour op deze bestelling is met deze annulering
                    // feitelijk verwerkt: koppel hem aan de creditorder.
                    $cancelledQuantities = [];
                    foreach ($orderProducts as $orderProduct) {
                        $cancelledQuantities[$orderProduct->id] = (int) $orderProduct->refundQuantity;
                    }
                    app(CancellationReturnSettler::class)->settle($this->order, $newOrder, $cancelledQuantities);

                    Notification::make()
                        ->title(__('Bestelling gemarkeerd als geannuleerd'))
                        ->body($reversalAmount ? __('Verzendkosten ingehouden: :bedrag', ['bedrag' => CurrencyHelper::formatPrice($reversalAmount)]) : null)
                        ->success()
                        ->send();

                    return redirect(route('filament.dashed.resources.orders.view', [$newOrder]));
                    //                    }
                } else {
                    $this->order->changeStatus('cancelled');

                    Notification::make()
                        ->title(__('Bestelling gemarkeerd als geannuleerd'))
                        ->success()
                        ->send();

                    return redirect(route('filament.dashed.resources.orders.view', [$this->order]));
                }
            });
    }

    /**
     * Verzendkosten die bij deze annulering worden ingehouden, of null. De
     * server rekent altijd zelf; het formulier kan het bedrag alleen verlagen.
     *
     * @param  array<string, mixed>  $data  formulierstatus met order_product_{id}-aantallen
     */
    public function reversalAmountFor(array $data): ?float
    {
        if (! ($data['charge_shipping'] ?? true)) {
            return null;
        }

        $proposal = $this->reversalProposal($data);
        if (! $proposal) {
            return null;
        }

        $requested = (float) ($data['charge_shipping_amount'] ?? 0);

        return $requested > 0 ? min($proposal->amount, round($requested, 2)) : $proposal->amount;
    }

    /** @param  array<string, mixed>  $data */
    protected function reversalProposal(array $data): ?FreeShippingReversalProposal
    {
        $quantities = [];
        foreach ($this->order->orderProducts as $orderProduct) {
            $quantities[$orderProduct->id] = (int) ($data["order_product_$orderProduct->id"] ?? 0);
        }

        return app(FreeShippingReversal::class)->propose($this->order, $quantities);
    }

    /** @return array<string, int> */
    protected function quantityState($get): array
    {
        $state = [];
        foreach ($this->order->orderProducts as $orderProduct) {
            $state["order_product_$orderProduct->id"] = (int) $get("order_product_$orderProduct->id");
        }

        return $state;
    }

    public function render()
    {
        return view('dashed-ecommerce-core::orders.components.plain-action');
    }
}
