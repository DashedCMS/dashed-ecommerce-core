<?php

namespace Dashed\DashedEcommerceCore\Livewire\Frontend\Checkout;

use Exception;
use Carbon\Carbon;
use Livewire\Component;
use Illuminate\Validation\Rule;
use Dashed\DashedCore\Models\User;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Http;
use Filament\Notifications\Notification;
use Illuminate\Support\Facades\Validator;
use Dashed\DashedCore\Models\Customsetting;
use Dashed\DashedCore\Classes\AccountHelper;
use Dashed\DashedEcommerceCore\Models\Order;
use Dashed\DashedEcommerceCore\Models\Product;
use Dashed\DashedEcommerceCore\Models\OrderLog;
use Dashed\DashedTranslations\Models\Translation;
use Dashed\DashedEcommerceCore\Models\DiscountCode;
use Dashed\DashedEcommerceCore\Models\OrderPayment;
use Dashed\DashedEcommerceCore\Models\OrderProduct;
use Dashed\DashedEcommerceCore\Classes\ShoppingCart;
use Dashed\DashedEcommerceCore\Models\ProductExtraOption;
use Dashed\DashedEcommerceCore\Livewire\Concerns\CartActions;
use Dashed\DashedEcommerceCore\Events\Orders\OrderCreatedEvent;

class Checkout extends Component
{
    use CartActions;

    public $shippingMethod;
    public $paymentMethod;
    public $depositPaymentMethod;
    public string $discountCode = '';
    public bool $invoiceAddress = false;
    public bool $isCompany = false;
    public string $note = '';
    public bool $marketing = false;
    public bool $generalCondition = false;
    public string $gender = '';
    public string $dateOfBirth = '';
    public string $email = '';
    public string $password = '';
    public string $passwordConfirmation = '';
    public string $phoneNumber = '';
    public string $firstName = '';
    public string $lastName = '';
    public string $street = '';
    public string $houseNr = '';
    public string $zipCode = '';
    public string $city = '';
    public string $country = '';
    public string $invoiceStreet = '';
    public string $invoiceHouseNr = '';
    public string $invoiceZipCode = '';
    public string $invoiceCity = '';
    public string $invoiceCountry = '';
    public string $company = '';
    public string $taxId = '';
    public array $customFields = [];
    public array $customFieldRules = [];
    public array $customFieldValues = [];

    public int $accountRequired;
    public int $firstAndLastnameRequired;
    public int $companyRequired;
    public int $phoneNumberRequired;
    public int $useDeliveryAddressAsInvoiceAddress;

    public $subtotal;
    public $discount;
    public $tax;
    public $total;
    public $paymentCosts;
    public $shippingCosts;
    public $depositAmount;
    public bool $postpayPaymentMethod = false;
    public \Illuminate\Database\Eloquent\Collection|array $shippingMethods = [];
    public array $paymentMethods = [];
    public array $depositPaymentMethods = [];
    public string $cartType = 'default';

