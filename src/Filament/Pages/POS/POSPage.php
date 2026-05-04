<?php

namespace Dashed\DashedEcommerceCore\Filament\Pages\POS;

use Carbon\Carbon;
use Livewire\Component;
use Filament\Actions\Action;
use Filament\Schemas\Schema;
use Dashed\DashedCore\Models\User;
use Dashed\DashedCore\Classes\Sites;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Actions\Contracts\HasActions;
use Filament\Schemas\Contracts\HasSchemas;
use LaraZeus\Quantity\Components\Quantity;
use Dashed\DashedCore\Models\Customsetting;
use Dashed\DashedEcommerceCore\Models\Order;
use Dashed\DashedEcommerceCore\Models\POSCart;
use DashedDEV\FilamentNumpadField\NumpadField;
use Filament\Schemas\Components\Utilities\Get;
use Dashed\DashedEcommerceCore\Classes\Countries;
use Dashed\DashedEcommerceCore\Models\DiscountCode;
use Filament\Actions\Concerns\InteractsWithActions;
use Filament\Schemas\Concerns\InteractsWithSchemas;
use Dashed\DashedEcommerceCore\Classes\CurrencyHelper;
// Belangrijk: in je controller gebruikte je Dashed\DashedCore\Models\User.
// Hier stond App\Models\User. Dat kan, maar kies 1.
// Ik trek ‘m gelijk met jullie core.
use Dashed\DashedEcommerceCore\Classes\ConceptOrderService;

class POSPage extends Component implements HasActions, HasSchemas
{
    use InteractsWithActions;
    use InteractsWithSchemas;

    public $searchQueryInputmode = false;

    // Wordt nog door frontend gebruikt als label, maar DB is leidend
    public $cartInstance = 'handorder';

    public $orderOrigin = 'pos';

    public $customerUserId = '';

    public $loadFromOrderId = '';

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

    public $productToChange = [
        'singlePrice' => 0,
    ];

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

    public bool $priceModeOverridden = false;

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

        return \DB::transaction(function () use ($userId) {
            $posCart = POSCart::where('user_id', $userId)
                ->where('status', 'active')
                ->lockForUpdate()
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
        });
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
                    ->label(fn () => $this->getActivePosCart()->prices_ex_vat ? 'Prijs (ex BTW)' : 'Prijs (incl BTW)')
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
                TextInput::make('name')
                    ->label('Productnaam')
                    ->required()
                    ->disabled()
                    ->columnSpanFull(),

