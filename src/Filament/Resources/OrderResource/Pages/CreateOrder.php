<?php

namespace Dashed\DashedEcommerceCore\Filament\Resources\OrderResource\Pages;

use Carbon\Carbon;
use Filament\Forms\Get;
use Filament\Actions\Action;
use Dashed\DashedCore\Models\User;
use Filament\Resources\Pages\Page;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\HtmlString;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Toggle;
use Filament\Forms\Components\Wizard;
use Illuminate\Support\Facades\Blade;
use Filament\Forms\Components\Section;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Placeholder;
use Dashed\DashedCore\Models\Customsetting;
use Dashed\DashedEcommerceCore\Models\Order;
use Dashed\DashedEcommerceCore\Models\Product;
use Dashed\DashedEcommerceCore\Models\OrderLog;
use Dashed\DashedTranslations\Models\Translation;
use Dashed\DashedEcommerceCore\Models\DiscountCode;
use Dashed\DashedEcommerceCore\Models\OrderPayment;
use Dashed\DashedEcommerceCore\Models\OrderProduct;
use Dashed\DashedEcommerceCore\Models\ProductExtra;
use Dashed\DashedEcommerceCore\Classes\ShoppingCart;
use Dashed\DashedEcommerceCore\Classes\CurrencyHelper;
use Dashed\DashedEcommerceCore\Models\ProductExtraOption;
use Dashed\DashedEcommerceCore\Filament\Resources\OrderResource;

class CreateOrder extends Page
{
    protected static string $resource = OrderResource::class;
    protected static ?string $title = 'Bestelling aanmaken';
    protected static string $view = 'dashed-ecommerce-core::orders.create-order';

    public $loading = false;

    public $subTotal = 0;
    public $discount = 0;
    public $vat = 0;
    public $total = 0;
    public $totalUnformatted = 0;

    public $user_id;
    public $marketing;
    public $password;
    public $password_confirmation;
    public $first_name;
    public $last_name;
    public $email;
    public $phone_number;
    public $date_of_birth;
    public $gender;
    public $street;
    public $house_nr;
    public $zip_code;
    public $city;
    public $country;
    public $company_name;
    public $btw_id;
    public $invoice_street;
    public $invoice_house_nr;
    public $invoice_zip_code;
    public $invoice_city;
    public $invoice_country;
    public $note;
    public $discount_code;
    public ?string $activeDiscountCode = '';
    public $orderProducts = [];
    public $shipping_method_id;
    public $payment_method_id;
    public $products = [];
    public $activatedProducts = [];

    public function mount()
    {
        ShoppingCart::setInstance('handorder');
    }

    protected function getActions(): array
    {
        return [
            Action::make('updateInfo')
                ->label('Gegevens bijwerken')
                ->action(fn () => $this->updateInfo()),
        ];
    }

    public function getUsersProperty()
    {
        $allUsers = DB::table('users')->select('first_name', 'last_name', 'email', 'id')->orderBy('last_name')->orderBy('email')->get();
        $users = [];
        foreach ($allUsers as $user) {
            $users[$user->id] = $user->first_name || $user->last_name ? "$user->first_name $user->last_name" : $user->email;
        }

        return $users;
    }

    public function getAllProductsProperty()
    {
        $products = Product::handOrderShowable()->with(['childProducts', 'parent'])->get();

        foreach ($products as &$product) {
            $product['stock'] = $product->stock();
            $product['price'] = CurrencyHelper::formatPrice($product->price);
            $product['productExtras'] = $product->allProductExtras();
        }

        return $products;
    }

    public function getProductExtrasSchema(Product $product): array
    {
        $productExtras = [];

        foreach ($product->allProductExtras() as $extra) {
            $extraOptions = [];
            foreach ($extra->ProductExtraOptions as $option) {
                $extraOptions[$option->id] = $option->value . ' (+ ' . CurrencyHelper::formatPrice($option->price) . ')';
            }

            $productExtras[] = Select::make('extra.' . $extra['id'])
                ->label($extra->name)
                ->options($extraOptions)
                ->required($extra['required']);
        }

        return $productExtras;
    }