    public function mount(Product $product)
    {
        $this->invoiceAddress = auth()->check() && auth()->user()->lastOrderFromAllOrders() && auth()->user()->lastOrderFromAllOrders()->invoice_street ? true : (Customsetting::get('checkout_delivery_address_standard_invoice_address') ? false : true);
        $this->isCompany = auth()->check() && auth()->user()->lastOrderFromAllOrders() && auth()->user()->lastOrderFromAllOrders()->company_name ? true : (Customsetting::get('checkout_form_company_name') == 'required' ? true : false);
        $this->country = auth()->check() && auth()->user()->lastOrderFromAllOrders() && auth()->user()->lastOrderFromAllOrders()->country ? auth()->user()->lastOrderFromAllOrders()->country : 'Nederland';
        $this->dateOfBirth = optional(auth()->user())->lastOrderFromAllOrders()->date_of_birth ?? '';
        $this->gender = optional(auth()->user())->lastOrderFromAllOrders()->gender ?? '';
        $this->email = optional(auth()->user())->lastOrderFromAllOrders()->email ?? '';
        $this->firstName = optional(auth()->user())->lastOrderFromAllOrders()->first_name ?? '';
        $this->lastName = optional(auth()->user())->lastOrderFromAllOrders()->last_name ?? '';
        $this->street = optional(auth()->user())->lastOrderFromAllOrders()->street ?? '';
        $this->houseNr = optional(auth()->user())->lastOrderFromAllOrders()->house_nr ?? '';
        $this->zipCode = optional(auth()->user())->lastOrderFromAllOrders()->zip_code ?? '';
        $this->city = optional(auth()->user())->lastOrderFromAllOrders()->city ?? '';
        $this->company = optional(auth()->user())->lastOrderFromAllOrders()->company_name ?? '';
        $this->taxId = optional(auth()->user())->lastOrderFromAllOrders()->btw_id ?? '';
        $this->phoneNumber = optional(auth()->user())->lastOrderFromAllOrders()->phone_number ?? '';
        $this->invoiceStreet = optional(auth()->user())->lastOrderFromAllOrders()->invoice_street ?? '';
        $this->invoiceHouseNr = optional(auth()->user())->lastOrderFromAllOrders()->invoice_house_nr ?? '';
        $this->invoiceZipCode = optional(auth()->user())->lastOrderFromAllOrders()->invoice_zip_code ?? '';
        $this->invoiceCity = optional(auth()->user())->lastOrderFromAllOrders()->invoice_city ?? '';
        $this->invoiceCountry = optional(auth()->user())->lastOrderFromAllOrders()->invoice_country ?? '';

        $this->accountRequired = Customsetting::get('checkout_account', default: 2);
        $this->firstAndLastnameRequired = Customsetting::get('checkout_form_name', default: 0);
        $this->companyRequired = Customsetting::get('checkout_form_company_name', default: 2);
        $this->phoneNumberRequired = Customsetting::get('checkout_form_phone_number_delivery_address', default: 0);
        $this->useDeliveryAddressAsInvoiceAddress = Customsetting::get('checkout_delivery_address_standard_invoice_address', default: 1) ?: 0;
        $this->customFields = ecommerce()->builder('customOrderFields');
        $this->customFieldRules = collect($this->customFields)->mapWithKeys(function ($customField, $key) {
            return [
                'customFieldValues.' . $key => $customField['rules'],
            ];
        })->toArray();

        if ($this->accountRequired == 1 && auth()->guest()) {
            Notification::make()
                ->danger()
                ->title(Translation::get('account-required', 'checkout', 'You need to create an account to checkout'))
                ->send();

            return $this->redirect(AccountHelper::getAccountUrl());
        }

        if ($this->companyRequired == 1) {
            $this->isCompany = true;
        }

        $this->checkCart();
        $this->retrievePaymentMethods();
        $this->retrieveShippingMethods();
        $this->fillPrices();

        $itemLoop = 0;
        $items = [];

        foreach ($this->cartItems as $cartItem) {
            $items[] = [
                'item_id' => $cartItem->model->id,
                'item_name' => $cartItem->model->name,
                'index' => $itemLoop,
                'discount' => $cartItem->model->discount_price > 0 ? number_format(($cartItem->model->discount_price - $cartItem->model->current_price), 2, '.', '') : 0,
                'item_category' => $cartItem->model->productCategories->first()?->name ?? null,
                'price' => number_format($cartItem->price, 2, '.', ''),
                'quantity' => $cartItem->qty,
            ];
            $itemLoop++;
        }

        $this->dispatch('checkoutInitiated', [
            'cartTotal' => number_format(ShoppingCart::total(false), 2, '.', ''),
            'items' => $items,
        ]);
    }

    public function getCartItemsProperty()
    {
        return ShoppingCart::cartItems();
    }

    public function retrievePaymentMethods()
    {
        $this->paymentMethods = $this->country ? ShoppingCart::getAvailablePaymentMethods($this->country, true) : [];
        if (Customsetting::get('first_payment_method_selected', null, true) && (! $this->paymentMethod || ! in_array($this->paymentMethod, collect($this->paymentMethods)->pluck('id')->toArray())) && count($this->paymentMethods)) {
            $this->paymentMethod = $this->paymentMethods[0]['id'] ?? '';
        }
    }

