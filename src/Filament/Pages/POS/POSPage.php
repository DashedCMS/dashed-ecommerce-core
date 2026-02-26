<?php

namespace Dashed\DashedEcommerceCore\Filament\Pages\POS;

use Carbon\Carbon;
use Livewire\Component;
use Illuminate\Support\Str;
use Filament\Actions\Action;
use Filament\Schemas\Schema;
use Dashed\DashedCore\Models\User;
use Dashed\DashedCore\Classes\Sites;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Schemas\Contracts\HasSchemas;
use LaraZeus\Quantity\Components\Quantity;
use Dashed\DashedCore\Models\Customsetting;
use Dashed\DashedEcommerceCore\Models\POSCart;
use DashedDEV\FilamentNumpadField\NumpadField;
use Filament\Schemas\Components\Utilities\Get;
use Dashed\DashedEcommerceCore\Classes\Countries;
use Dashed\DashedEcommerceCore\Models\DiscountCode;
use Filament\Schemas\Concerns\InteractsWithSchemas;
// Belangrijk: in je controller gebruikte je Dashed\DashedCore\Models\User.
// Hier stond App\Models\User. Dat kan, maar kies 1.
// Ik trek ‘m gelijk met jullie core.
use Dashed\DashedEcommerceCore\Classes\CurrencyHelper;

class POSPage extends Component implements HasSchemas
{
    use InteractsWithSchemas;

    public $searchQueryInputmode = false;

    // Wordt nog door frontend gebruikt als label, maar DB is leidend
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
        'name' => '',
        'quantity' => 1,
        'vat_rate' => 21,
        'price' => 0,
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