    protected function getFormSchema(): array
    {
        $schema = [];

        $schema[] = Wizard\Step::make('Persoonlijke informatie')
            ->schema([
                Select::make('user_id')
                    ->label('Hang de bestelling aan een gebruiker')
                    ->options($this->users)
                    ->searchable()
                    ->reactive(),
                Toggle::make('marketing')
                    ->label('De klant accepteert marketing'),
                TextInput::make('password')
                    ->label('Wachtwoord')
                    ->type('password')
                    ->nullable()
                    ->minLength(6)
                    ->maxLength(255)
                    ->confirmed()
                    ->visible(fn (Get $get) => ! $get('user_id')),
                TextInput::make('password_confirmation')
                    ->label('Wachtwoord herhalen')
                    ->type('password')
                    ->nullable()
                    ->minLength(6)
                    ->maxLength(255)
                    ->confirmed()
                    ->visible(fn (Get $get) => ! $get('user_id')),
                TextInput::make('first_name')
                    ->label('Voornaam')
                    ->nullable()
                    ->maxLength(255),
                TextInput::make('last_name')
                    ->label('Achternaam')
                    ->required()
                    ->nullable()
                    ->maxLength(255),
                DatePicker::make('date_of_birth')
                    ->label('Geboortedatum')
                    ->nullable()
                    ->date(),
                Select::make('gender')
                    ->label('Geslacht')
                    ->options([
                        '' => 'Niet gekozen',
                        'm' => 'Man',
                        'f' => 'Vrouw',
                    ]),
                TextInput::make('email')
                    ->label('Email')
                    ->type('email')
                    ->required()
                    ->email()
                    ->minLength(4)
                    ->maxLength(255),
                TextInput::make('phone_number')
                    ->label('Telefoon nummer')
                    ->maxLength(255),
            ])
            ->columns(2);

        $schema[] = Wizard\Step::make('Adres')
            ->schema([
                TextInput::make('street')
                    ->label('Straat')
                    ->nullable()
                    ->maxLength(255)
                    ->lazy()
                    ->reactive(),
                TextInput::make('house_nr')
                    ->label('Huisnummer')
                    ->nullable()
                    ->required(fn (Get $get) => $get('street'))
                    ->maxLength(255),
                TextInput::make('zip_code')
                    ->label('Postcode')
                    ->required(fn (Get $get) => $get('street'))
                    ->nullable()
                    ->maxLength(255),
                TextInput::make('city')
                    ->label('Stad')
                    ->required(fn (Get $get) => $get('street'))
                    ->nullable()
                    ->maxLength(255),
                TextInput::make('country')
                    ->label('Land')
                    ->required()
                    ->nullable()
                    ->maxLength(255)
                    ->lazy(),
                TextInput::make('company_name')
                    ->label('Bedrijfsnaam')
                    ->maxLength(255),
                TextInput::make('btw_id')
                    ->label('BTW id')
                    ->maxLength(255),
                TextInput::make('invoice_street')
                    ->label('Factuur straat')
                    ->nullable()
                    ->maxLength(255)
                    ->reactive(),
                TextInput::make('invoice_house_nr')
                    ->label('Factuur huisnummer')
                    ->required(fn (Get $get) => $get('invoice_street'))
                    ->nullable()
                    ->maxLength(255),
                TextInput::make('invoice_zip_code')
                    ->label('Factuur postcode')
                    ->required(fn (Get $get) => $get('invoice_street'))
                    ->nullable()
                    ->maxLength(255),
                TextInput::make('invoice_city')
                    ->label('Factuur stad')
                    ->required(fn (Get $get) => $get('invoice_street'))
                    ->nullable()
                    ->maxLength(255),
                TextInput::make('invoice_country')
                    ->label('Factuur land')
                    ->required(fn (Get $get) => $get('invoice_street'))
                    ->nullable()
                    ->maxLength(255),
            ])
            ->columns(2);

        $schema[] = Wizard\Step::make('Producten')
            ->schema([
                Repeater::make('products')
                    ->label('Kies producten')
                    ->helperText('Check goed of de producten op voorraad zijn. Als een product niet op voorraad is, wordt hij bij stap 5 wel meegeteld, maar niet aangemaakt in de bestelling.')
                    ->schema([
                        Select::make('product')
                            ->label('Kies product')
                            ->options(Product::handOrderShowable()->pluck('name', 'id'))
                            ->searchable()
                            ->reactive(),
                        TextInput::make('quantity')
                            ->label('Aantal')
                            ->numeric()
                            ->required()
                            ->minValue(0)
                            ->maxValue(1000)
                            ->default(0),
                        Placeholder::make('Voorraad')
                            ->content(fn (Get $get) => $get('product') ? Product::find($get('product'))->stock() : 'Kies een product'),
                        Placeholder::make('Prijs')
                            ->content(fn (Get $get) => $get('product') ? Product::find($get('product'))->currentPrice : 'Kies een product'),
                        Placeholder::make('Afbeelding')
                            ->content(fn (Get $get) => $get('product') ? new HtmlString('<img width="300" src="' . mediaHelper()->getSingleImage(Product::find($get('product'))->firstImageUrl, 'medium') . '">') : 'Kies een product'),
                        Section::make('Extra\'s')
                            ->schema(fn (Get $get) => $get('product') ? $this->getProductExtrasSchema(Product::find($get('product'))) : []),
                    ]),
            ])
            ->columnSpan(2);

        //        $productSchemas = [];
        //
        //        $productSchemas[] = Select::make('activatedProducts')
        //            ->label('Kies producten')
        //            ->helperText('Check goed of de producten op voorraad zijn. Als een product niet op voorraad is, wordt hij bij stap 5 wel meegeteld, maar niet aangemaakt in de bestelling.')
        //            ->options(Product::handOrderShowable()->pluck('name', 'id'))
        //            ->searchable()
        //            ->multiple()
        //            ->reactive();
        //
        //        foreach ($this->getAllProductsProperty() as $product) {
        //            $productExtras = [];

        //            foreach ($product['productExtras'] as $extra) {
        //                $extraOptions = [];
        //                foreach ($extra['product_extra_options'] ?? [] as $option) {
        //                    $option = ProductExtraOption::find($option['id']);
        //                    $extraOptions[$option->id] = $option->value . ' (+ ' . CurrencyHelper::formatPrice($option->price) . ')';
        //                }
        //
        //                $productExtras[] = Select::make('products.' . $product->id . '.extra.' . $extra['id'])
        //                    ->label($extra['name'][array_key_first($extra['name'])])
        //                    ->options($extraOptions)
        //                    ->required($extra['required']);
        //            }

        //            $productSchemas[] = Section::make('Product ' . $product->name)
        //                ->schema(array_merge([
        //                    TextInput::make('products.' . $product->id . '.quantity')
        //                        ->label('Aantal')
        //                        ->numeric()
        //                        ->required()
        //                        ->minValue(0)
        //                        ->maxValue(1000)
        //                        ->default(0),
        //                    Placeholder::make('Voorraad')
        //                        ->content($product->stock()),
        //                    Placeholder::make('Prijs')
        //                        ->content($product->currentPrice),
        //                    Placeholder::make('Afbeelding')
        //                        ->content(new HtmlString('<img width="300" src="' . app(\Dashed\Drift\UrlBuilder::class)->url('dashed', $product->firstImageUrl, []) . '">')),
        //                ], $productExtras))
        //                ->visible(fn(Get $get) => in_array($product->id, $get('activatedProducts')));
        //        }
        //
        //        $schema[] = Wizard\Step::make('Producten')
        //            ->schema($productSchemas)
        //            ->columnSpan(2);

        $schema[] = Wizard\Step::make('Overige informatie')
            ->schema([
                Textarea::make('note')
                    ->label('Notitie')
                    ->nullable()
                    ->maxLength(1500),
                TextInput::make('discount_code')
                    ->label('Kortingscode')
                    ->nullable()
                    ->maxLength(255)
                    ->reactive(),
                Select::make('shipping_method_id')
                    ->label('Verzendmethode')
                    ->required()
                    ->options(function () {
                        return collect(ShoppingCart::getAvailableShippingMethods($this->country, true))->pluck('name', 'id')->toArray();
                    }),
            ]);

        $schema[] = Wizard\Step::make('Bestelling')
            ->schema([
                Placeholder::make('')
                    ->content('Subtotaal: ' . $this->subTotal),
                Placeholder::make('')
                    ->content('Korting: ' . $this->discount),
                Placeholder::make('')
                    ->content('BTW: ' . $this->vat),
                Placeholder::make('')
                    ->content('Totaal: ' . $this->total),
            ]);

        return [
            Wizard::make($schema)
                ->submitAction(new HtmlString(Blade::render(
                    <<<BLADE
    <x-filament::button
        type="submit"
        size="sm"
    >
        Bestelling aanmaken
    </x-filament::button>
BLADE
                ))),
        ];
    }

