<?php

namespace Dashed\DashedEcommerceCore\Filament\Pages\POS;

use App\Models\User;
use Carbon\Carbon;
<<<<<<< HEAD
=======
use Dashed\DashedEcommerceCore\Classes\Countries;
<<<<<<< HEAD
use Filament\Schemas\Components\Utilities\Get;
>>>>>>> fb4555ce42557585ae0976d428f4262d50f93752
=======
use Filament\Actions\Action;
use Filament\Schemas\Components\Utilities\Get;
>>>>>>> 0e41c1e64088f869ad042e2d1cde9ebbef4f95f7
use Livewire\Component;
use Illuminate\Support\Str;
use Filament\Schemas\Schema;
use Dashed\DashedCore\Classes\Sites;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Schemas\Contracts\HasSchemas;
use Dashed\DashedCore\Models\Customsetting;
use Dashed\DashedEcommerceCore\Models\POSCart;
use Filament\Schemas\Components\Utilities\Get;
use Dashed\DashedEcommerceCore\Models\DiscountCode;
use Filament\Schemas\Concerns\InteractsWithSchemas;
use Dashed\DashedEcommerceCore\Classes\CurrencyHelper;

class POSPage extends Component implements HasSchemas
{
    use InteractsWithSchemas;
    public $searchQueryInputmode = false;
    public $cartInstance = 'handorder';
    public $orderOrigin = 'pos';
    public $customerUserId = '';
    public $firstName = '';
    public $lastName = '';
    public $phoneNumber = '';
    public $email = '';
    public $street = '';
    public $houseNr = '';
    public $zipCode = '';
    public $city = '';
    public $country = '';
    public $company = '';
    public $btwId = '';
    public $invoiceStreet = '';
    public $invoiceHouseNr = '';
    public $invoiceZipCode = '';
    public $invoiceCity = '';
    public $invoiceCountry = '';
    public $note = '';
    public $customFields = [];
    public $productToChange = [];

    public ?array $customProductData = [
        'quantity' => 1,
        'vat_rate' => 21,
    ];
    public ?array $createDiscountData = [
        'type' => 'percentage',
        'note' => '',
        'amount' => '',
        'percentage' => '',
        'discountCode' => '',
    ];
    public ?array $cancelOrderData = [];
    public ?array $customerData = [];
    public $cashPaymentAmount = null;

    protected $listeners = [
        'fullscreenValue',
        'notify',
    ];

    public function mount(): void
    {
        $this->searchQueryInputmode = Customsetting::get('pos_search_query_inputmode', default: false);
    }

    public function notify($type, $message): void
    {
        Notification::make()
            ->title($message)
            ->$type()
            ->send();
    }

    //    public function getForms(): array
    //    {
    //        return [
    //            'customProductForm',
    //            'createDiscountForm',
    //            'customerDataForm',
    //            'changeProductForm',
    //        ];
    //    }

