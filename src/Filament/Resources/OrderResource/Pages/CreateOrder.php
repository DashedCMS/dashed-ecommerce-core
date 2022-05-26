<?php

namespace Qubiqx\QcommerceEcommerceCore\Filament\Resources\OrderResource\Pages;

use Carbon\Carbon;
use Filament\Resources\Pages\Page;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\HtmlString;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Toggle;
use Qubiqx\QcommerceCore\Models\User;
use Filament\Forms\Components\Section;
use Filament\Forms\Contracts\HasForms;
use Filament\Forms\Components\Textarea;
use Illuminate\Support\Facades\Storage;
use Qubiqx\QcommerceCore\Classes\Sites;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\MultiSelect;
use Filament\Forms\Components\Placeholder;
use Qubiqx\QcommerceCore\Models\Customsetting;
use Filament\Forms\Concerns\InteractsWithForms;
use Qubiqx\QcommerceEcommerceCore\Models\Order;
use Qubiqx\QcommerceEcommerceCore\Models\Product;
use Qubiqx\QcommerceEcommerceCore\Models\OrderLog;
use Qubiqx\QcommerceTranslations\Models\Translation;
use Qubiqx\QcommerceEcommerceCore\Models\DiscountCode;
use Qubiqx\QcommerceEcommerceCore\Models\OrderPayment;
use Qubiqx\QcommerceEcommerceCore\Models\OrderProduct;
use Qubiqx\QcommerceEcommerceCore\Models\ProductExtra;
use Qubiqx\QcommerceEcommerceCore\Classes\ShoppingCart;
use Qubiqx\QcommerceEcommerceCore\Models\PaymentMethod;
use Qubiqx\QcommerceEcommerceCore\Classes\CurrencyHelper;
use Qubiqx\QcommerceEcommerceCore\Models\ProductExtraOption;
use Qubiqx\QcommerceEcommerceCore\Filament\Resources\OrderResource;

class CreateOrder extends Page implements HasForms
{
    use InteractsWithForms;

    protected static string $resource = OrderResource::class;
    protected static ?string $title = 'Bestelling aanmaken';
    protected static string $view = 'qcommerce-ecommerce-core::orders.create-order';

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
    public $orderProducts = [];
    public $shipping_method_id;
    public $payment_method_id;
    public $products = [];
    public $activatedProducts = [];

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
        $products = Product::handOrderShowable()->with(['childProducts', 'parentProduct'])->get();

        foreach ($products as &$product) {
            $product['stock'] = $product->stock();
            $product['price'] = CurrencyHelper::formatPrice($product->price);
            $product['productExtras'] = $product->allProductExtras();
        }