    public function retrieveShippingMethods()
    {
        $shippingAddress = "$this->street $this->houseNr, $this->zipCode $this->city, $this->country";

        $this->shippingMethods = $this->country ? ShoppingCart::getAvailableShippingMethods($this->country, true, $shippingAddress) : [];
        if (Customsetting::get('first_shipping_method_selected', null, true) && (! $this->shippingMethod || ! in_array($this->shippingMethod, collect($this->shippingMethods)->pluck('id')->toArray())) && count($this->shippingMethods)) {
            $this->shippingMethod = $this->shippingMethods->first()['id'] ?? '';
        }
    }

    public function updated($name, $value)
    {
        if (in_array($name, ['invoiceHouseNr', 'invoiceZipCode'])) {
            $this->updateInvoiceAddressByApi();
        }

        if (in_array($name, ['country', 'street', 'houseNr', 'zipCode', 'city'])) {
            if (in_array($name, ['houseNr', 'zipCode'])) {
                $this->updateAddressByApi();
            }

            $this->retrievePaymentMethods();
            $this->retrieveShippingMethods();
            $this->fillPrices();
        }
    }

    public function updateAddressByApi(): void
    {
        $response = $this->getAddresInfoByApi($this->zipCode, $this->houseNr);
        if ($response) {
            $this->city = $response['city'];
            $this->street = $response['street'];
        }
    }

    public function updateInvoiceAddressByApi(): void
    {
        $response = $this->getAddresInfoByApi($this->invoiceZipCode, $this->invoiceHouseNr);
        if ($response) {
            $this->invoiceCity = $response['city'];
            $this->invoiceStreet = $response['street'];
        }
    }

    public function getAddresInfoByApi(?string $zipCode = null, ?string $houseNr = null): array
    {
        $postNLApikey = Customsetting::get('checkout_postnl_api_key');
        if ($postNLApikey && $zipCode && $houseNr) {
            try {
                $response = Http::withHeaders([
                    'Content-Type' => 'Application/json',
                    'apikey' => $postNLApikey,
                ])
                    ->retry(3, 1000)
                    ->post('https://api.postnl.nl/address/national/v1/validate', [
                        'PostalCode' => $zipCode,
                        'HouseNumber' => $houseNr,
                    ])
                    ->json()[0] ?? [];
                if ($response) {
                    $response = [
                        'city' => $response['City'],
                        'street' => $response['Street'],
                    ];
                }
            } catch (Exception $exception) {
                $response = [];
            }

            return $response;
        }

        $postcodeApi = Customsetting::get('checkout_postcode_api_key');
        if ($postcodeApi && $zipCode && $houseNr) {
            try {
                $response = Http::withHeaders([
                    'Content-Type' => 'Application/json',
                ])
                    ->withToken($postcodeApi)
                    ->retry(3, 1000)
                    ->get('https://postcode.tech/api/v1/postcode', [
                        'postcode' => $zipCode,
                        'number' => preg_replace('/\D/', '', $houseNr),
                    ]);
                if ($response->successful()) {
                    $response = $response->json();
                }
            } catch (Exception $exception) {
                $response = [];
            }

            return $response;
        }

        return [];
    }

    public function updatedShippingMethod()
    {
        $this->fillPrices();
    }

    public function updatedPaymentMethod()
    {
        $this->fillPrices();
    }

    public function updatedPostpayPaymentMethod()
    {
        $this->fillPrices();
    }

