<?php

namespace Dashed\DashedEcommerceCore\Filament\Pages\POS;

use Carbon\Carbon;
use Filament\Forms\Get;
use Livewire\Component;
use Filament\Forms\Form;
use Illuminate\Support\Str;
use Dashed\DashedCore\Classes\Sites;
use Filament\Forms\Components\Select;
use Filament\Forms\Contracts\HasForms;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Dashed\DashedEcommerceCore\Models\Order;
use Dashed\DashedEcommerceCore\Models\POSCart;
use Filament\Forms\Concerns\InteractsWithForms;
use Dashed\DashedEcommerceCore\Models\DiscountCode;
use Dashed\DashedEcommerceCore\Classes\CurrencyHelper;

class POSPage extends Component implements HasForms
{
    use InteractsWithForms;


    //    public $loading = false;
    //
    public $subTotal = 0;
    public $discount = 0;
    public $vat = 0;
    public $vatPercentages = [];
    public $total = 0;
    public $totalUnformatted = 0;

    public $cartInstance = 'handorder';
    public $orderOrigin = 'pos';

    public ?array $customProductData = [
        'quantity' => 1,
        'vat_rate' => 21,
    ];
    public ?array $createDiscountData = [];
    public $cashPaymentAmount = null;

    protected $listeners = [
        'fullscreenValue',
        'notify',
    ];

    public function mount(): void
    {
    }

    public function notify($type, $message): void
    {
        Notification::make()
            ->title($message)
            ->$type()
            ->send();
    }

    public function getForms(): array
    {
        return [
            'customProductForm',
            'createDiscountForm',
//            'searchOrderForm',
        ];
    }

    public function customProductForm(Form $form): Form
    {
        return $form
            ->schema([
                TextInput::make('name')
                    ->label('Productnaam')
                    ->required()
                    ->autofocus()
                    ->columnSpanFull(),
                TextInput::make('price')
                    ->label('Prijs')
                    ->numeric()
                    ->minValue(0)
                    ->maxValue(999999)
                    ->inputMode('decimal')
                    ->required()
                    ->prefix('€')
                    ->columnSpanFull(),
                TextInput::make('quantity')
                    ->label('Aantal')
                    ->numeric()
                    ->minValue(1)
                    ->maxValue(999999)
                    ->inputMode('numeric')
                    ->required()
                    ->default(1)
                    ->prefix('x'),
                TextInput::make('vat_rate')
                    ->label('Percentage')
                    ->numeric()
                    ->minValue(0)
                    ->maxValue(100)
                    ->inputMode('numeric')
                    ->required()
                    ->default(21)
                    ->prefix('%'),
            ])
            ->columns(2)
            ->statePath('customProductData');
    }

    //    public function searchOrderForm(Form $form): Form
    //    {
    //        return $form
    //            ->schema([
    //                TextInput::make('order_id')
    //                    ->label('Zoek order op ID')
    //                    ->required()
    //                    ->autofocus()
    //                    ->columnSpanFull()
    //                    ->extraInputAttributes([
    //                        'class' => 'search-order',
    //                    ]),
    //            ])
    //            ->columns(2)
    //            ->statePath('searchOrderData');
    //    }
    //
    //    public function submitSearchOrderForm()
    //    {
    //        $orderId = str($this->searchOrderData['order_id'])->trim()->replace(' ', '')->replace('order-', '');
    //        $this->searchOrderData['order_id'] = $orderId;
    //        $order = Order::where('id', $orderId)
    //            ->orWhere('invoice_id', $orderId)
    //            ->first();
    //        if (!$order) {
    //            Notification::make()
    //                ->title('Order niet gevonden')
    //                ->danger()
    //                ->send();
    //            $this->showOrder = null;
    //
    //            return;
    //        }
    //
    //        $this->searchOrderPopup = false;
    //        $this->showOrder = $order;
    //        $this->searchOrderData['order_id'] = null;
    //    }

    public function submitCustomProductForm()
    {
        $this->customProductForm->validate();

        $product = [
            'id' => null,
            'product' => null,
            'name' => $this->customProductData['name'],
            'quantity' => $this->customProductData['quantity'],
            'price' => $this->customProductData['price'] * $this->customProductData['quantity'],
            'singlePrice' => $this->customProductData['price'],
            'vat_rate' => $this->customProductData['vat_rate'],
            'customProduct' => true,
            'extra' => [],
            'identifier' => Str::random(),
            'customId' => 'custom-' . rand(1, 10000000),
        ];

        $posCart = POSCart::where('user_id', auth()->user()->id)->where('status', 'active')->first();
        $products = $posCart->products;
        $products[] = $product;
        $posCart->products = $products;
        $posCart->save();

        $this->customProductData = [
            'quantity' => 1,
            'vat_rate' => 21,
        ];

        $this->dispatch('addCustomProduct', $product);
    }