        // Zorg dat er altijd een actieve cart bestaat
        $this->getActivePosCart();
    }

    public function notify($type, $message): void
    {
        Notification::make()
            ->title($message)
            ->$type()
            ->send();
    }

    private function getActivePosCart(): POSCart
    {
        $userId = auth()->user()?->id;

        $posCart = POSCart::where('user_id', $userId)
            ->where('status', 'active')
            ->first();

        if (! $posCart) {
            $posCart = new POSCart();
            $posCart->user_id = $userId;
            $posCart->status = 'active';
            $posCart->identifier = uniqid();
            $posCart->country = $this->country ?: (Countries::getAllSelectedCountries()[0] ?? 'NL');
            $posCart->products = [];
            $posCart->save();
        }

        return $posCart;
    }

    public function customProductForm(Schema $schema): Schema
    {
        return $schema
            ->schema([
                TextInput::make('name')
                    ->label('Productnaam')
                    ->required()
                    ->autofocus()
                    ->columnSpanFull(),

                NumpadField::make('price')
                    ->label('Prijs')
                    ->minCents(0)
                    ->maxCents(9999999)
                    ->required()
                    ->columnSpanFull(),

                Quantity::make('quantity')
                    ->label('Aantal')
                    ->numeric()
                    ->minValue(1)
                    ->maxValue(999999)
                    ->inputMode('numeric')
                    ->required()
                    ->default(1)
                    ->prefix('x'),

                Quantity::make('vat_rate')
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

                NumpadField::make('productToChange.singlePrice')
                    ->label('Prijs')
                    ->minCents(0)
                    ->maxCents(999999)
                    ->required()
                    ->columnSpanFull(),
            ])
            ->columns(2);
    }

    public function submitChangeProductForm()
    {
        $this->changeProductForm->validate();

        $posCart = $this->getActivePosCart();
        $products = $posCart->products ?? [];

        foreach ($products as &$product) {
            if (($product['identifier'] ?? null) === ($this->productToChange['identifier'] ?? null)) {
                $qty = max(1, (int) ($product['quantity'] ?? 1));
                $single = (float) ($this->productToChange['singlePrice'] ?? 0);

                $product['singlePrice'] = $single;
                $product['price'] = $single * $qty;

                // ✅ line price formatted (was vroeger single price formatted)
                $product['priceFormatted'] = CurrencyHelper::formatPrice($product['price']);
                $product['isCustomPrice'] = true;
            }
        }

        $posCart->products = array_values($products);
        $posCart->save();

        $this->productToChange = [];

        $this->dispatch('productChanged');
        $this->dispatch('resetNumpad');
    }

    public function submitCustomProductForm()
    {
        $this->customProductForm->validate();

        $qty = max(1, (int) ($this->customProductData['quantity'] ?? 1));
        $single = (float) ($this->customProductData['price'] ?? 0);
        $vat = (float) ($this->customProductData['vat_rate'] ?? 21);

        $product = [
            'id' => null,
            'product' => null,
            'name' => (string) ($this->customProductData['name'] ?? ''),
            'quantity' => $qty,
            'singlePrice' => $single,
            'price' => $single * $qty,
            'priceFormatted' => CurrencyHelper::formatPrice($single * $qty),

            // ✅ consistent key
            'vat_rate' => $vat,

            'customProduct' => true,
            'isCustomPrice' => true, // custom = altijd custom price
            'extra' => [],
            'identifier' => Str::random(),
            'customId' => 'custom-' . rand(1, 10000000),
        ];

        $posCart = $this->getActivePosCart();
        $products = $posCart->products ?? [];
        $products[] = $product;

        $posCart->products = array_values($products);
        $posCart->save();

        $this->customProductData = [
            'name' => '',
            'quantity' => 1,
            'vat_rate' => 21,
            'price' => 0,
        ];

        $this->dispatch('addCustomProduct', $product);
        $this->dispatch('resetNumpad');

        Notification::make()
            ->body('Aangepast product toegevoegd')
            ->success()
            ->send();
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
                    ->required(),

                TextInput::make('note')
                    ->label('Reden voor korting')
                    ->visible(fn (Get $get) => $get('type') !== 'discountCode')
                    ->reactive(),

                NumpadField::make('amount')
                    ->label('Prijs')
                    ->minCents(0)
                    ->maxCents(999999)
                    ->required()
                    ->reactive()
                    ->visible(fn (Get $get) => $get('type') === 'amount')
                    ->helperText('Bij opslaan wordt er een kortingscode gemaakt die 30 minuten geldig is.'),

                TextInput::make('percentage')
                    ->label('Percentage')
                    ->numeric()
                    ->minValue(0)
                    ->maxValue(100)
                    ->inputMode('numeric')
                    ->autofocus()
                    ->required()
                    ->default(10)
                    ->prefix('%')
                    ->reactive()
                    ->visible(fn (Get $get) => $get('type') === 'percentage')
                    ->helperText('Bij opslaan wordt er een kortingscode gemaakt die 30 minuten geldig is.'),

                Select::make('discountCode')
                    ->label('Kortingscode')
                    ->preload()
                    ->searchable()
                    ->options(function () {
                        $discountCodes = DiscountCode::usable()->get();
                        $options = [];

                        foreach ($discountCodes as $discountCode) {
                            $value = $discountCode->type === 'amount'
                                ? CurrencyHelper::formatPrice($discountCode->discount_amount)
                                : ($discountCode->discount_percentage . '%');

                            $options[$discountCode->id] = $discountCode->name . ' (' . $discountCode->code . ') (' . $value . ')';
                        }

                        return $options;
                    })
                    ->required()
                    ->visible(fn (Get $get) => $get('type') === 'discountCode'),
            ])
            ->statePath('createDiscountData');
    }

    public function submitCreateDiscountForm()
    {
        $posCart = $this->getActivePosCart();

        if (! ($posCart->products ?? [])) {
            Notification::make()
                ->title('Geen producten in winkelmand')
                ->danger()
                ->send();

            $this->createDiscountPopup = false;

            return;
        }

        $this->createDiscountForm->validate();

        if (($this->createDiscountData['type'] ?? '') === 'discountCode') {
            $discountCode = DiscountCode::find($this->createDiscountData['discountCode'] ?? null);
        } else {
            $discountCode = new DiscountCode();
            $discountCode->site_ids = [Sites::getActive()];
            $discountCode->name = 'Point of Sale discount';
            $discountCode->note = $this->createDiscountData['note'] ?? '';
            $discountCode->code = '*****-*****-*****-*****-*****';
            $discountCode->type = $this->createDiscountData['type'];

            $field = 'discount_' . $this->createDiscountData['type'];
            $discountCode->{$field} = $this->createDiscountData[$this->createDiscountData['type']] ?? null;

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

            return;
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

        $this->dispatch('resetNumpad');
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

                                if (! $state) {
                                    Notification::make()->title('Selecteer eerst een gebruiker')->danger()->send();

                                    return;
                                }

                                $user = User::find($state);

                                if (! $user) {
                                    Notification::make()->title('Gebruiker niet gevonden')->danger()->send();

                                    return;
                                }

                                $lastOrder = $user->lastOrderFromAllOrders();
                                if (! $lastOrder) {
                                    Notification::make()->title('Geen eerdere bestelling gevonden voor deze gebruiker')->warning()->send();

                                    return;
                                }

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

                                Notification::make()->title('Gegevens van laatste bestelling geladen')->success()->send();
                            })
                    )
                    ->helperText('Selecteer een account om de bestelling aan te koppelen'),

                TextInput::make('firstName')->label('Voornaam')->maxLength(255),
                TextInput::make('lastName')->label('Achternaam')->maxLength(255),

                TextInput::make('email')
                    ->label('Email')
                    ->type('email')
                    ->email()
                    ->minLength(4)
                    ->maxLength(255),

                TextInput::make('phoneNumber')->label('Telefoon nummer')->maxLength(255),

                TextInput::make('street')
                    ->label('Straat')
                    ->maxLength(255)
                    ->lazy()
                    ->reactive(),

                TextInput::make('houseNr')
                    ->label('Huisnummer')
                    ->nullable()
                    ->required(fn (Get $get) => (bool) $get('street'))
                    ->maxLength(255),

                TextInput::make('zipCode')
                    ->label('Postcode')
                    ->required(fn (Get $get) => (bool) $get('street'))
                    ->nullable()
                    ->maxLength(255),

                TextInput::make('city')
                    ->label('Stad')
                    ->required(fn (Get $get) => (bool) $get('street'))
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

                TextInput::make('company')->label('Bedrijfsnaam')->maxLength(255),
                TextInput::make('btwId')->label('BTW id')->maxLength(255),

                TextInput::make('invoiceStreet')
                    ->label('Factuur straat')
                    ->nullable()
                    ->maxLength(255)
                    ->reactive(),

                // ✅ FIX: required check gebruikt nu invoiceStreet (niet invoice_street)
                TextInput::make('invoiceHouseNr')
                    ->label('Factuur huisnummer')
                    ->required(fn (Get $get) => (bool) $get('invoiceStreet'))
                    ->nullable()
                    ->maxLength(255),

                TextInput::make('invoiceZipCode')
                    ->label('Factuur postcode')
                    ->required(fn (Get $get) => (bool) $get('invoiceStreet'))
                    ->nullable()
                    ->maxLength(255),

                TextInput::make('invoiceCity')
                    ->label('Factuur stad')
                    ->required(fn (Get $get) => (bool) $get('invoiceStreet'))
                    ->nullable()
                    ->maxLength(255),

                Select::make('invoiceCountry')
                    ->label('Factuur land')
                    ->required(fn (Get $get) => (bool) $get('invoiceStreet'))
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
                'country' => $this->country ?: (Countries::getAllSelectedCountries()[0] ?? 'NL'),
            ])
            ->columns(2);
    }

    public function submitCustomerDataForm()
    {
        $posCart = $this->getActivePosCart();

        $this->customerDataForm->validate();

        $posCart->customer_user_id = $this->customerUserId ?: null;
        $posCart->first_name = $this->firstName ?: null;
        $posCart->last_name = $this->lastName ?: null;
        $posCart->phone_number = $this->phoneNumber ?: null;
        $posCart->email = $this->email ?: null;

        $posCart->street = $this->street ?: null;
        $posCart->house_nr = $this->houseNr ?: null;
        $posCart->zip_code = $this->zipCode ?: null;
        $posCart->city = $this->city ?: null;
        $posCart->country = $this->country ?: (Countries::getAllSelectedCountries()[0] ?? 'NL');

        $posCart->company = $this->company ?: null;
        $posCart->btw_id = $this->btwId ?: null;

        $posCart->invoice_street = $this->invoiceStreet ?: null;
        $posCart->invoice_house_nr = $this->invoiceHouseNr ?: null;
        $posCart->invoice_zip_code = $this->invoiceZipCode ?: null;
        $posCart->invoice_city = $this->invoiceCity ?: null;
        $posCart->invoice_country = $this->invoiceCountry ?: null;

        $posCart->note = $this->note ?: null;
        $posCart->custom_fields = $this->customFields ?: [];

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
