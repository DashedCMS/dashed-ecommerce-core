<?php

namespace Dashed\DashedEcommerceCore\Filament\Pages\POS;

use Carbon\Carbon;
use Filament\Forms\Get;
use Livewire\Component;
use Filament\Forms\Form;
use Illuminate\Support\Str;
use Dashed\DashedCore\Models\User;
use Dashed\DashedCore\Classes\Sites;
use Filament\Forms\Components\Select;
use Filament\Forms\Contracts\HasForms;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Dashed\DashedCore\Models\Customsetting;
use Dashed\DashedEcommerceCore\Models\Order;
use Dashed\DashedEcommerceCore\Models\POSCart;
use Dashed\DashedEcommerceCore\Models\Product;
use Dashed\DashedEcommerceCore\Models\OrderLog;
use Filament\Forms\Concerns\InteractsWithForms;
use Dashed\DashedTranslations\Models\Translation;
use Dashed\DashedEcommerceCore\Models\DiscountCode;
use Dashed\DashedEcommerceCore\Models\OrderPayment;
use Dashed\DashedEcommerceCore\Models\OrderProduct;
use Dashed\DashedEcommerceCore\Classes\ShoppingCart;
use Dashed\DashedEcommerceCore\Models\PaymentMethod;
use Dashed\DashedEcommerceCore\Classes\CurrencyHelper;
use Dashed\DashedEcommerceCore\Models\ProductExtraOption;

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
    //
    //    public $user_id;
    //    public $marketing;
    //    public $password;
    //    public $password_confirmation;
    //    public $first_name;
    //    public $last_name;
    //    public $email;
    //    public $phone_number;
    //    public $date_of_birth;
    //    public $gender;
    //    public $street;
    //    public $house_nr;
    //    public $zip_code;
    //    public $city;
    //    public $country;
    //    public $company_name;
    //    public $btw_id;
    //    public $invoice_street;
    //    public $invoice_house_nr;
    //    public $invoice_zip_code;
    //    public $invoice_city;
    //    public $invoice_country;
    //    public $note;
    //    public $discount_code;
    //    public ?string $activeDiscountCode = '';
    //    public $orderProducts = [];
    //    public $shipping_method_id;
    //    public $payment_method_id;
    //    public $paymentMethod;
    //    public $allProducts = [];
    //    public $products = [];
    //    public $searchedProducts = [];
    //    public $searchProductQuery;
    //    public $searchQueryInputmode = 'none';
    //    public $customProductPopup = false;
    public ?array $customProductData = [
        'quantity' => 1,
        'vat_rate' => 21,
    ];
    //    public ?array $searchOrderData = [];
    //    public $createDiscountPopup = false;
    public ?array $createDiscountData = [];
    //    public $checkoutPopup = false;
    //    public $paymentPopup = false;
    //    public $orderConfirmationPopup = false;
    //    public $searchOrderPopup = false;
    //    public $posPaymentMethods = [];
    //    public $suggestedCashPaymentAmounts = [];
    //    public $cashPaymentAmount = null;
    //    public $orderOrigin;
    //    public $order;
    //    public $orderPayment;
    //    public $lastOrder;
    //    public $showOrder;
    //    public $posIdentifier = '';
    //    public bool $isPinTerminalPayment = false;
    //    public bool $pinTerminalError = false;
    //    public ?string $pinTerminalErrorMessage = null;
    //    public ?string $pinTerminalStatus = 'pending';
    //    public $fullscreen = false;

    public array $cachableVariables = [
//        'products' => [],
        'searchQueryInputmode' => 'none',
//        'discount_code' => '',
    ];

    protected $listeners = [
        'fullscreenValue',
        'notify',
    ];

    public function mount(): void
    {
        //        ShoppingCart::setInstance($this->cartInstance);
        //        $this->allProducts = Product::handOrderShowable()->get();
        //        $this->loadVariables();
        //        $this->fillPOSCart(true);
        //        $this->customProductForm->fill([
        //            'quantity' => 1,
        //            'vat_rate' => 21,
        //        ]);
        //        $this->createDiscountForm->fill([
        //            'type' => 'percentage',
        //        ]);
        //        $this->updateInfo(false);
        //        $this->lastOrder = Order::where('order_origin', 'pos')->latest()->first();
    }

    public function notify($type, $message): void
    {
        Notification::make()
            ->title($message)
            ->$type()
            ->send();
    }

    //    public function finishPOSCart()
    //    {
    //        $posCart = POSCart::where('user_id', auth()->user()->id)->where('identifier', $this->posIdentifier)->first();
    //        $posCart->status = 'finished';
    //        $posCart->save();
    //        $this->posIdentifier = uniqid();
    //    }
    //
    public function createOrder(): array
    {
        $this->updateInfo(false);
        $this->loading = true;
        ShoppingCart::setInstance($this->cartInstance);
        \Cart::instance($this->cartInstance)->content();
        ShoppingCart::removeInvalidItems(checkStock: false);

        $cartItems = ShoppingCart::cartItems($this->cartInstance);
        $checkoutData = ShoppingCart::getCheckoutData($this->shipping_method_id, $this->payment_method_id);

        if (! $cartItems) {
            Notification::make()
                ->title(Translation::get('no-items-in-cart', 'cart', 'You dont have any products in your shopping cart'))
                ->danger()
                ->send();

            return [
                'success' => false,
            ];
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

        if (! $shippingMethod && $this->orderOrigin != 'pos') {
            //            Notification::make()
            //                ->title('Ga een stap terug, klik op "Gegevens bijwerken" en ga door')
            //                ->danger()
            //                ->send();
            Notification::make()
                ->title(Translation::get('no-valid-shipping-method-chosen', 'cart', 'You did not choose a valid shipping method'))
                ->danger()
                ->send();

            return [
                'success' => false,
            ];
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

            return [
                'success' => false,
            ];
        }

        if (Customsetting::get('checkout_account') != 'disabled' && Auth::guest() && $this->password) {
            if (User::where('email', $this->email)->count()) {
                Notification::make()
                    ->title(Translation::get('email-duplicate-for-user', 'cart', 'The email you chose has already been used to create a account'))
                    ->danger()
                    ->send();

                return [
                    'success' => false,
                ];
            }

            $user = new User();
            $user->first_name = $this->first_name;
            $user->last_name = $this->last_name;
            $user->email = $this->email;
            $user->password = Hash::make($this->password);
            $user->save();
        }

        $order = new Order();
        $order->order_origin = $this->orderOrigin;
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
        $subTotal = ShoppingCart::subtotal(false, $shippingMethod->id ?? null, $paymentMethod['id'] ?? null);
        $discount = ShoppingCart::totalDiscount(false, $this->discount_code);
        $btw = ShoppingCart::btw(false, true, $shippingMethod->id ?? null, $paymentMethod['id'] ?? null);
        $btwPercentages = ShoppingCart::btwPercentages(false, true, $shippingMethod->id ?? null, $paymentMethod['id'] ?? null);
        $total = ShoppingCart::total(false, true, $shippingMethod->id ?? null, $paymentMethod['id'] ?? null);
        $shippingCosts = 0;
        $paymentCosts = 0;

        if (($shippingMethod->costs ?? 0) > 0) {
            $shippingCosts = $shippingMethod->costs;
        }

        if (isset($paymentMethod['extra_costs']) && $paymentMethod['extra_costs'] > 0) {
            $paymentCosts = $paymentMethod['extra_costs'];
        }

        $order->total = $total;
        $order->subtotal = $subTotal;
        $order->btw = $btw;
        $order->vat_percentages = $btwPercentages;
        $order->discount = $discount;
        $order->status = 'pending';
        $order->ga_user_id = null;

        if ($discountCode) {
            $order->discount_code_id = $discountCode->id;
        }

        $order->shipping_method_id = $shippingMethod['id'] ?? null;

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
            $orderProduct->product_id = $cartItem->model->id ?? null;
            $orderProduct->order_id = $order->id;
            $orderProduct->name = $cartItem->model->name ?? $cartItem->name;
            $orderProduct->sku = $cartItem->model->sku ?? null;
            $orderProduct->vat_rate = $cartItem->options['vat_rate'] ?? $cartItem->taxRate;
            $orderProduct->price = Product::getShoppingCartItemPrice($cartItem, $discountCode ?? null);
            $orderProduct->discount = Product::getShoppingCartItemPrice($cartItem) - $orderProduct->price;
            $productExtras = [];
            foreach ($cartItem->options['options'] as $optionId => $option) {
                if ($option['name'] ?? false) {
                    $productExtras[] = [
                        'id' => $optionId,
                        'name' => $option['name'],
                        'value' => $option['value'],
                        'price' => ProductExtraOption::find($optionId)->price,
                    ];
                }
            }
            $orderProduct->product_extras = $productExtras;

            if ($cartItem->model && $cartItem->model->isPreorderable() && $cartItem->model->stock < $cartItem->qty) {
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

        return [
            'success' => true,
            'order' => $order,
        ];
    }

    public function getForms(): array
    {
        return [
            'customProductForm',
//            'createOrderForm',
            'createDiscountForm',
//            'cashPaymentForm',
//            'searchOrderForm',
        ];
    }
    //
    //    public function createOrderForm(Form $form): Form
    //    {
    //        return $form;
    //    }

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
    //
    //    public function closeShowOrder()
    //    {
    //        $this->showOrder = null;
    //    }
    //
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

    //    public function removeDiscount()
    //    {
    //        $this->discount_code = '';
    //        $this->updateInfo(false);
    //    }
    //
    //
    //    public function printReceipt(Order $order, $isCopy = false)
    //    {
    //        $order->refresh();
    //        $order->printReceipt($isCopy);
    //    }
    //
    //    public function closeCheckout()
    //    {
    //        $this->checkoutPopup = false;
    //    }
    //
    //    public function closePayment()
    //    {
    //        if ($this->isPinTerminalPayment) {
    //            self::cancelPinTerminalPayment($this->order);
    //        }
    //
    //        if (!$this->order) {
    //            return;
    //        }
    //
    //        if ($this->order->isPaidFor()) {
    //            Notification::make()
    //                ->body(Translation::get('order-already-paid', 'payments', 'De bestelling is al betaald'))
    //                ->danger()
    //                ->send();
    //        } else {
    //            $this->order->delete();
    //            Notification::make()
    //                ->body(Translation::get('order-cancelled', 'payments', 'De bestelling is geannuleerd'))
    //                ->success()
    //                ->send();
    //        }
    //
    //        $this->order = null;
    //        $this->paymentPopup = false;
    //    }
    //
    //    public function closeOrderConfirmation()
    //    {
    //        $this->orderConfirmationPopup = false;
    //        $this->lastOrder = $this->order;
    //        $this->order = null;
    //        $this->cacheVariables();
    //    }

    //    public function cashPaymentForm(Form $form): Form
    //    {
    //        return $form
    //            ->schema([
    //                TextInput::make('cashPaymentAmount')
    //                    ->label('Prijs')
    //                    ->hiddenLabel()
    //                    ->placeholder('Anders...')
    //                    ->numeric()
    //                    ->minValue(0)
    //                    ->maxValue(999999)
    //                    ->inputMode('decimal')
    //                    ->required()
    //                    ->reactive()
    //                    ->debounce(300)
    //                    ->extraInputAttributes([
    //                        'class' => 'text-xl sm:text-xl md:text-xl py-2',
    //                    ])
    //                    ->extraFieldWrapperAttributes([
    //                        'class' => 'text-xl sm:text-xl md:text-xl py-2',
    //                    ])
    //                    ->extraAttributes([
    //                        'class' => 'text-xl sm:text-xl md:text-xl py-2',
    //                    ])
    //                    ->prefix('€'),
    //            ]);
    //    }
    //
    public function selectPaymentMethod($paymentMethodId)
    {
        $response = $this->createOrder();

        if ($response['success']) {
            $this->payment_method_id = $paymentMethodId;
            $this->paymentMethod = PaymentMethod::find($paymentMethodId);

            $this->order = $response['order'];

            $this->suggestedCashPaymentAmounts = $this->getPaymentOptions($this->totalUnformatted);
            $this->checkoutPopup = false;
            $this->paymentPopup = true;

            $this->isPinTerminalPayment = false;
            if ($this->paymentMethod->pinTerminal) {
                $this->startPinTerminalPayment();
            }
        } else {
            $this->order = null;
        }
    }
    //
    //    public function setCashPaymentAmount($amount): void
    //    {
    //        $this->cashPaymentAmount = $amount;
    //        $this->markAsPaid();
    //    }
    //
    //    public function checkPinTerminalPayment(): void
    //    {
    //        if (!$this->order || $this->pinTerminalStatus != 'pending') {
    //            return;
    //        }
    //
    //        $this->order->refresh();
    //
    //        if ($this->order->isPaidFor()) {
    //            $this->pinTerminalStatus = 'paid';
    //            self::finishPaidOrder($this->order);
    //        } elseif ($this->order->status == 'cancelled') {
    //            $this->cancelPinTerminalPaymentByCustomer($this->order);
    //        }
    //    }

    //
    //    public function cancelPinTerminalPayment(Order $order): void
    //    {
    //        $this->isPinTerminalPayment = false;
    //        $this->pinTerminalStatus = 'pending';
    //        $this->pinTerminalError = false;
    //        $this->pinTerminalErrorMessage = null;
    //
    //        try {
    //            $success = ecommerce()->builder('paymentServiceProviders')[$this->orderPayment->psp]['class']::cancelPinTerminalTransaction($this->orderPayment);
    //        } catch (\Exception $exception) {
    //            $success = false;
    //        }
    //        if (!$success) {
    //            Notification::make()
    //                ->danger()
    //                ->title(Translation::get('failed-to-stop-terminal-payment-try-again', 'cart', 'De pin betaling kon niet worden gestopt'))
    //                ->send();
    //        }
    //    }
    //
    //    public function cancelPinTerminalPaymentByCustomer(Order $order): void
    //    {
    //        $this->isPinTerminalPayment = true;
    //        $this->pinTerminalStatus = 'cancelled_by_customer';
    //        $this->pinTerminalError = true;
    //        $this->pinTerminalErrorMessage = 'Betaling geannuleerd door klant';
    //
    //        try {
    //            $success = ecommerce()->builder('paymentServiceProviders')[$this->orderPayment->psp]['class']::cancelPinTerminalTransaction($this->orderPayment);
    //        } catch (\Exception $exception) {
    //            $success = false;
    //        }
    //        if (!$success) {
    //            Notification::make()
    //                ->danger()
    //                ->title(Translation::get('failed-to-stop-terminal-payment-try-again', 'cart', 'De pin betaling kon niet worden gestopt'))
    //                ->send();
    //        }
    //    }
    //
    //    public function markAsPaid(bool $hasMultiplePayments = false): void
    //    {
    //        if ($this->paymentMethod->is_cash_payment) {
    //            if (!$this->cashPaymentAmount) {
    //                Notification::make()
    //                    ->title('Geen bedrag ingevoerd')
    //                    ->danger()
    //                    ->send();
    //
    //                return;
    //            } elseif (!$hasMultiplePayments && $this->cashPaymentAmount < $this->totalUnformatted) {
    //                Notification::make()
    //                    ->title('Bedrag is te laag')
    //                    ->danger()
    //                    ->send();
    //
    //                return;
    //            }
    //        }
    //
    //        $order = $this->order;
    //
    //        $orderPayment = new OrderPayment();
    //        $orderPayment->amount = $this->cashPaymentAmount ?: $this->totalUnformatted;
    //        $orderPayment->order_id = $order->id;
    //        $orderPayment->payment_method_id = $this->payment_method_id;
    //        $orderPayment->payment_method = $this->paymentMethod->name;
    //        $orderPayment->psp = 'own';
    //        $orderPayment->save();
    //        $orderPayment->changeStatus('paid');
    //        $this->orderPayment = $orderPayment;
    //
    //        if ($orderPayment->amount > $order->total) {
    //            $difference = $order->total - $orderPayment->amount;
    //
    //            $refundOrderPayment = new OrderPayment();
    //            $refundOrderPayment->amount = $difference;
    //            $refundOrderPayment->order_id = $order->id;
    //            $refundOrderPayment->payment_method_id = $this->payment_method_id;
    //            $refundOrderPayment->payment_method = $this->paymentMethod->name;
    //            $refundOrderPayment->psp = 'own';
    //            $refundOrderPayment->save();
    //            $refundOrderPayment->changeStatus('paid');
    //        }
    //
    //        $order->refresh();
    //        if ($this->paymentMethod->is_cash_payment && $this->cashPaymentAmount < $this->totalUnformatted && $hasMultiplePayments) {
    //            $paymentMethod = collect($this->posPaymentMethods)->whereNotNull('pin_terminal_id')->first();
    //            if (!$paymentMethod) {
    //                Notification::make()
    //                    ->title('Geen pin terminal gevonden, bestelling incorrect afgehandeld')
    //                    ->danger()
    //                    ->send();
    //
    //                return;
    //            }
    //            $this->payment_method_id = $paymentMethod['id'];
    //            $this->paymentMethod = PaymentMethod::find($paymentMethod['id']);
    //            self::startPinTerminalPayment();
    //        } else {
    //            self::finishPaidOrder($order);
    //        }
    //    }
    //
    //    public function createPaymentWithExtraPayment(): void
    //    {
    //        self::markAsPaid(true);
    //    }
    //
    //    public function finishPaidOrder(Order $order)
    //    {
    //        $order->changeStatus('paid');
    //        $order->changeFulfillmentStatus('handled');
    //
    //        $this->printReceipt($order);
    //        $hasCashPayment = false;
    //        foreach ($order->orderPayments as $orderPayment) {
    //            if ($orderPayment->paymentMethod->is_cash_payment) {
    //                $hasCashPayment = true;
    //            }
    //        }
    //        if ($hasCashPayment) {
    //            $this->openCashRegister();
    //        }
    //
    //        $this->paymentPopup = false;
    //        $this->products = [];
    //        $this->discount_code = '';
    //        $this->cashPaymentAmount = null;
    //        $this->finishPOSCart();
    //        $this->updateInfo(false);
    //        $this->orderConfirmationPopup = true;
    //    }
    //
    //    public function printLastOrder()
    //    {
    //        if ($this->lastOrder) {
    //            $this->printReceipt($this->lastOrder, true);
    //        } else {
    //            Notification::make()
    //                ->title('Geen order gevonden')
    //                ->danger()
    //                ->send();
    //        }
    //    }

    public function render()
    {
        return view('dashed-ecommerce-core::pos.pages.point-of-sale');
    }

    public function fullscreenValue($fullscreen)
    {
        $this->fullscreen = $fullscreen;
    }
}