    public function createDiscountForm(Form $form): Form
    {
        return $form
            ->schema([
                Select::make('type')
                    ->label('Type')
                    ->options([
                        'percentage' => 'Percentage',
                        'amount' => 'Vast bedrag',
                        'discountCode' => 'Kortingscode',
                    ])
                    ->reactive()
                    ->autofocus()
                    ->required(),
                TextInput::make('note')
                    ->label('Reden voor korting')
                    ->visible(fn (Get $get) => $get('type') != 'discountCode')
                    ->reactive(),
                TextInput::make('amount')
                    ->label('Prijs')
                    ->numeric()
                    ->minValue(0)
                    ->maxValue(999999)
                    ->inputMode('decimal')
                    ->required()
                    ->prefix('€')
                    ->reactive()
                    ->visible(fn (Get $get) => $get('type') == 'amount')
                    ->helperText('Bij opslaan wordt er een kortingscode gemaakt die 30 minuten geldig is.'),
                TextInput::make('percentage')
                    ->label('Percentage')
                    ->numeric()
                    ->minValue(0)
                    ->maxValue(100)
                    ->inputMode('numeric')
                    ->required()
                    ->default(21)
                    ->prefix('%')
                    ->reactive()
                    ->visible(fn (Get $get) => $get('type') == 'percentage')
                    ->helperText('Bij opslaan wordt er een kortingscode gemaakt die 30 minuten geldig is.'),
                Select::make('discountCode')
                    ->label('Kortings code')
                    ->preload()
                    ->searchable()
                    ->options(function () {
                        $discountCodes = DiscountCode::usable()->get();
                        $options = [];
                        foreach ($discountCodes as $discountCode) {
                            $options[$discountCode->id] = $discountCode->name . ' (' . $discountCode->code . ') (' . ($discountCode->type == 'amount' ? CurrencyHelper::formatPrice($discountCode->discount_amount) : ($discountCode->discount_percentage . '%')) . ')';
                        }

                        return $options;
                    })
                    ->required()
                    ->visible(fn (Get $get) => $get('type') == 'discountCode'),

            ])
            ->statePath('createDiscountData');
    }

    public function submitCreateDiscountForm()
    {
        $posCart = POSCart::where('user_id', auth()->user()->id)->where('status', 'active')->first();

        if (! $posCart->products) {
            Notification::make()
                ->title('Geen producten in winkelmand')
                ->danger()
                ->send();
            $this->createDiscountPopup = false;

            return;
        }

        $this->createDiscountForm->validate();

        if ($this->createDiscountData['type'] == 'discountCode') {
            $discountCode = DiscountCode::find($this->createDiscountData['discountCode']);
        } else {
            $discountCode = new DiscountCode();
            $discountCode->site_ids = [Sites::getActive()];
            $discountCode->name = 'Point of Sale discount';
            $discountCode->note = $this->createDiscountData['note'] ?? '';
            $discountCode->code = '*****-*****-*****-*****-*****';
            $discountCode->type = $this->createDiscountData['type'];
            $discountCode->{'discount_' . $this->createDiscountData['type']} = $this->createDiscountData[$this->createDiscountData['type']];
            $discountCode->start_date = Carbon::now();
            $discountCode->end_date = Carbon::now()->addMinutes(30);
            $discountCode->limit_use_per_customer = 1;
            $discountCode->use_stock = 1;
            $discountCode->stock = 1;
            $discountCode->save();
        }

        if (! $discountCode) {
            Notification::make()
                ->title('Kortingscode niet gevonden')
                ->danger()
                ->send();
        }
        $posCart->discount_code = $discountCode->code;
        $posCart->save();

        $this->createDiscountData = [];

        $this->dispatch('discountCodeCreated');
    }

    public function render()
    {
        return view('dashed-ecommerce-core::pos.pages.point-of-sale');
    }

    public function fullscreenValue($fullscreen)
    {
        $this->fullscreen = $fullscreen;
    }
}