    public function rules()
    {
        return array_merge([
//            'generalCondition' => [
//                'accepted',
//            ],
            'firstName' => [
                'max:255',
                Rule::requiredIf($this->firstAndLastnameRequired == 1 || $this->postpayPaymentMethod),
            ],
            'lastName' => [
                'required',
                'max:255',
            ],
            'email' => [
                'required',
                'email:rfc',
                'max:255',
            ],
            'password' => [
                Rule::requiredIf(Customsetting::get('checkout_account') == 'required' && ! auth()->check()),
                'nullable',
                'min:6',
                'max:255',
            ],
            'passwordConfirmation' => [
                'same:password',
            ],
            'street' => [
                'required',
                'max:255',
            ],
            'houseNr' => [
                'required',
                'max:255',
            ],
            'zipCode' => [
                'required',
                'max:10',
            ],
            'city' => [
                'required',
                'max:255',
            ],
            'country' => [
                'required',
                'max:255',
            ],
            'phoneNumber' => [
                Rule::requiredIf($this->phoneNumberRequired == 1),
                'max:255',
            ],
            'company' => [
                Rule::requiredIf($this->companyRequired == 1),
                'max:255',
            ],
            'taxId' => [
                'max:255',
            ],
            'note' => [
                'max:1500',
            ],
            'invoiceStreet' => [
                'max:255',
            ],
            'invoiceHouseNr' => [
                Rule::requiredIf((bool)$this->invoiceStreet),
                'max:255',
            ],
            'invoiceZipCode' => [
                Rule::requiredIf((bool)$this->invoiceStreet),
                'max:255',
            ],
            'invoiceCity' => [
                Rule::requiredIf((bool)$this->invoiceStreet),
                'max:255',
            ],
            'invoiceCountry' => [
                Rule::requiredIf((bool)$this->invoiceStreet),
                'max:255',
            ],
//            'payment_method' => [
//                'required',
//            ],
            'shippingMethod' => [
                'required',
                'max:255',
            ],
        ], $this->customFieldRules);
    }