                NumpadField::make('singlePrice')
                    ->label(fn () => $this->getActivePosCart()->prices_ex_vat ? 'Prijs (ex BTW)' : 'Prijs (incl BTW)')
                    ->minCents(0)
                    ->maxCents(999999)
                    ->required()
                    ->columnSpanFull(),
            ])
            ->columns(2)
            ->statePath('productToChange');
    }

    public function openChangeProductForm(array $product): void
    {
        $posCart = $this->getActivePosCart();
        $storedSingleIncl = (float) ($product['singlePrice'] ?? 0);
        $vatRate = (float) ($product['vat_rate'] ?? 21);

        $displaySingle = $posCart->prices_ex_vat
            ? \Dashed\DashedEcommerceCore\Classes\VatDisplay::exFromIncl($storedSingleIncl, $vatRate)
            : $storedSingleIncl;

        $this->productToChange = [
            'identifier' => $product['identifier'] ?? null,
            'name' => $product['name'] ?? '',
            'singlePrice' => round($displaySingle, 2),
            'quantity' => (int) ($product['quantity'] ?? 1),
            'vat_rate' => $vatRate,
        ];

        $this->changeProductForm->fill($this->productToChange);
    }

    public function getHeaderActions(): array
    {
        $posCart = $this->getActivePosCart();
        $exMode = (bool) ($posCart->prices_ex_vat ?? false);

        return [
            Action::make('togglePriceMode')
                ->label($exMode ? 'Prijzen: ex BTW' : 'Prijzen: incl BTW')
                ->icon($exMode ? 'heroicon-o-receipt-percent' : 'heroicon-o-currency-euro')
                ->color($exMode ? 'warning' : 'gray')
                ->button()
                ->action('togglePriceMode'),
        ];
    }

    public function togglePriceMode(): void
    {
        $posCart = $this->getActivePosCart();
        $posCart->prices_ex_vat = ! (bool) $posCart->prices_ex_vat;
        $posCart->save();

        $this->priceModeOverridden = true;

        Notification::make()
            ->title($posCart->prices_ex_vat ? __('Prijzen tonen ex BTW') : __('Prijzen tonen incl BTW'))
            ->success()
            ->send();

        $this->dispatch('price-mode-toggled');
    }

    public function submitChangeProductForm()
    {
        $this->changeProductForm->validate();

        $posCart = $this->getActivePosCart();
        $products = $posCart->products ?? [];

        foreach ($products as &$product) {
            if (($product['identifier'] ?? null) === ($this->productToChange['identifier'] ?? null)) {
                $qty = max(1, (int) ($product['quantity'] ?? 1));
                $enteredSingle = (float) ($this->productToChange['singlePrice'] ?? 0);
                $vatRate = (float) ($product['vat_rate'] ?? 21);

                // Cart always stores incl-VAT unit prices; convert from ex when the cashier entered ex.
                $singleIncl = $posCart->prices_ex_vat
                    ? $enteredSingle * (1 + max(0.0, $vatRate) / 100)
                    : $enteredSingle;

                $product['singlePrice'] = $singleIncl;
                $product['price'] = $singleIncl * $qty;

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
                                : ($discountCode->discount_percentage.'%');

                            $options[$discountCode->id] = $discountCode->name.' ('.$discountCode->code.') ('.$value.')';
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

            $field = 'discount_'.$this->createDiscountData['type'];
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
                    ->searchable()
                    ->preload()
                    ->getSearchResultsUsing(function (string $search) {
                        return User::where(function ($q) use ($search) {
                            $q->where('first_name', 'like', "%{$search}%")
                                ->orWhere('last_name', 'like', "%{$search}%")
                                ->orWhere('email', 'like', "%{$search}%");
                        })
                            ->limit(50)
                            ->get()
                            ->mapWithKeys(fn ($user) => [
                                $user->id => $user->name.($user->name !== $user->email ? ' ('.$user->email.')' : ''),
                            ])
                            ->toArray();
                    })
                    ->getOptionLabelUsing(fn ($value) => optional(User::find($value))->name)
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

                                $lastOrder = method_exists($user, 'lastOrderFromAllOrders')
                                    ? $user->lastOrderFromAllOrders()
                                    : null;

                                // Per-field fallback: prefer the last order's value, fall back to
                                // the user's own account fields when there is no order (or the
                                // order has the value empty). User column naming matches except
                                // `company`/`tax_id` which map to the order's `company_name`/`btw_id`.
                                $pick = fn (?string $orderValue, mixed $userValue) => $orderValue !== null && $orderValue !== ''
                                    ? $orderValue
                                    : ($userValue ?: null);

                                $this->firstName = $pick($lastOrder?->first_name, $user->first_name);
                                $this->lastName = $pick($lastOrder?->last_name, $user->last_name);
                                $this->email = $pick($lastOrder?->email, $user->email);
                                $this->phoneNumber = $pick($lastOrder?->phone_number, $user->phone_number);
                                $this->street = $pick($lastOrder?->street, $user->street);
                                $this->houseNr = $pick($lastOrder?->house_nr, $user->house_nr);
                                $this->zipCode = $pick($lastOrder?->zip_code, $user->zip_code);
                                $this->city = $pick($lastOrder?->city, $user->city);
                                $this->country = $pick($lastOrder?->country, $user->country);
                                $this->company = $pick($lastOrder?->company_name, $user->company);
                                $this->btwId = $pick($lastOrder?->btw_id, $user->tax_id);

                                $this->invoiceStreet = $pick($lastOrder?->invoice_street, $user->invoice_street);
                                $this->invoiceHouseNr = $pick($lastOrder?->invoice_house_nr, $user->invoice_house_nr);
                                $this->invoiceZipCode = $pick($lastOrder?->invoice_zip_code, $user->invoice_zip_code);
                                $this->invoiceCity = $pick($lastOrder?->invoice_city, $user->invoice_city);
                                $this->invoiceCountry = $pick($lastOrder?->invoice_country, $user->invoice_country);

                                Notification::make()
                                    ->title($lastOrder
                                        ? 'Gegevens van laatste bestelling geladen'
                                        : 'Gegevens uit accountprofiel geladen (geen eerdere bestelling gevonden)')
                                    ->success()
                                    ->send();
                            })
                    )
                    ->helperText('Selecteer een account om de bestelling aan te koppelen'),

                Select::make('loadFromOrderId')
                    ->label('Gegevens uit bestelling kopiëren')
                    ->columnSpanFull()
                    ->searchable()
                    ->placeholder('Zoek een bestelling op naam, e-mail of factuurnummer')
                    ->getSearchResultsUsing(function (string $search) {
                        return Order::query()
                            ->where(function ($q) use ($search) {
                                $q->where('first_name', 'like', "%{$search}%")
                                    ->orWhere('last_name', 'like', "%{$search}%")
                                    ->orWhere('email', 'like', "%{$search}%")
                                    ->orWhere('invoice_id', 'like', "%{$search}%")
                                    ->orWhere('company_name', 'like', "%{$search}%")
                                    ->orWhere('phone_number', 'like', "%{$search}%");
                            })
                            ->orderByDesc('created_at')
                            ->limit(50)
                            ->get()
                            ->mapWithKeys(function ($order) {
                                $name = trim(($order->first_name ?: '').' '.($order->last_name ?: ''));
                                $label = '#'.($order->invoice_id ?: $order->id);
                                if ($name !== '') {
                                    $label .= ' - '.$name;
                                }
                                if ($order->email) {
                                    $label .= ' ('.$order->email.')';
                                }

                                return [$order->id => $label];
                            })
                            ->toArray();
                    })
                    ->getOptionLabelUsing(function ($value) {
                        $order = Order::find($value);
                        if (! $order) {
                            return null;
                        }
                        $name = trim(($order->first_name ?: '').' '.($order->last_name ?: ''));
                        $label = '#'.($order->invoice_id ?: $order->id);

                        return $name !== '' ? $label.' - '.$name : $label;
                    })
                    ->suffixAction(
                        Action::make('loadFromOrder')
                            ->label('Gegevens invoeren')
                            ->icon('heroicon-m-clipboard-document-list')
                            ->action(function (Get $get) {
                                $state = $get('loadFromOrderId');
                                if (! $state) {
                                    Notification::make()->title('Selecteer eerst een bestelling')->danger()->send();

                                    return;
                                }

                                $order = Order::find($state);
                                if (! $order) {
                                    Notification::make()->title('Bestelling niet gevonden')->danger()->send();

                                    return;
                                }

                                $pick = fn (?string $value, mixed $current) => $value !== null && $value !== ''
                                    ? $value
                                    : ($current ?: null);

                                $this->firstName = $pick($order->first_name, $this->firstName);
                                $this->lastName = $pick($order->last_name, $this->lastName);
                                $this->email = $pick($order->email, $this->email);
                                $this->phoneNumber = $pick($order->phone_number, $this->phoneNumber);
                                $this->street = $pick($order->street, $this->street);
                                $this->houseNr = $pick($order->house_nr, $this->houseNr);
                                $this->zipCode = $pick($order->zip_code, $this->zipCode);
                                $this->city = $pick($order->city, $this->city);
                                $this->country = $pick($order->country, $this->country);
                                $this->company = $pick($order->company_name, $this->company);
                                $this->btwId = $pick($order->btw_id, $this->btwId);

                                $this->invoiceStreet = $pick($order->invoice_street, $this->invoiceStreet);
                                $this->invoiceHouseNr = $pick($order->invoice_house_nr, $this->invoiceHouseNr);
                                $this->invoiceZipCode = $pick($order->invoice_zip_code, $this->invoiceZipCode);
                                $this->invoiceCity = $pick($order->invoice_city, $this->invoiceCity);
                                $this->invoiceCountry = $pick($order->invoice_country, $this->invoiceCountry);

                                Notification::make()
                                    ->title('Gegevens van bestelling #'.($order->invoice_id ?: $order->id).' geladen')
                                    ->success()
                                    ->send();
                            })
                    )
                    ->helperText('Zoek een eerdere bestelling om alle klantgegevens snel te kopiëren. Koppelt de bestelling niet aan een account.'),

                TextInput::make('firstName')->label('Voornaam')->maxLength(255),
                TextInput::make('lastName')->label('Achternaam')->maxLength(255),

                TextInput::make('email')
                    ->label('Email')
                    ->type('email')
                    ->email()
                    ->minLength(4)
                    ->maxLength(255),

                TextInput::make('phoneNumber')->label('Telefoon nummer')->maxLength(255),

                TextInput::make('zipCode')
                    ->label('Postcode')
                    ->nullable()
                    ->maxLength(255)
                    ->live(onBlur: true)
                    ->afterStateUpdated(function ($state, $set, $get) {
                        $address = \Dashed\DashedEcommerceCore\Services\Address\AddressLookup::lookup($state, $get('houseNr'));
                        if (! empty($address['street'])) {
                            $set('street', $address['street']);
                        }
                        if (! empty($address['city'])) {
                            $set('city', $address['city']);
                        }
                    }),

                TextInput::make('houseNr')
                    ->label('Huisnummer')
                    ->nullable()
                    ->maxLength(255)
                    ->live(onBlur: true)
                    ->afterStateUpdated(function ($state, $set, $get) {
                        $address = \Dashed\DashedEcommerceCore\Services\Address\AddressLookup::lookup($get('zipCode'), $state);
                        if (! empty($address['street'])) {
                            $set('street', $address['street']);
                        }
                        if (! empty($address['city'])) {
                            $set('city', $address['city']);
                        }
                    }),

                TextInput::make('street')
                    ->label('Straat')
                    ->maxLength(255)
                    ->lazy()
                    ->reactive(),

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

                TextInput::make('invoiceZipCode')
                    ->label('Factuur postcode')
                    ->nullable()
                    ->maxLength(255)
                    ->live(onBlur: true)
                    ->afterStateUpdated(function ($state, $set, $get) {
                        $address = \Dashed\DashedEcommerceCore\Services\Address\AddressLookup::lookup($state, $get('invoiceHouseNr'));
                        if (! empty($address['street'])) {
                            $set('invoiceStreet', $address['street']);
                        }
                        if (! empty($address['city'])) {
                            $set('invoiceCity', $address['city']);
                        }
                    }),

                TextInput::make('invoiceHouseNr')
                    ->label('Factuur huisnummer')
                    ->nullable()
                    ->maxLength(255)
                    ->live(onBlur: true)
                    ->afterStateUpdated(function ($state, $set, $get) {
                        $address = \Dashed\DashedEcommerceCore\Services\Address\AddressLookup::lookup($get('invoiceZipCode'), $state);
                        if (! empty($address['street'])) {
                            $set('invoiceStreet', $address['street']);
                        }
                        if (! empty($address['city'])) {
                            $set('invoiceCity', $address['city']);
                        }
                    }),

                TextInput::make('invoiceStreet')
                    ->label('Factuur straat')
                    ->nullable()
                    ->maxLength(255)
                    ->reactive(),

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

        if (! $this->priceModeOverridden) {
            $customer = $this->customerUserId
                ? User::find($this->customerUserId)
                : null;

            $shouldShowEx = (bool) ($customer->show_prices_ex_vat ?? false);

            if ($shouldShowEx !== (bool) $posCart->prices_ex_vat) {
                $posCart->prices_ex_vat = $shouldShowEx;
                $posCart->save();

                Notification::make()
                    ->title($shouldShowEx
                        ? __('Ex BTW modus ingeschakeld voor deze klant')
                        : __('Incl BTW modus ingeschakeld voor deze klant'))
                    ->success()
                    ->send();
            }
        }

        $this->dispatch('saveCustomerData');
    }

    public function saveAsConceptAction(): Action
    {
        return Action::make('saveAsConcept')
            ->label(__('Opslaan als concept'))
            ->icon('heroicon-o-bookmark')
            ->color('gray')
            ->requiresConfirmation()
            ->modalHeading(__('Huidige cart opslaan als concept?'))
            ->action(function () {
                $posCart = $this->getActivePosCart();

                if (empty($posCart->products ?? [])) {
                    Notification::make()
                        ->title(__('Geen producten in de cart'))
                        ->danger()
                        ->send();

                    return;
                }

                $existingConcept = $posCart->loaded_concept_order_id
                    ? Order::find($posCart->loaded_concept_order_id)
                    : null;

                ConceptOrderService::saveAsConcept(
                    $posCart,
                    auth()->user(),
                    $existingConcept,
                );

                $posCart->refresh();
                $posCart->loaded_concept_order_id = null;
                $posCart->save();

                Notification::make()
                    ->title($existingConcept ? __('Concept bijgewerkt') : __('Concept opgeslagen'))
                    ->success()
                    ->send();

                $this->redirect(request()->header('Referer') ?? url()->current());
            });
    }

    public function conceptQueueAction(): Action
    {
        $count = Order::concept()->count();

        return Action::make('conceptQueue')
            ->label(__('Concepten (:count)', ['count' => $count]))
            ->icon('heroicon-o-queue-list')
            ->modalHeading(__('Concept wachtrij'))
            ->modalContent(fn () => view('dashed-ecommerce-core::pos.partials.concept-queue-modal'))
            ->modalSubmitAction(false)
            ->modalCancelActionLabel(__('Sluiten'));
    }

    public function loadConcept(int $orderId): void
    {
        $order = Order::concept()->find($orderId);

        if (! $order) {
            Notification::make()
                ->title(__('Concept bestaat niet meer, ververs de wachtrij'))
                ->danger()
                ->send();

            return;
        }

        $posCart = $this->getActivePosCart();
        $posCart->products = [];
        $posCart->loaded_concept_order_id = $order->id;
        $posCart->save();

        ConceptOrderService::hydrate($posCart, $order);

        Notification::make()
            ->title(__('Concept geladen'))
            ->success()
            ->send();

        $this->redirect(request()->header('Referer') ?? url()->current());
    }

    public function cancelConcept(int $orderId): void
    {
        $order = Order::concept()->find($orderId);
        if (! $order) {
            return;
        }

        ConceptOrderService::cancel($order);

        Notification::make()
            ->title(__('Concept verwijderd'))
            ->success()
            ->send();

        $this->dispatch('$refresh');
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