        return $products;
    }

    public function getSearchableUsers($query)
    {
        return User::where(DB::raw('lower(first_name)'), 'LIKE', '%' . strtolower($query) . '%')->orWhere(DB::raw('lower(last_name)'), 'LIKE', '%' . strtolower($query) . '%')->orWhere(DB::raw('lower(email)'), 'LIKE', '%' . strtolower($query) . '%')->limit(50)->pluck('name', 'id');
    }

    public function getSearchableProducts($query)
    {
        return Product::handOrderShowable()->where(DB::raw('lower(name)'), 'LIKE', '%' . strtolower($query) . '%')->orWhere(DB::raw('lower(content)'), 'LIKE', '%' . strtolower($query) . '%')->limit(50)->pluck('name', 'id');
    }

    protected function getFormSchema(): array
    {
        $schema = [];

        $schema[] = Section::make('Persoonlijke informatie')
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
                    ->rules([
                        'nullable',
                        'min:6',
                        'max:255',
                        'confirmed',
                    ])
                    ->visible(fn (\Closure $get) => ! $get('user_id')),
                TextInput::make('password_confirmation')
                    ->label('Wachtwoord herhalen')
                    ->type('password')
                    ->rules([
                        'nullable',
                        'min:6',
                        'max:255',
                    ])
                    ->visible(fn (\Closure $get) => ! $get('user_id')),
                TextInput::make('first_name')
                    ->label('Voornaam')
                    ->rules([
                        'nullable',
                        'min:6',
                        'max:255',
                    ]),
                TextInput::make('last_name')
                    ->label('Achternaam')
                    ->required()
                    ->rules([
                        'required',
                        'min:6',
                        'max:255',
                    ]),
                DatePicker::make('date_of_birth')
                    ->label('Geboortedatum')
                    ->rules([
                        'nullable',
                        'date',
                    ]),
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
                    ->rules([
                        'required',
                        'email:rfc',
                        'min:6',
                        'max:255',
                    ]),
                TextInput::make('phone_number')
                    ->label('Telefoon nummer')
                    ->rules([
                        'max:255',
                    ]),
                TextInput::make('street')
                    ->label('Straat')
                    ->rules([
                        'nullable',
                        'min:6',
                        'max:255',
                    ])
                    ->reactive(),
                TextInput::make('house_nr')
                    ->label('Huisnummer')
                    ->required(fn (\Closure $get) => $get('street'))
                    ->rules([
                        'nullable',
                        'min:6',
                        'max:255',
                    ]),
                TextInput::make('zip_code')
                    ->label('Postcode')
                    ->required(fn (\Closure $get) => $get('street'))
                    ->rules([
                        'nullable',
                        'min:6',
                        'max:255',
                    ]),
                TextInput::make('city')
                    ->label('Stad')
                    ->required(fn (\Closure $get) => $get('street'))
                    ->rules([
                        'nullable',
                        'min:6',
                        'max:255',
                    ]),
                TextInput::make('country')
                    ->label('Land')
                    ->required()
                    ->rules([
                        'required',
                        'min:6',
                        'max:255',
                    ])
                    ->reactive(),
                TextInput::make('company_name')
                    ->label('Bedrijfsnaam')
                    ->rules([
                        'max:255',
                    ]),
                TextInput::make('btw_id')
                    ->label('BTW id')
                    ->rules([
                        'max:255',
                    ]),
                TextInput::make('invoice_street')
                    ->label('Factuur straat')
                    ->rules([
                        'nullable',
                        'min:6',
                        'max:255',
                    ])
                    ->reactive(),
                TextInput::make('invoice_house_nr')
                    ->label('Factuur huisnummer')
                    ->required(fn (\Closure $get) => $get('invoice_street'))
                    ->rules([
                        'nullable',
                        'min:6',
                        'max:255',
                    ]),
                TextInput::make('invoice_zip_code')
                    ->label('Factuur postcode')
                    ->required(fn (\Closure $get) => $get('invoice_street'))
                    ->rules([
                        'nullable',
                        'min:6',
                        'max:255',
                    ]),
                TextInput::make('invoice_city')
                    ->label('Factuur stad')
                    ->required(fn (\Closure $get) => $get('invoice_street'))
                    ->rules([
                        'nullable',
                        'min:6',
                        'max:255',
                    ]),
                TextInput::make('invoice_country')
                    ->label('Factuur land')
                    ->required(fn (\Closure $get) => $get('invoice_street'))
                    ->rules([
                        'nullable',
                        'min:6',
                        'max:255',
                    ]),
                Textarea::make('note')
                    ->label('Notitie')
                    ->rules([
                        'nullable',
                        'max:1500',
                    ]),
                TextInput::make('discount_code')
                    ->label('Kortingscode')
                    ->rules([
                        'nullable',
                        'max:255',
                    ])
                    ->reactive(),
            ])
            ->columns([
                'default' => 1,
                'lg' => 2,
            ]);

        $productSchemas = [];