    public function submit()
    {
        $this->dispatch('checkoutSubmitted');

        $this->checkCart();

        $validator = Validator::make([
            'shippingMethod' => $this->shippingMethod,
            'paymentMethod' => $this->paymentMethod,
            'depositPaymentMethod' => $this->depositPaymentMethod,
            'note' => $this->note,
            'marketing' => $this->marketing,
            'generalCondition' => $this->generalCondition,
            'gender' => $this->gender,
            'dateOfBirth' => $this->dateOfBirth,
            'email' => $this->email,
            'password' => $this->password,
            'passwordConfirmation' => $this->passwordConfirmation,
            'phoneNumber' => $this->phoneNumber,
            'firstName' => $this->firstName,
            'lastName' => $this->lastName,
            'street' => $this->street,
            'houseNr' => $this->houseNr,
            'zipCode' => $this->zipCode,
            'city' => $this->city,
            'country' => $this->country,
            'invoiceStreet' => $this->invoiceStreet,
            'invoiceHouseNr' => $this->invoiceHouseNr,
            'invoiceZipCode' => $this->invoiceZipCode,
            'invoiceCity' => $this->invoiceCity,
            'invoiceCountry' => $this->invoiceCountry,
            'company' => $this->company,
            'taxId' => $this->taxId,
            'customFieldValues' => $this->customFieldValues,
        ], $this->rules());

        if ($validator->fails()) {
            Notification::make()
                ->danger()
                ->title(collect($validator->errors())->first()[0])
                ->send();

            return $this->dispatch('showAlert', 'error', collect($validator->errors())->first()[0]);
        }

        foreach (ecommerce()->builder('fulfillmentProviders') as $fulfillmentProvider) {
            if ($fulfillmentProvider['class']::isConnected() && method_exists($fulfillmentProvider['class'], 'validateAddress')) {
                $addressValid = $fulfillmentProvider['class']::validateAddress($this->street, $this->houseNr, $this->zipCode, $this->city, $this->country);
                if (! $addressValid['success']) {
                    Notification::make()
                        ->danger()
                        ->title(Translation::get('address-invalid-' . str($fulfillmentProvider['name'])->slug() . '-' . str($addressValid['message'])->slug(), 'checkout', $addressValid['message']))
                        ->send();

                    return $this->dispatch('showAlert', 'error', Translation::get('address-invalid-' . str($fulfillmentProvider['name'])->slug() . '-' . str($addressValid['message'])->slug(), 'checkout', $addressValid['message']));
                }
            }
        }

        $cartItems = $this->cartItems;

        if (! $cartItems) {
            Notification::make()
                ->danger()
                ->title(Translation::get('no-items-in-cart', 'cart', 'You dont have any products in your shopping cart'))
                ->send();

            return $this->dispatch('showAlert', 'error', Translation::get('no-items-in-cart', 'cart', 'You dont have any products in your shopping cart'));
        }

        $paymentMethods = ShoppingCart::getPaymentMethods();
        $paymentMethod = '';
        foreach ($paymentMethods as $thisPaymentMethod) {
            if ($thisPaymentMethod['id'] == $this->paymentMethod) {
                $paymentMethod = $thisPaymentMethod;
            }
        }

        $paymentMethodPresent = (bool)$paymentMethod;
        if (! $paymentMethodPresent) {
            foreach (ecommerce()->builder('paymentServiceProviders') as $psp) {
                if ($psp['class']::isConnected()) {
                    $paymentMethodPresent = true;
                }
            }
            if (! $paymentMethodPresent) {
                Notification::make()
                    ->danger()
                    ->title(Translation::get('no-valid-payment-method-chosen', 'cart', 'You did not choose a valid payment method'))
                    ->send();

                return $this->dispatch('showAlert', 'error', Translation::get('no-valid-payment-method-chosen', 'cart', 'You did not choose a valid payment method'));
            }
        }

        $shippingMethods = ShoppingCart::getAvailableShippingMethods($this->country);
        $shippingMethod = '';
        foreach ($shippingMethods as $thisShippingMethod) {
            if ($thisShippingMethod['id'] == $this->shippingMethod) {
                $shippingMethod = $thisShippingMethod;
            }
        }

        if (! $shippingMethod) {
            Notification::make()
                ->danger()
                ->title(Translation::get('no-valid-payment-method-chosen', 'cart', 'You did not choose a valid shipping method'))
                ->send();

            return $this->dispatch('showAlert', 'error', Translation::get('no-valid-shipping-method-chosen', 'cart', 'You did not choose a valid shipping method'));
        }

        $depositAmount = ShoppingCart::depositAmount(false, true, $shippingMethod->id, $paymentMethod['id'] ?? null);
        if ($depositAmount > 0.00) {
            $validator = Validator::make([
                'depositPaymentMethod' => $this->depositPaymentMethod,
            ], [
                'depositPaymentMethod' => ['required'],
            ]);

            if ($validator->fails()) {
                Notification::make()
                    ->danger()
                    ->title(collect($validator->errors())->first()[0])
                    ->send();

                return $this->dispatch('showAlert', 'error', collect($validator->errors())->first()[0]);
            }

            $depositPaymentMethod = '';
            foreach ($paymentMethods as $thisPaymentMethod) {
                if ($thisPaymentMethod['id'] == $this->depositPaymentMethod) {
                    $depositPaymentMethod = $thisPaymentMethod;
                }
            }

            if (! $depositPaymentMethod) {
                Notification::make()
                    ->danger()
                    ->title(Translation::get('no-valid-deposit-payment-method-chosen', 'cart', 'You did not choose a valid payment method for the deposit'))
                    ->send();

                return $this->dispatch('showAlert', 'error', Translation::get('no-valid-deposit-payment-method-chosen', 'cart', 'You did not choose a valid payment method for the deposit'));
            }
        }

        $discountCode = DiscountCode::usable()->where('code', session('discountCode'))->where('is_global_discount', false)->first();

        if (! $discountCode) {
            session(['discountCode' => '']);
            $discountCode = '';
        } elseif ($discountCode && ! $discountCode->isValidForCart($this->email)) {
            session(['discountCode' => '']);

            Notification::make()
                ->danger()
                ->title(Translation::get('discount-code-invalid', 'cart', 'The discount code you choose is invalid'))
                ->send();

            return $this->dispatch('showAlert', 'error', Translation::get('discount-code-invalid', 'cart', 'The discount code you choose is invalid'));
        }

        if (Customsetting::get('checkout_account') != 'disabled' && auth()->guest() && $this->password) {
            if (User::where('email', $this->email)->count()) {

                Notification::make()
                    ->danger()
                    ->title(Translation::get('email-duplicate-for-user', 'cart', 'The email you chose has already been used to create a account'))
                    ->send();

                return $this->dispatch('showAlert', 'error', Translation::get('email-duplicate-for-user', 'cart', 'The email you chose has already been used to create a account'));
            }

            $user = new User();
            $user->first_name = $this->firstName;
            $user->last_name = $this->lastName;
            $user->email = $this->email;
            $user->password = Hash::make($this->password);
            $user->save();

            Auth::login($user, 1);
        }

        $order = new Order();
        $order->first_name = $this->firstName;
        $order->gender = $this->gender;
        $order->date_of_birth = $this->dateOfBirth ? Carbon::parse($this->dateOfBirth) : null;
        $order->last_name = $this->lastName;
        $order->email = $this->email;
        $order->phone_number = $this->phoneNumber;
        $order->street = $this->street;
        $order->house_nr = $this->houseNr;
        $order->zip_code = $this->zipCode;
        $order->city = $this->city;
        $order->country = $this->country;
        $order->marketing = $this->marketing;
        $order->company_name = $this->company;
        $order->btw_id = $this->taxId;
        $order->note = $this->note;
        $order->invoice_street = $this->invoiceStreet;
        $order->invoice_house_nr = $this->invoiceHouseNr;
        $order->invoice_zip_code = $this->invoiceZipCode;
        $order->invoice_city = $this->invoiceCity;
        $order->invoice_country = $this->invoiceCountry;
        $order->invoice_id = 'PROFORMA';

        foreach ($this->customFieldValues as $field => $value) {
            if ($value) {
                $field = str($field)->snake()->toString();
                $order->$field = $value;
            }
        }

        $subTotal = ShoppingCart::subtotal(false, $shippingMethod->id, $paymentMethod['id'] ?? '');
        $discount = ShoppingCart::totalDiscount();
        $btw = ShoppingCart::btw(false, true, $shippingMethod->id, $paymentMethod['id'] ?? '');
        $btwPercentages = ShoppingCart::btwPercentages(false, true, $shippingMethod->id, $paymentMethod['id'] ?? '');
        $total = ShoppingCart::total(false, true, $shippingMethod->id, $paymentMethod['id'] ?? '');
        $shippingCosts = 0;
        $paymentCosts = 0;

        if ($shippingMethod->costs > 0) {
            $shippingCosts = $shippingMethod->costs;
        }

        if ($paymentMethod && isset($paymentMethod['extra_costs']) && $paymentMethod['extra_costs'] > 0) {
            $paymentCosts = $paymentMethod['extra_costs'];
        }

        $order->total = $total;
        $order->subtotal = $subTotal;
        $order->btw = $btw;
        $order->vat_percentages = $btwPercentages;
        $order->discount = $discount;
        $order->status = 'pending';
        $gaUserId = preg_replace("/^.+\.(.+?\..+?)$/", '\\1', @$_COOKIE['_ga']);
        $order->ga_user_id = $gaUserId;

        if ($discountCode) {
            $order->discount_code_id = $discountCode->id;
        }

        $order->shipping_method_id = $shippingMethod['id'];
        $order->payment_method_id = $paymentMethod['id'];

        if (Auth::check()) {
            $order->user_id = auth()->user()->id;
        }

        $order->save();

        $orderContainsPreOrders = false;
        foreach ($cartItems as $cartItem) {
            $isBundleItemWithIndividualPricing = false;
            if ($cartItem->model->is_bundle && $cartItem->model->use_bundle_product_price) {
                $isBundleItemWithIndividualPricing = true;
            }

            $orderProduct = new OrderProduct();
            $orderProduct->quantity = $cartItem->qty;
            $orderProduct->product_id = $cartItem->model->id;
            $orderProduct->order_id = $order->id;
            $orderProduct->name = $cartItem->model->name;
            $orderProduct->sku = $cartItem->model->sku;
            //            if ($discountCode) {
            //                $discountedPrice = $discountCode->getDiscountedPriceForProduct($cartItem->model, $cartItem->qty);
            //                $orderProduct->price = $discountedPrice;
            //                $orderProduct->discount = ($cartItem->model->currentPrice * $orderProduct->quantity) - $discountedPrice;
            //            } else {
            $orderProduct->price = Product::getShoppingCartItemPrice($cartItem, $discountCode ?? null);
            $orderProduct->discount = Product::getShoppingCartItemPrice($cartItem) - $orderProduct->price;
            //            }
            $productExtras = [];
            foreach ($cartItem->options['options'] as $optionId => $option) {
                $productExtras[] = [
                    'id' => $optionId,
                    'name' => $option['name'],
                    'value' => $option['value'],
                    'path' => $option['path'] ?? '',
                    'price' => str($optionId)->contains('product-extra-') ? 0 : ProductExtraOption::find($optionId)->price,
                ];
            }
            $orderProduct->product_extras = $productExtras;
            $orderProduct->hidden_options = $cartItem->options['hiddenOptions'] ?? [];

            if ($cartItem->model->isPreorderable() && $cartItem->model->stock < $cartItem->qty) {
                $orderProduct->is_pre_order = true;
                $orderProduct->pre_order_restocked_date = $cartItem->model->expected_in_stock_date;
                $orderContainsPreOrders = true;
            }

            $orderProduct->save();

            foreach ($cartItem->model->bundleProducts as $bundleProduct) {

                $orderProduct = new OrderProduct();
                $orderProduct->quantity = $cartItem->qty;
                $orderProduct->product_id = $bundleProduct->id;
                $orderProduct->order_id = $order->id;
                $orderProduct->name = $bundleProduct->name;
                $orderProduct->sku = $bundleProduct->sku;

                //                if ($isBundleItemWithIndividualPricing) {
                //                    if ($discountCode) {
                //                        $discountedPrice = $discountCode->getDiscountedPriceForProduct($bundleProduct, $cartItem->qty);
                //                        $orderProduct->price = $discountedPrice;
                //                        $orderProduct->discount = ($bundleProduct->currentPrice * $orderProduct->quantity) - $discountedPrice;
                //                    } else {
                //                        $orderProduct->price = $bundleProduct->currentPrice * $orderProduct->quantity;
                //                        $orderProduct->discount = 0;
                //                    }
                //                } else {
                $orderProduct->price = 0;
                $orderProduct->discount = 0;
                //                }

                if ($bundleProduct->isPreorderable() && $bundleProduct->stock < $cartItem->qty) {
                    $orderProduct->is_pre_order = true;
                    $orderProduct->pre_order_restocked_date = $bundleProduct->expected_in_stock_date;
                    $orderContainsPreOrders = true;
                }

                $orderProduct->save();
            }
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

        if (! $paymentMethod) {
            $orderPayment->payment_method = $psp;
        } elseif ($orderPayment->psp == 'own') {
            $orderPayment->payment_method_id = $paymentMethod['id'];

            if ($depositAmount > 0.00) {
                $orderPayment->amount = $depositAmount;
                $orderPayment->psp = $depositPaymentMethod['psp'];
                $orderPayment->payment_method_id = $depositPaymentMethod['id'];

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
        $orderLog->user_id = Auth::check() ? auth()->user()->id : null;
        $orderLog->tag = 'order.created';
        $orderLog->save();

        OrderCreatedEvent::dispatch($order);

        if ($orderPayment->psp == 'own' && $orderPayment->status == 'paid') {
            $newPaymentStatus = 'waiting_for_confirmation';
            $order->changeStatus($newPaymentStatus);

            return redirect(url(ShoppingCart::getCompleteUrl()) . '?paymentId=' . $orderPayment->hash);
        } else {
            try {
                $transaction = ecommerce()->builder('paymentServiceProviders')[$orderPayment->psp]['class']::startTransaction($orderPayment);
            } catch (\Exception $exception) {
                if (env('APP_ENV') == 'local') {
                    throw new \Exception('Cannot start payment: ' . $exception->getMessage());
                } else {

                    $orderLog = new OrderLog();
                    $orderLog->order_id = $order->id;
                    $orderLog->user_id = Auth::check() ? auth()->user()->id : null;
                    $orderLog->tag = 'order.note.created';
                    $orderLog->note = Translation::get('failed-to-start-payment-try-again', 'cart', 'The payment could not be started:') . ' ' . $exception->getMessage();
                    $orderLog->save();

                    Notification::make()
                        ->danger()
                        ->title(Translation::get('failed-to-start-payment-try-again', 'cart', 'The payment could not be started, please try again'))
                        ->send();

                    return $this->dispatch('showAlert', 'error', Translation::get('failed-to-start-payment-try-again', 'cart', 'The payment could not be started, please try again'));
                }

            }

            return redirect($transaction['redirectUrl'], 303);
        }
    }

    public function render()
    {
        return view(env('SITE_THEME', 'dashed') . '.checkout.checkout');
    }
}
