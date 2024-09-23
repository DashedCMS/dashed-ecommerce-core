<?php

namespace Dashed\DashedEcommerceCore\Filament\Resources\OrderResource\Concerns;

use Carbon\Carbon;
use Filament\Forms\Get;
use Dashed\DashedCore\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Toggle;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\FileUpload;
use Dashed\DashedCore\Models\Customsetting;
use Dashed\DashedEcommerceCore\Models\Order;
use Filament\Forms\Components\DateTimePicker;
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

trait CreateManualOrderActions
{
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
    public $allProducts = [];
    public $products = [];
    public $searchedProducts = [];
    public $searchProductQuery;
    public string $cartInstance = '';
    public $searchQueryInputmode = 'none';

    public array $cachableVariables = [
        'products' => [],
        'searchQueryInputmode' => 'none',
    ];

    public function initialize($cartInstance)
    {
        $this->cartInstance = $cartInstance;
        ShoppingCart::setInstance($this->cartInstance);
        $this->allProducts = Product::handOrderShowable()->get();
        $this->loadVariables();
        $this->updateInfo(false);
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

    public function getProductExtrasSchema(Product $product): array
    {
        $productExtras = [];

        foreach ($product->allProductExtras() as $extra) {
            $extraOptions = [];
            foreach ($extra->ProductExtraOptions as $option) {
                $extraOptions[$option->id] = $option->value . ' (+ ' . CurrencyHelper::formatPrice($option->price) . ')';
            }

            if ($extra->type == 'input') {
                if ($extra->input_type == 'date') {
                    $productExtras[] = DatePicker::make('extra.' . $extra['id'])
                        ->label($extra->name)
                        ->required($extra['required']);
                } elseif ($extra->input_type == 'datetime') {
                    $productExtras[] = DateTimePicker::make('extra.' . $extra['id'])
                        ->label($extra->name)
                        ->required($extra['required']);
                } else {
                    $productExtras[] = TextInput::make('extra.' . $extra['id'])
                        ->label($extra->name)
                        ->required($extra['required']);
                }
            } elseif ($extra->type == 'select') {
                $productExtras[] = Select::make('extra.' . $extra['id'])
                    ->label($extra->name)
                    ->options($extraOptions)
                    ->required($extra['required']);
            } elseif ($extra->type == 'single') {
                $productExtras[] = Select::make('extra.' . $extra['id'])
                    ->label($extra->name)
                    ->options($extraOptions)
                    ->required($extra['required']);
            } elseif ($extra->type == 'multiple') {
                $productExtras[] = Select::make('extra.' . $extra['id'])
                    ->label($extra->name)
                    ->options($extraOptions)
                    ->multiple()
                    ->required($extra['required']);
            } elseif ($extra->type == 'checkbox') {
                $productExtras[] = Toggle::make('extra.' . $extra['id'])
                    ->label($extra->name)
                    ->required($extra['required']);
            } elseif ($extra->type == 'file') {
                $productExtras[] = FileUpload::make('extra.' . $extra['id'])
                    ->label($extra->name)
                    ->required($extra['required']);
            } elseif ($extra->type == 'image') {
                $productExtras[] = FileUpload::make('extra.' . $extra['id'])
                    ->label($extra->name)
                    ->image()
                    ->required($extra['required']);
            } else {
                $productExtras[] = TextInput::make('extra.' . $extra['id'])
                    ->label($extra->name)
                    ->helperText('This extra option is not build in yet, please notify Dashed')
                    ->required($extra['required']);
            }
        }

        return $productExtras;
    }

    public function updateInfo($showNotification = true)
    {
        ShoppingCart::setInstance($this->cartInstance);
        ShoppingCart::emptyMyCart();

        $this->loading = true;

        //        foreach (\Cart::instance($this->cartInstance)->content() as $row) {
        //            \Cart::remove($row->rowId);
        //        }

        foreach ($this->products ?: [] as $chosenProduct) {
            $product = Product::find($chosenProduct['id']);
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

                \Cart::instance($this->cartInstance)->add($product->id, $product->name, $chosenProduct['quantity'], $productPrice, $options)->associate(Product::class);
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

        $this->cacheVariables();

        $this->loading = false;
    }

    public function createOrder(): array
    {
        $this->updateInfo(false);
        $this->loading = true;
        \Cart::instance($this->cartInstance)->content();
        ShoppingCart::removeInvalidItems();

        $cartItems = ShoppingCart::cartItems();
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

        if (! $shippingMethod) {
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

        return [
            'success' => true,
            'order' => $order,
        ];
    }

    public function selectProduct()
    {
        $selectedProduct = Product::handOrderShowable()
            ->where('name', $this->searchProductQuery)
            ->orWhere('sku', $this->searchProductQuery)
            ->orWhere('ean', $this->searchProductQuery)
            ->first();

        $this->addProduct($selectedProduct['id'] ?? null);
    }

    public function addProduct($productId)
    {
        $selectedProduct = Product::handOrderShowable()
            ->find($productId);

        if ($selectedProduct) {
            $productAlreadyInCart = false;

            foreach ($this->products ?: [] as &$product) {
                if ($product['id'] == $selectedProduct['id']) {
                    $productAlreadyInCart = true;
                    $product['quantity']++;
                    $product['price'] = $selectedProduct->getOriginal('price') * $product['quantity'];
                }
            }

            if (! $productAlreadyInCart) {
                $this->products[] = [
                    'id' => $selectedProduct['id'],
                    'product' => $selectedProduct,
                    'quantity' => 1,
                    'price' => $selectedProduct->getOriginal('price'),
                    'extra' => [],
                ];
            }

            $this->searchProductQuery = '';
            $this->updateInfo(false);
        } else {
            Notification::make()
                ->title('Product ' . $this->searchProductQuery . ' niet gevonden')
                ->danger()
                ->send();
            $this->searchProductQuery = '';
        }

        $this->dispatch('focus');
    }

    public function changeQuantity($productId, $quantity)
    {
        foreach ($this->products as $productKey => &$product) {
            if ($product['id'] == $productId) {
                if ($quantity == 0) {
                    unset($this->products[$productKey]);
                } else {
                    $product['quantity'] = $quantity;
                    $product['price'] = $product['product']->getOriginal('price') * $quantity;
                }
            }
        }

        $this->updateInfo(false);
    }

    public function clearProducts()
    {
        $this->products = [];

        $this->updateInfo(false);
    }

    public function updateSearchedProducts()
    {
        $this->searchedProducts = Product::handOrderShowable()
            ->search($this->searchProductQuery)
            ->limit(25)
            ->get();
        //        Notification::make()
        //            ->title('boem')
        //            ->success()
        //            ->send();
    }

    //    public function updated()
    //    {
    //        $this->cacheVariables();
    //    }

    public function toggleSearchQueryInputmode()
    {
        if ($this->searchQueryInputmode == 'none') {
            $this->searchQueryInputmode = null;
        } else {
            $this->searchQueryInputmode = 'none';
        }

        $this->dispatch('focus');
        $this->cacheVariables();
    }

    public function cacheVariables(): void
    {
        foreach ($this->cachableVariables as $variable => $defaultValue) {
            session([$variable => $this->{$variable}]);
        }
    }

    public function loadVariables(): void
    {
        foreach ($this->cachableVariables as $variable => $defaultValue) {
            $this->{$variable} = session($variable, $defaultValue);
        }
    }
}