    public function updateInfo($showNotification = true)
    {
        ShoppingCart::setInstance('handorder');
        ShoppingCart::emptyMyCart();

        $this->loading = true;

        //        foreach (\Cart::instance('handorder')->content() as $row) {
        //            \Cart::remove($row->rowId);
        //        }

        foreach ($this->products as $chosenProduct) {
            $product = Product::find($chosenProduct['product']);
            if (($chosenProduct['quantity'] ?? 0) > 0) {
                $productPrice = $product->getOriginal('price');
                $options = [];
                foreach ($chosenProduct['extra'] ?? [] as $productExtraId => $productExtraOptionId) {
                    if ($productExtraOptionId) {
                        $thisProductExtra = ProductExtra::find($productExtraId);
                        $thisOption = ProductExtraOption::find($productExtraOptionId);
                        if ($thisOption->calculate_only_1_quantity) {
                            $productPrice += ($thisOption->price / $this->products[$product->id]['quantity']);
                        } else {
                            $productPrice += $thisOption->price;
                        }
                        $options[$thisOption->id] = [
                            'name' => $thisProductExtra->name,
                            'value' => $thisOption->value,
                        ];
                    }
                }

                \Cart::instance('handorder')->add($product->id, $product->name, $chosenProduct['quantity'], $productPrice, $options)->associate(Product::class);
            }
        }

        if (! $this->discount_code) {
            session(['discountCode' => '']);
            $this->activeDiscountCode = null;
        } else {
            $discountCode = DiscountCode::usable()->where('code', $this->discount_code)->first();
            if (! $discountCode || ! $discountCode->isValidForCart()) {
                session(['discountCode' => '']);
                $this->activeDiscountCode = null;
            } else {
                session(['discountCode' => $discountCode->code]);

                if ($this->activeDiscountCode != $discountCode->code) {
                    $this->activeDiscountCode = $discountCode->code;
                    $showNotification = false;

                    Notification::make()
                        ->title('Korting toegevoegd, klik nogmaals op "Gegevens bijwerken" om de korting toe te passen')
                        ->success()
                        ->send();
                }
            }
        }

        $shippingMethods = ShoppingCart::getAvailableShippingMethods($this->country);
        $shippingMethod = '';
        foreach ($shippingMethods as $thisShippingMethod) {
            if ($thisShippingMethod['id'] == $this->shipping_method_id) {
                $shippingMethod = $thisShippingMethod;
            }
        }

        if (! $shippingMethod) {
            $this->shipping_method_id = null;
        }

        $checkoutData = ShoppingCart::getCheckoutData($this->shipping_method_id, $this->payment_method_id);

        $this->totalUnformatted = $checkoutData['total'];

        $this->discount = $checkoutData['discountFormatted'];
        $this->vat = $checkoutData['btwFormatted'];
        $this->subTotal = $checkoutData['subTotalFormatted'];
        $this->total = $checkoutData['totalFormatted'];

        if ($showNotification) {
            Notification::make()
                ->title('Informatie bijgewerkt')
                ->success()
                ->send();
        }
        $this->loading = false;
    }