    public function customProductForm(Schema $schema): Schema
    {
        return $schema
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
                \LaraZeus\Quantity\Components\Quantity::make('quantity')
                    ->label('Aantal')
                    ->numeric()
                    ->minValue(1)
                    ->maxValue(999999)
                    ->inputMode('numeric')
                    ->required()
                    ->default(1)
                    ->prefix('x'),
                \LaraZeus\Quantity\Components\Quantity::make('vat_rate')
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

    public function changeProductForm(Schema $schema): Schema
    {
        return $schema
            ->schema([
                TextInput::make('productToChange.name')
                    ->label('Productnaam')
                    ->required()
                    ->disabled()
                    ->columnSpanFull(),
                TextInput::make('productToChange.singlePrice')
                    ->label('Prijs')
                    ->numeric()
                    ->minValue(0)
                    ->maxValue(999999)
                    ->inputMode('decimal')
                    ->required()
                    ->prefix('€')
                    ->columnSpanFull(),
            ])
            ->columns(2);
    }

    public function submitChangeProductForm()
    {
        $this->changeProductForm->validate();

        $posCart = POSCart::where('user_id', auth()->user()->id)->where('status', 'active')->first();

        $products = $posCart->products;

        foreach ($products as &$product) {
            if ($product['identifier'] == $this->productToChange['identifier']) {
                $product['price'] = $this->productToChange['singlePrice'] * $product['quantity'];
                $product['priceFormatted'] = CurrencyHelper::formatPrice($this->productToChange['singlePrice']);
                $product['singlePrice'] = $this->productToChange['singlePrice'];
                $product['isCustomPrice'] = true;
            }
        }

        $posCart->products = $products;
        $posCart->save();
        $this->productToChange = [];

        $this->dispatch('productChanged');
    }

    public function submitCustomProductForm()
    {
        $this->customProductForm->validate();

        $product = [
            'id' => null,
            'product' => null,
            'name' => $this->customProductData['name'],
            'quantity' => $this->customProductData['quantity'],
            'price' => $this->customProductData['price'] * $this->customProductData['quantity'],
            'priceFormatted' => CurrencyHelper::formatPrice($this->customProductData['price'] * $this->customProductData['quantity']),
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

    public function createDiscountForm(Schema $schema): Schema
    {
        return $schema
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
                    ->visible(fn(Get $get) => $get('type') != 'discountCode')
                    ->reactive(),
                TextInput::make('amount')
                    ->label('Prijs')
                    ->numeric()
                    ->minValue(0)
                    ->maxValue(999999)
                    ->inputMode('integer')
                    ->required()
                    ->prefix('€')
                    ->reactive()
                    ->visible(fn(Get $get) => $get('type') == 'amount')
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
                    ->visible(fn(Get $get) => $get('type') == 'percentage')
                    ->helperText('Bij opslaan wordt er een kortingscode gemaakt die 30 minuten geldig is.'),
                Select::make('discountCode')
                    ->label('Kortingscode')
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
                    ->visible(fn(Get $get) => $get('type') == 'discountCode'),

            ])
            ->statePath('createDiscountData');
    }

    public function submitCreateDiscountForm()
    {
        $posCart = POSCart::where('user_id', auth()->user()->id)->where('status', 'active')->first();

        if (!$posCart->products) {
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

        if (!$discountCode) {
            Notification::make()
                ->title('Kortingscode niet gevonden')
                ->danger()
                ->send();
        }
        $posCart->discount_code = $discountCode->code;
        $posCart->save();

        $this->createDiscountData = [
            'type' => 'percentage',
            'note' => '',
            'amount' => '',
            'percentage' => '',
            'discountCode' => '',
        ];

        $this->dispatch('discountCodeCreated', [
            'discountCode' => $discountCode->code,
        ]);
    }

    public function customerDataForm(Schema $schema): Schema
    {
        return $schema
            ->schema([
                Select::make('customerUserId')
                    ->label('Account')
                    ->columnSpanFull()
                    ->options(function () {
                        $users = User::all();
                        $options = [];

                        foreach ($users as $user) {
                            $options[$user->id] = $user->name . ($user->name != $user->email ? ' ( ' . $user->email . ' )' : '');
                        }

                        return $options;
                    })
                    ->suffixAction(
                        Action::make('copyCostToPrice')
                            ->label('Gegevens invoeren')
                            ->icon('heroicon-m-clipboard')
                            ->action(function (Get $get) {

                                $state = $get('customerUserId');

                                if(!$state){
                                    Notification::make()
                                        ->title('Selecteer eerst een gebruiker')
                                        ->danger()
                                        ->send();
                                    return;
                                }

                                $user = User::find($state);

                                if ($user) {
                                    $lastOrder = $user->lastOrderFromAllOrders();
                                    if($lastOrder){
                                        $this->firstName = $lastOrder->first_name;
                                        $this->lastName = $lastOrder->last_name;
                                        $this->email = $user->email;
                                        $this->phoneNumber = $lastOrder->phone_number;
                                        $this->street = $lastOrder->street;
                                        $this->houseNr = $lastOrder->house_nr;
                                        $this->zipCode = $lastOrder->zip_code;
                                        $this->city = $lastOrder->city;
                                        $this->country = $lastOrder->country;
                                        $this->company = $lastOrder->company;
                                        $this->btwId = $lastOrder->btw_id;
                                        $this->invoiceStreet = $lastOrder->invoice_street;
                                        $this->invoiceHouseNr = $lastOrder->invoice_house_nr;
                                        $this->invoiceZipCode = $lastOrder->invoice_zip_code;
                                        $this->invoiceCity = $lastOrder->invoice_city;
                                        $this->invoiceCountry = $lastOrder->invoice_country;

                                        Notification::make()
                                            ->title('Gegevens van laatste bestelling geladen')
                                            ->success()
                                            ->send();
                                    }else{
                                        Notification::make()
                                            ->title('Geen eerdere bestelling gevonden voor deze gebruiker')
                                            ->warning()
                                            ->send();
                                    }
                                }else{
                                    Notification::make()
                                        ->title('Gebruiker niet gevonden')
                                        ->danger()
                                        ->send();
                                }
                            })
                    )
                    ->helperText('Selecteer een account om de bestelling aan te koppelen'),
                TextInput::make('firstName')
                    ->label('Voornaam')
                    ->maxLength(255),
                TextInput::make('lastName')
                    ->label('Achternaam')
                    ->maxLength(255),
                TextInput::make('email')
                    ->label('Email')
                    ->type('email')
                    ->email()
                    ->minLength(4)
                    ->maxLength(255),
                TextInput::make('phoneNumber')
                    ->label('Telefoon nummer')
                    ->maxLength(255),
                TextInput::make('street')
                    ->label('Straat')
                    ->maxLength(255)
                    ->lazy()
                    ->reactive(),
                TextInput::make('houseNr')
                    ->label('Huisnummer')
                    ->nullable()
                    ->required(fn(Get $get) => $get('street'))
                    ->maxLength(255),
                TextInput::make('zipCode')
                    ->label('Postcode')
                    ->required(fn(Get $get) => $get('street'))
                    ->nullable()
                    ->maxLength(255),
                TextInput::make('city')
                    ->label('Stad')
                    ->required(fn(Get $get) => $get('street'))
                    ->nullable()
                    ->maxLength(255),
                Select::make('country')
                    ->label('Land')
                    ->options(function () {
                        $countries = Countries::getAllSelectedCountries();
                        $options = [];
                        foreach ($countries as $country) {
                            $options[$country] = $country;
                        }

                        return $options;
                    })
                    ->required()
                    ->nullable()
                    ->lazy()
                    ->columnSpanFull(),
                TextInput::make('company')
                    ->label('Bedrijfsnaam')
                    ->maxLength(255),
                TextInput::make('btwId')
                    ->label('BTW id')
                    ->maxLength(255),
                TextInput::make('invoiceStreet')
                    ->label('Factuur straat')
                    ->nullable()
                    ->maxLength(255)
                    ->reactive(),
                TextInput::make('invoiceHouseNr')
                    ->label('Factuur huisnummer')
                    ->required(fn(Get $get) => $get('invoice_street'))
                    ->nullable()
                    ->maxLength(255),
                TextInput::make('invoiceZipCode')
                    ->label('Factuur postcode')
                    ->required(fn(Get $get) => $get('invoice_street'))
                    ->nullable()
                    ->maxLength(255),
                TextInput::make('invoiceCity')
                    ->label('Factuur stad')
                    ->required(fn(Get $get) => $get('invoice_street'))
                    ->nullable()
                    ->maxLength(255),
                Select::make('invoiceCountry')
                    ->label('Factuur land')
                    ->required(fn(Get $get) => $get('invoice_street'))
                    ->options(function () {
                        $countries = Countries::getAllSelectedCountries();
                        $options = [];
                        foreach ($countries as $country) {
                            $options[$country] = $country;
                        }

                        return $options;
                    })
                    ->nullable()
                    ->columnSpanFull(),
                Textarea::make('note')
                    ->label('Notitie')
                    ->nullable()
                    ->maxLength(5000)
                    ->columnSpanFull(),
            ])
            ->fill([
                'country' => $this->country ?: Countries::getAllSelectedCountries()[0],
            ])
            ->columns(2);
    }

    public function submitCustomerDataForm()
    {
        $posCart = POSCart::where('user_id', auth()->user()->id)->where('status', 'active')->first();

        $this->customerDataForm->validate();

        $posCart->customer_user_id = $this->customerUserId;
        $posCart->first_name = $this->firstName;
        $posCart->last_name = $this->lastName;
        $posCart->phone_number = $this->phoneNumber;
        $posCart->email = $this->email;
        $posCart->street = $this->street;
        $posCart->house_nr = $this->houseNr;
        $posCart->zip_code = $this->zipCode;
        $posCart->city = $this->city;
        $posCart->country = $this->country;
        $posCart->company = $this->company;
        $posCart->btw_id = $this->btwId;
        $posCart->invoice_street = $this->invoiceStreet;
        $posCart->invoice_house_nr = $this->invoiceHouseNr;
        $posCart->invoice_zip_code = $this->invoiceZipCode;
        $posCart->invoice_city = $this->invoiceCity;
        $posCart->invoice_country = $this->invoiceCountry;
        $posCart->note = $this->note;
        $posCart->save();

        $this->dispatch('saveCustomerData');
    }

    public function render()
    {
        $view = 'dashed-ecommerce-core::pos.pages.point-of-sale';

        if (view()->exists('dashed.pos.point-of-sale')) {
            $view = 'dashed.pos.point-of-sale';
        }

        return view($view);
    }

    public function fullscreenValue($fullscreen)
    {
        $this->fullscreen = $fullscreen;
    }
}