//        $productSchemas[] = MultiSelect::make('activatedProducts')
//            ->label('Kies producten')
//            ->getSearchResultsUsing(fn (string $query) => $this->getSearchableProducts($query))
//            ->getOptionLabelsUsing(fn ($values): ?string => Product::find($values)->pluck('name'))
//            ->reactive();

        $productSchemas[] = MultiSelect::make('activatedProducts')
            ->label('Kies producten')
            ->options(Product::handOrderShowable()->pluck('name', 'id'))
            ->searchable()
            ->reactive();

        foreach ($this->getAllProductsProperty() as $product) {
            $productExtras = [];

            foreach ($product['productExtras'] as $extra) {
                $extraOptions = [];
                foreach ($extra->productExtraOptions as $option) {
                    $extraOptions[$option->id] = $option->value . ' (+ ' . CurrencyHelper::formatPrice($option->price) . ')';
                }
                $productExtras[] = Select::make('products.' . $product->id . '.extra.' . $extra->id)
                    ->label($extra->name)
                    ->options($extraOptions)
                    ->reactive()
                    ->required($extra->required);
            }

            $productSchemas[] = Section::make('Product ' . $product->name)
                ->schema(array_merge([
                    TextInput::make('products.' . $product->id . '.quantity')
                        ->label('Aantal')
                        ->type('number')
                        ->required()
                        ->minValue(0)
                        ->maxValue(1000)
                        ->default(0)
                        ->rules([
                            'required',
                            'numeric',
                            'min:1',
                            'max:1000',
                        ]),
                    Placeholder::make('Voorraad')
                        ->content($product->stock()),
                    Placeholder::make('Prijs')
                        ->content($product->currentPrice),
                    Placeholder::make('Afbeelding')
                        ->content(new HtmlString('<img width="300" src="' . Storage::url($product->firstImageUrl) . '">')),
                ], $productExtras))
                ->visible(fn (\Closure $get) => in_array($product->id, $get('activatedProducts')))
                ->reactive();
        }

        $schema[] = Section::make('Producten')
            ->schema($productSchemas)
            ->columnSpan([
                'default' => 1,
                'lg' => 2,
            ])
            ->reactive();

        $schema[] = Section::make('Overige informatie')
            ->schema([
                Select::make('payment_method_id')
                    ->label('Betaalmethode')
                    ->required()
                    ->options(PaymentMethod::where('available_from_amount', '<', $this->totalUnformatted)->where('psp', 'own')->where('site_id', Sites::getActive())->where('active', 1)->pluck('name', 'id')->toArray()),
                Select::make('shipping_method_id')
                    ->label('Verzendmethode')
                    ->required()
                    ->options(collect(ShoppingCart::getAvailableShippingMethods($this->country, true))->pluck('name', 'id')->toArray()),
            ])
            ->columns([
                'default' => 1,
                'lg' => 2,
            ])
            ->reactive();

        $schema[] = Section::make('Bestelling')
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

        return $schema;
    }

    public function updatedProducts($path, $value): void
    {
        $this->updateInfo();
    }

    public function updatedCountry($path, $value): void
    {
        $this->updateInfo();
    }

    public function updatedShippingMethodId($path, $value): void
    {
        $this->updateInfo();
    }

    public function updatedPaymentMethodId($path, $value): void
    {
        $this->updateInfo();
    }

    public function updatedDiscountCode($path, $value): void
    {
        $this->updateInfo();
    }

    public function updateInfo()
    {
        $this->loading = true;

        foreach (\Cart::instance('handorder')->content() as $row) {
            \Cart::remove($row->rowId);
        }

        foreach ($this->getAllProductsProperty() as $product) {
            if (($this->products[$product->id]['quantity'] ?? 0) > 0) {
                $productPrice = $product->getOriginal('price');
                $options = [];
                foreach ($this->products[$product->id]['extra'] ?? [] as $productExtraId => $productExtraOptionId) {
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

                \Cart::add($product->id, $product->name, $this->products[$product->id]['quantity'], $productPrice, $options)->associate(Product::class);
            }
        }

        if (! $this->discount_code) {
            session(['discountCode' => '']);
        } else {
            $discountCode = DiscountCode::usable()->where('code', $this->discount_code)->first();
            if (! $discountCode || ! $discountCode->isValidForCart()) {
                session(['discountCode' => '']);
            } else {
                session(['discountCode' => $discountCode->code]);
            }
        }

        $checkoutData = ShoppingCart::getCheckoutData($this->shipping_method_id, $this->payment_method_id);

        $this->totalUnformatted = $checkoutData['total'];

        $this->discount = $checkoutData['discountFormatted'];
        $this->vat = $checkoutData['btwFormatted'];
        $this->subTotal = $checkoutData['subTotalFormatted'];
        $this->total = $checkoutData['totalFormatted'];

        $this->notify('success', 'Informatie bijgewerkt');
        $this->loading = false;
    }

    public function submit()
    {
        $this->loading = true;
        \Cart::instance('handorder')->content();
        ShoppingCart::removeInvalidItems();

        $cartItems = ShoppingCart::cartItems();

        if (! $cartItems) {
            $this->notify('error', Translation::get('no-items-in-cart', 'cart', 'You dont have any products in your shopping cart'));

            return;
        }

        $paymentMethods = ShoppingCart::getPaymentMethods();
        $paymentMethod = '';
        foreach ($paymentMethods as $thisPaymentMethod) {
            if ($thisPaymentMethod['id'] == $this->payment_method_id) {
                $paymentMethod = $thisPaymentMethod;
            }
        }

        if (! $paymentMethod) {
            $this->notify('error', Translation::get('no-valid-payment-method-chosen', 'cart', 'You did not choose a valid payment method'));

            return;
        }

        $shippingMethods = ShoppingCart::getAvailableShippingMethods($this->country);
        $shippingMethod = '';
        foreach ($shippingMethods as $thisShippingMethod) {
            if ($thisShippingMethod['id'] == $this->shipping_method_id) {
                $shippingMethod = $thisShippingMethod;
            }
        }

        if (! $shippingMethod) {
            $this->notify('error', Translation::get('no-valid-shipping-method-chosen', 'cart', 'You did not choose a valid shipping method'));

            return;
        }

        $discountCode = DiscountCode::usable()->where('code', session('discountCode'))->first();

        if (! $discountCode) {
            session(['discountCode' => '']);
            $discountCode = '';
        } elseif ($discountCode && ! $discountCode->isValidForCart($this->email)) {
            session(['discountCode' => '']);

            $this->notify('error', Translation::get('discount-code-invalid', 'cart', 'The discount code you choose is invalid'));

            return;
        }

        if (Customsetting::get('checkout_account') != 'disabled' && Auth::guest() && $this->password) {
            if (User::where('email', $this->email)->count()) {
                $this->notify('error', Translation::get('email-duplicate-for-user', 'cart', 'The email you chose has already been used to create a account'));

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

        $subTotal = ShoppingCart::subtotal(false, $shippingMethod->id, $paymentMethod['id']);
        $discount = ShoppingCart::totalDiscount();
        $btw = ShoppingCart::btw(false, true, $shippingMethod->id, $paymentMethod['id']);
        $total = ShoppingCart::total(false, true, $shippingMethod->id, $paymentMethod['id']);
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
            if ($discountCode) {
                $discountedPrice = $discountCode->getDiscountedPriceForProduct($cartItem);
                $orderProduct->price = $discountedPrice;
                $orderProduct->discount = ($cartItem->price * $orderProduct->quantity) - $discountedPrice;
            } else {
                $orderProduct->price = $cartItem->price * $orderProduct->quantity;
                $orderProduct->discount = 0;
            }
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

        $orderPayment = new OrderPayment();
        $orderPayment->amount = $order->total;
        $orderPayment->order_id = $order->id;
        if ($paymentMethod) {
            $psp = $paymentMethod['psp'];
        } else {
            foreach (ecommerce()->builder('paymentServiceProviders') as $pspId => $ecommercePSP) {
                if ($ecommercePSP['class']::isConnected()) {
                    $psp = $pspId;
                }
            }
        }

        $orderPayment->psp = $psp;
        $depositAmount = 0;

        if (! $paymentMethod) {
            $orderPayment->payment_method = $psp;
        } elseif ($orderPayment->psp == 'own') {
            $orderPayment->payment_method_id = $paymentMethod['id'];

            if ($depositAmount > 0.00) {
                $orderPayment->amount = $depositAmount;
//                $orderPayment->psp = $depositPaymentMethod['psp'];
//                $orderPayment->payment_method_id = $depositPaymentMethod['id'];

                $order->has_deposit = true;
                $order->save();
            } else {
                $orderPayment->amount = 0;
                $orderPayment->status = 'paid';
            }
        } else {
            $orderPayment->payment_method = $paymentMethod['name'];
            $orderPayment->payment_method_id = $paymentMethod['id'];
        }

        $orderPayment->save();
        $orderPayment->refresh();

        $orderLog = new OrderLog();
        $orderLog->order_id = $order->id;
        $orderLog->user_id = Auth::check() ? Auth::user()->id : null;
        $orderLog->tag = 'order.created.by.admin';
        $orderLog->save();

        if ($orderPayment->psp == 'own' && $orderPayment->status == 'paid') {
            $newPaymentStatus = 'waiting_for_confirmation';
            $order->changeStatus($newPaymentStatus);

            return redirect(url(route('filament.resources.orders.view', [$order])));
        } else {
            try {
                $transaction = ecommerce()->builder('paymentServiceProviders')[$orderPayment->psp]['class']::startTransaction($orderPayment);
            } catch (\Exception $exception) {
                throw new \Exception('Cannot start payment: ' . $exception->getMessage());
            }

            return redirect($transaction['redirectUrl'], 303);
        }

//        if ($paymentMethod['system'] == 'own' && $orderPayment->status == 'paid') {
//            $newStatus = 'waiting_for_confirmation';
//            $order->changeStatus($newStatus);
//            return redirect(route('qcommerce.orders.edit', [$order]));
//        } elseif ($paymentMethod['system'] == 'mollie' || (Mollie::isConnected() && $paymentMethod['system'] == 'own' && $orderPayment->status == 'pending')) {
//            $transaction = Mollie::startTransaction($order, $orderPayment);
//
//            return redirect($transaction->getCheckoutUrl(), 303);
//        } elseif ($paymentMethod['system'] == 'paynl' || (PayNL::isConnected() && $paymentMethod['system'] == 'own' && $orderPayment->status == 'pending')) {
//            $transaction = PayNL::startTransaction($order, $orderPayment);
//
//            return redirect($transaction->getRedirectUrl(), 303);
//        } else {
//            throw new \Exception('Cannot start payment');
//        }
    }
}