    public function submit()
    {
        $this->updateInfo(false);
        $this->loading = true;
        \Cart::instance('handorder')->content();
        ShoppingCart::removeInvalidItems();

        $cartItems = ShoppingCart::cartItems();
        $checkoutData = ShoppingCart::getCheckoutData($this->shipping_method_id, $this->payment_method_id);

        if (! $cartItems) {
            Notification::make()
                ->title(Translation::get('no-items-in-cart', 'cart', 'You dont have any products in your shopping cart'))
                ->danger()
                ->send();

            return;
        }

        //        $paymentMethods = ShoppingCart::getPaymentMethods();
        //        $paymentMethod = '';
        //        foreach ($paymentMethods as $thisPaymentMethod) {
        //            if ($thisPaymentMethod['id'] == $this->payment_method_id) {
        //                $paymentMethod = $thisPaymentMethod;
        //            }
        //        }

        //        if (!$paymentMethod) {
        //            Notification::make()
        //                ->title(Translation::get('no-valid-payment-method-chosen', 'cart', 'You did not choose a valid payment method'))
        //                ->danger()
        //                ->send();
        //
        //            return;
        //        }

        $shippingMethods = ShoppingCart::getAvailableShippingMethods($this->country);
        $shippingMethod = '';
        foreach ($shippingMethods as $thisShippingMethod) {
            if ($thisShippingMethod['id'] == $this->shipping_method_id) {
                $shippingMethod = $thisShippingMethod;
            }
        }

        if (! $shippingMethod) {
            //            Notification::make()
            //                ->title('Ga een stap terug, klik op "Gegevens bijwerken" en ga door')
            //                ->danger()
            //                ->send();
            Notification::make()
                ->title(Translation::get('no-valid-shipping-method-chosen', 'cart', 'You did not choose a valid shipping method'))
                ->danger()
                ->send();

            return;
        }

        $discountCode = DiscountCode::usable()->where('code', session('discountCode'))->first();

        if (! $discountCode) {
            session(['discountCode' => '']);
            $discountCode = '';
        } elseif ($discountCode && ! $discountCode->isValidForCart($this->email)) {
            session(['discountCode' => '']);

            Notification::make()
                ->title(Translation::get('discount-code-invalid', 'cart', 'The discount code you choose is invalid'))
                ->danger()
                ->send();

            return;
        }

        if (Customsetting::get('checkout_account') != 'disabled' && Auth::guest() && $this->password) {
            if (User::where('email', $this->email)->count()) {
                Notification::make()
                    ->title(Translation::get('email-duplicate-for-user', 'cart', 'The email you chose has already been used to create a account'))
                    ->danger()
                    ->send();

                return;
            }

            $user = new User();
            $user->first_name = $this->first_name;
            $user->last_name = $this->last_name;
            $user->email = $this->email;
            $user->password = Hash::make($this->password);
            $user->save();
        }

        $order = new Order();
        $order->first_name = $this->first_name;
        $order->last_name = $this->last_name;
        $order->email = $this->email;
        $order->gender = $this->gender;
        $order->date_of_birth = $this->date_of_birth ? Carbon::parse($this->date_of_birth) : null;
        $order->phone_number = $this->phone_number;
        $order->street = $this->street;
        $order->house_nr = $this->house_nr;
        $order->zip_code = $this->zip_code;
        $order->city = $this->city;
        $order->country = $this->country;
        $order->marketing = $this->marketing ? 1 : 0;
        $order->company_name = $this->company_name;
        $order->btw_id = $this->btw_id;
        $order->note = $this->note;
        $order->invoice_street = $this->invoice_street;
        $order->invoice_house_nr = $this->invoice_house_nr;
        $order->invoice_zip_code = $this->invoice_zip_code;
        $order->invoice_city = $this->invoice_city;
        $order->invoice_country = $this->invoice_country;
        $order->invoice_id = 'PROFORMA';

        session(['discountCode' => $this->discount_code]);
        $subTotal = ShoppingCart::subtotal(false, $shippingMethod->id, $paymentMethod['id'] ?? null);
        $discount = ShoppingCart::totalDiscount(false, $this->discount_code);
        $btw = ShoppingCart::btw(false, true, $shippingMethod->id, $paymentMethod['id'] ?? null);
        $total = ShoppingCart::total(false, true, $shippingMethod->id, $paymentMethod['id'] ?? null);
        $shippingCosts = 0;
        $paymentCosts = 0;

        if ($shippingMethod->costs > 0) {
            $shippingCosts = $shippingMethod->costs;
        }

        if (isset($paymentMethod['extra_costs']) && $paymentMethod['extra_costs'] > 0) {
            $paymentCosts = $paymentMethod['extra_costs'];
        }

        $order->total = $total;
        $order->subtotal = $subTotal;
        $order->btw = $btw;
        $order->discount = $discount;
        $order->status = 'pending';
        $order->ga_user_id = null;

        if ($discountCode) {
            $order->discount_code_id = $discountCode->id;
        }

        $order->shipping_method_id = $shippingMethod['id'];

        if (isset($user)) {
            $order->user_id = $user->id;
        } else {
            if ($this->user_id) {
                $order->user_id = $this->user_id;
            }
        }

        $order->save();

        $orderContainsPreOrders = false;
        foreach ($cartItems as $cartItem) {
            $orderProduct = new OrderProduct();
            $orderProduct->quantity = $cartItem->qty;
            $orderProduct->product_id = $cartItem->model->id;
            $orderProduct->order_id = $order->id;
            $orderProduct->name = $cartItem->model->name;
            $orderProduct->sku = $cartItem->model->sku;
            $orderProduct->price = $cartItem->model->getShoppingCartItemPrice($cartItem, $discountCode ?? null);
            $orderProduct->discount = $cartItem->model->getShoppingCartItemPrice($cartItem) - $orderProduct->price;
            $productExtras = [];
            foreach ($cartItem->options as $optionId => $option) {
                $productExtras[] = [
                    'id' => $optionId,
                    'name' => $option['name'],
                    'value' => $option['value'],
                    'price' => ProductExtraOption::find($optionId)->price,
                ];
            }
            $orderProduct->product_extras = $productExtras;

            if ($cartItem->model->isPreorderable() && $cartItem->model->stock < $cartItem->qty) {
                $orderProduct->is_pre_order = true;
                $orderProduct->pre_order_restocked_date = $cartItem->model->expected_in_stock_date;
                $orderContainsPreOrders = true;
            }

            $orderProduct->save();
        }

        if ($paymentCosts) {
            $orderProduct = new OrderProduct();
            $orderProduct->quantity = 1;
            $orderProduct->product_id = null;
            $orderProduct->order_id = $order->id;
            $orderProduct->name = $paymentMethod['name'];
            $orderProduct->price = $paymentCosts;
            if ($order->paymentMethod) {
                $orderProduct->btw = ShoppingCart::vatForPaymentMethod($paymentMethod['id']);
            }
            $orderProduct->discount = 0;
            $orderProduct->product_extras = [];
            $orderProduct->sku = 'payment_costs';
            $orderProduct->save();
        }

        if ($shippingCosts) {
            $orderProduct = new OrderProduct();
            $orderProduct->quantity = 1;
            $orderProduct->product_id = null;
            $orderProduct->order_id = $order->id;
            $orderProduct->name = $order->shippingMethod->name;
            $orderProduct->price = $shippingCosts;
            $orderProduct->btw = ShoppingCart::vatForShippingMethod($order->shippingMethod->id, false, true);
            $orderProduct->vat_rate = ShoppingCart::vatRateForShippingMethod($order->shippingMethod->id);
            $orderProduct->discount = ShoppingCart::vatForShippingMethod($order->shippingMethod->id, false, false) - $orderProduct->btw;
            $orderProduct->product_extras = [];
            $orderProduct->sku = 'shipping_costs';
            $orderProduct->save();
        }

        if ($orderContainsPreOrders) {
            $order->contains_pre_orders = true;
            $order->save();
        }

        //        $orderPayment = new OrderPayment();
        //        $orderPayment->amount = $order->total;
        //        $orderPayment->order_id = $order->id;
        //        if ($paymentMethod) {
        //            $psp = $paymentMethod['psp'];
        //        } else {
        //            foreach (ecommerce()->builder('paymentServiceProviders') as $pspId => $ecommercePSP) {
        //                if ($ecommercePSP['class']::isConnected()) {
        //                    $psp = $pspId;
        //                }
        //            }
        //        }

        //        $orderPayment->psp = $psp;
        //        $depositAmount = 0;

        //        if (!$paymentMethod) {
        //            $orderPayment->payment_method = $psp;
        //        } elseif ($orderPayment->psp == 'own') {
        //            $orderPayment->payment_method_id = $paymentMethod['id'];
        //
        //            if ($depositAmount > 0.00) {
        //                $orderPayment->amount = $depositAmount;
        //                //                $orderPayment->psp = $depositPaymentMethod['psp'];
        //                //                $orderPayment->payment_method_id = $depositPaymentMethod['id'];
        //
        //                $order->has_deposit = true;
        //                $order->save();
        //            } else {
        //                $orderPayment->amount = 0;
        //                $orderPayment->status = 'paid';
        //            }
        //        } else {
        //            $orderPayment->payment_method = $paymentMethod['name'];
        //            $orderPayment->payment_method_id = $paymentMethod['id'];
        //        }
        //
        //        $orderPayment->save();
        //        $orderPayment->refresh();

        $orderLog = new OrderLog();
        $orderLog->order_id = $order->id;
        $orderLog->user_id = Auth::check() ? Auth::user()->id : null;
        $orderLog->tag = 'order.created.by.admin';
        $orderLog->save();

        //        if ($orderPayment->psp == 'own' && $orderPayment->status == 'paid') {
        $newPaymentStatus = 'waiting_for_confirmation';
        $order->changeStatus($newPaymentStatus);

        return redirect(url(route('filament.dashed.resources.orders.view', [$order])));
        //        } else {
        //            try {
        //                $transaction = ecommerce()->builder('paymentServiceProviders')[$orderPayment->psp]['class']::startTransaction($orderPayment);
        //            } catch (\Exception $exception) {
        //                throw new \Exception('Cannot start payment: ' . $exception->getMessage());
        //            }
        //
        //            return redirect($transaction['redirectUrl'], 303);
        //        }
    }
}
