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
use Illuminate\Database\Eloquent\Collection;
use Dashed\DashedEcommerceCore\Models\CartLog;
use Dashed\DashedEcommerceCore\Models\Product;
use Dashed\DashedEcommerceCore\Models\OrderLog;
use Dashed\DashedEcommerceCore\Classes\Countries;
use Dashed\DashedTranslations\Models\Translation;
use Dashed\DashedEcommerceCore\Classes\CartHelper;
use Dashed\DashedEcommerceCore\Models\OrderPayment;
use Dashed\DashedEcommerceCore\Models\OrderProduct;
use Dashed\DashedEcommerceCore\Classes\ShoppingCart;
use Dashed\DashedEcommerceCore\Classes\TikTokHelper;
use Dashed\DashedEcommerceCore\Models\AbandonedCartEmail;
use Dashed\DashedEcommerceCore\Models\ProductExtraOption;
use Dashed\DashedEcommerceCore\Services\CartActivityLogger;
use Dashed\DashedEcommerceCore\Livewire\Concerns\CartActions;
use Dashed\DashedEcommerceCore\Events\Orders\OrderCreatedEvent;
use Dashed\DashedEcommerceCore\Jobs\AbandonedCart\ScheduleAbandonedCartEmailsForCartJob;

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

    public array $countryList = [];

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

    public Collection|array $paymentMethods = [];

    public Collection|array $depositPaymentMethods = [];

    public $cartType = 'default';

    public ?bool $taxIdValidated = null;

    public ?string $taxIdValidationMessage = null;

    protected ?string $lastZipLookedUp = null;

    protected ?string $lastHouseNrLookedUp = null;

    protected ?string $lastValidatedTaxId = null;

    protected ?string $lastValidatedTaxCountry = null;

    public function mount(Product $product)
    {
        cartHelper()->initialize($this->cartType);
        cartHelper()->setCartType($this->cartType);

        $user = auth()->user();

        if ($user && $user->invoice_street) {
            $this->invoiceAddress = true;
        } else {
            $this->invoiceAddress = Customsetting::get('checkout_delivery_address_standard_invoice_address')
                ? false
                : true;
        }

        if ($user && $user->company_name) {
            $this->isCompany = true;
        } else {
            $this->isCompany = Customsetting::get('checkout_form_company_name') == 'required';
        }

        $this->country = $user?->country ?? 'Nederland';
        $this->dateOfBirth = $user?->date_of_birth ?? '';
        $this->gender = $user?->gender ?? '';
        $this->email = $user?->email ?? cartHelper()->getCart()->abandoned_email ?? '';
        $this->firstName = $user?->first_name ?? '';
        $this->lastName = $user?->last_name ?? '';
        $this->street = $user?->street ?? '';
        $this->houseNr = $user?->house_nr ?? '';
        $this->zipCode = $user?->zip_code ?? '';
        $this->city = $user?->city ?? '';
        $this->company = $user?->company_name ?? '';
        $this->taxId = $user?->btw_id ?? '';
        $this->phoneNumber = $user?->phone_number ?? '';
        $this->invoiceStreet = $user?->invoice_street ?? '';
        $this->invoiceHouseNr = $user?->invoice_house_nr ?? '';
        $this->invoiceZipCode = $user?->invoice_zip_code ?? '';
        $this->invoiceCity = $user?->invoice_city ?? '';
        $this->invoiceCountry = $user?->invoice_country ?? '';

        $this->countryList = Countries::getAllSelectedCountries();

        $this->accountRequired = Customsetting::get('checkout_account', default: 2);
        $this->firstAndLastnameRequired = Customsetting::get('checkout_form_name', default: 0);
        $this->companyRequired = Customsetting::get('checkout_form_company_name', default: 2);
        $this->phoneNumberRequired = Customsetting::get('checkout_form_phone_number_delivery_address', default: 0);
        $this->useDeliveryAddressAsInvoiceAddress = Customsetting::get('checkout_delivery_address_standard_invoice_address', default: 1) ?: 0;

        $customFields = ecommerce()->builder('customOrderFields');
        foreach ($customFields as $key => $customField) {
            if (isset($customField['hideFromCheckout']) && $customField['hideFromCheckout'] === true) {
                unset($customFields[$key]);
            }
        }

        $this->customFields = $customFields;
        $this->customFieldRules = collect($this->customFields)->mapWithKeys(function ($customField, $key) {
            return [
                'customFieldValues.'.$key => $customField['rules'],
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

        $sessionDiscount = session()->pull('discountCode');
        if ($sessionDiscount) {
            $this->discountCode = $sessionDiscount;
            cartHelper()->applyDiscountCode($sessionDiscount);
        }

        $this->checkCart();
        $this->fillPrices();

        $itemLoop = 0;
        $items = [];

        foreach ($this->cartItems as $cartItem) {
            $model = $cartItem->model ?? null;
            if (! $model) {
                continue;
            }

            $items[] = [
                'item_id' => $model->id,
                'item_name' => $model->name,
                'index' => $itemLoop,
                'discount' => ($model->discount_price ?? 0) > 0
                    ? number_format((($model->discount_price ?? 0) - ($model->current_price ?? 0)), 2, '.', '')
                    : 0,
                'item_category' => $model->productCategories->first()?->name ?? null,
                'price' => number_format((float) ($cartItem->price ?? 0), 2, '.', ''),
                'quantity' => (int) ($cartItem->qty ?? 0),
            ];

            $itemLoop++;
        }

        $cartTotal = cartHelper()->getTotal();

        $this->dispatch('checkoutInitiated', [
            'cartTotal' => number_format($cartTotal, 2, '.', ''),
            'items' => $items,
            'tiktokItems' => TikTokHelper::getShoppingCartItems($cartTotal),
        ]);
    }

    public function getCartItemsProperty()
    {
        cartHelper()->initialize($this->cartType);
        cartHelper()->setCartType($this->cartType);

        return cartHelper()->getCartItems();
    }

    public function retrievePaymentMethods(): void
    {
        $this->paymentMethods = $this->country ? ShoppingCart::getAvailablePaymentMethods($this->country) : [];

        if (
            Customsetting::get('first_payment_method_selected', null, true)
            && (
                ! $this->paymentMethod
                || ! in_array($this->paymentMethod, collect($this->paymentMethods)->pluck('id')->toArray())
            )
            && count($this->paymentMethods)
        ) {
            $this->paymentMethod = $this->paymentMethods[0]['id'] ?? '';
        }
    }

    public function retrieveShippingMethods(): void
    {
        $shippingAddress = "$this->street $this->houseNr, $this->zipCode $this->city, $this->country";

        $this->shippingMethods = $this->country
            ? ShoppingCart::getAvailableShippingMethods($this->country, $shippingAddress, $this->paymentMethod)
            : [];

        if (
            Customsetting::get('first_shipping_method_selected', null, true)
            && (
                ! $this->shippingMethod
                || ! in_array($this->shippingMethod, collect($this->shippingMethods)->pluck('id')->toArray())
            )
            && count($this->shippingMethods)
        ) {
            $this->shippingMethod = $this->shippingMethods->first()['id'] ?? '';
        }
    }

    public function updatedEmail(string $value): void
    {
        if (! filter_var($value, FILTER_VALIDATE_EMAIL)) {
            return;
        }

        $cart = cartHelper()->getCart();

        if (! $cart) {
            return;
        }

        if ($cart->abandoned_email === $value) {
            return;
        }

        $cart->abandoned_email = $value;
        $cart->saveQuietly();

        ScheduleAbandonedCartEmailsForCartJob::dispatch($cart->id);
    }

    public function updated($name, $value)
    {
        if (in_array($name, ['invoiceHouseNr', 'invoiceZipCode'], true)) {
            $this->updateInvoiceAddressByApi();

            return;
        }

        if (in_array($name, ['houseNr', 'zipCode'], true)) {
            $this->updateAddressByApi();
        }

        if (in_array($name, ['country', 'taxId', 'company'], true)) {
            $this->taxIdValidated = null;
            $this->taxIdValidationMessage = null;
            $this->lastValidatedTaxId = null;
            $this->lastValidatedTaxCountry = null;

            $this->resetErrorBag('taxId');
        }

        $fieldsAffectingCart = [
            'country',
            'company',
            'taxId',
            'shippingMethod',
            'paymentMethod',
            'depositPaymentMethod',
        ];

        if (in_array($name, $fieldsAffectingCart, true)) {
            $this->fillPrices();
        }
    }

    public function updateAddressByApi(): void
    {
        $zip = preg_replace('/\s+/', '', (string) $this->zipCode);
        $houseNr = (string) $this->houseNr;

        if (strlen($zip) < 6 || $houseNr === '') {
            return;
        }

        $response = $this->getAddressInfoByApi($zip, $houseNr);

        if (! empty($response)) {
            $this->city = $response['city'] ?? $this->city;
            $this->street = $response['street'] ?? $this->street;
        }
    }

    public function updateInvoiceAddressByApi(): void
    {
        $zip = preg_replace('/\s+/', '', (string) $this->invoiceZipCode);
        $houseNr = (string) $this->invoiceHouseNr;

        if (strlen($zip) < 6 || $houseNr === '') {
            return;
        }

        $response = $this->getAddressInfoByApi($zip, $houseNr);

        if (! empty($response)) {
            $this->invoiceCity = $response['city'] ?? $this->invoiceCity;
            $this->invoiceStreet = $response['street'] ?? $this->invoiceStreet;
        }
    }

    public function getAddressInfoByApi(?string $zipCode = null, ?string $houseNr = null): array
    {
        $zipCode = trim((string) $zipCode);
        $houseNr = trim((string) $houseNr);

        if ($zipCode === '' || $houseNr === '') {
            return [];
        }

        if ($zipCode === $this->lastZipLookedUp && $houseNr === $this->lastHouseNrLookedUp) {
            return [];
        }

        $this->lastZipLookedUp = $zipCode;
        $this->lastHouseNrLookedUp = $houseNr;

        $postNLApikey = Customsetting::get('checkout_postnl_api_key');
        if ($postNLApikey) {
            try {
                $response = Http::withHeaders([
                    'Content-Type' => 'Application/json',
                    'apikey' => $postNLApikey,
                ])
                    ->retry(1, 500)
                    ->post('https://api.postnl.nl/address/national/v1/validate', [
                        'PostalCode' => $zipCode,
                        'HouseNumber' => $houseNr,
                    ])
                    ->json()[0] ?? [];

                if ($response) {
                    return [
                        'city' => $response['City'] ?? null,
                        'street' => $response['Street'] ?? null,
                    ];
                }
            } catch (Exception $exception) {
                // negeren, we vallen terug op postcode.tech
            }
        }

        $postcodeApi = Customsetting::get('checkout_postcode_api_key');
        if ($postcodeApi) {
            try {
                $response = Http::withHeaders([
                    'Content-Type' => 'Application/json',
                ])
                    ->withToken($postcodeApi)
                    ->retry(1, 500)
                    ->get('https://postcode.tech/api/v1/postcode', [
                        'postcode' => $zipCode,
                        'number' => preg_replace('/\D/', '', $houseNr),
                    ]);

                if ($response->successful()) {
                    $json = $response->json();

                    return [
                        'city' => $json['city'] ?? null,
                        'street' => $json['street'] ?? null,
                    ];
                }
            } catch (Exception $exception) {
                // niks
            }
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

    public function fillPrices(): void
    {
        cartHelper()->initialize($this->cartType);
        cartHelper()->setCartType($this->cartType);

        $this->retrievePaymentMethods();
        $this->retrieveShippingMethods();

        $shippingZone = ShoppingCart::getShippingZoneByCountry($this->country);
        $vatReverseCharge = $this->shouldApplyVatReverseCharge(validateTaxId: true);

        cartHelper()->setShippingMethod($this->shippingMethod);
        cartHelper()->setShippingZone($shippingZone->id ?? null);
        cartHelper()->setPaymentMethod($this->paymentMethod);
        cartHelper()->setVatReverseCharge($vatReverseCharge);

        $this->depositPaymentMethods = cartHelper()->getDepositPaymentMethods();
        cartHelper()->setDepositPaymentMethod(is_numeric($this->depositPaymentMethod) ? (int) $this->depositPaymentMethod : null);

        cartHelper()->updateData();

        $this->subtotal = cartHelper()->getSubtotal();
        $this->discount = cartHelper()->getDiscount();
        $this->tax = cartHelper()->getTax();
        $this->total = cartHelper()->getTotal();

        $this->shippingCosts = cartHelper()->getShippingCosts();
        $this->paymentCosts = cartHelper()->getPaymentCosts();

        $this->depositPaymentMethods = cartHelper()->getDepositPaymentMethods();
        $this->postpayPaymentMethod = cartHelper()->getIsPostpayPaymentMethod();
        $this->depositAmount = cartHelper()->getDepositAmount();

        $this->getSuggestedProducts();
    }

    public function shouldApplyVatReverseCharge(bool $validateTaxId = true): bool
    {
        if (! filled($this->company) || ! filled($this->taxId) || ! filled($this->country)) {
            return false;
        }

        $shippingZone = ShoppingCart::getShippingZoneByCountry($this->country);

        if (! $shippingZone || ! $shippingZone->vat_reverse_charge) {
            return false;
        }

        if (! $validateTaxId) {
            return true;
        }

        return $this->validateTaxIdWithVies();
    }

    public function rules()
    {
        return array_merge([
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
                Rule::requiredIf((bool) $this->invoiceStreet),
                'max:255',
            ],
            'invoiceZipCode' => [
                Rule::requiredIf((bool) $this->invoiceStreet),
                'max:255',
            ],
            'invoiceCity' => [
                Rule::requiredIf((bool) $this->invoiceStreet),
                'max:255',
            ],
            'invoiceCountry' => [
                Rule::requiredIf((bool) $this->invoiceStreet),
                'max:255',
            ],
            'shippingMethod' => [
                'required',
                'max:255',
            ],
        ], $this->customFieldRules);
    }

    public function submit()
    {
        cartHelper()->initialize($this->cartType);
        cartHelper()->setCartType($this->cartType);

        $this->dispatch('checkoutSubmitted');

        $this->fillPrices();
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

        $shippingZone = ShoppingCart::getShippingZoneByCountry($this->country);

        if (
            $shippingZone?->vat_reverse_charge
            && filled($this->company)
            && filled($this->taxId)
            && ! $this->validateTaxIdWithVies()
        ) {
            $this->addError('taxId', $this->taxIdValidationMessage);

            Notification::make()
                ->danger()
                ->title($this->taxIdValidationMessage)
                ->send();

            return $this->dispatch('showAlert', 'error', $this->taxIdValidationMessage);
        }

        foreach (ecommerce()->builder('fulfillmentProviders') as $fulfillmentProvider) {
            if ($fulfillmentProvider['class']::isConnected() && method_exists($fulfillmentProvider['class'], 'validateAddress')) {
                $addressValid = $fulfillmentProvider['class']::validateAddress($this->street, $this->houseNr, $this->zipCode, $this->city, $this->country);

                if (! $addressValid['success']) {
                    Notification::make()
                        ->danger()
                        ->title(Translation::get(
                            'address-invalid-'.str($fulfillmentProvider['name'])->slug().'-'.str($addressValid['message'])->slug(),
                            'checkout',
                            $addressValid['message']
                        ))
                        ->send();

                    return $this->dispatch('showAlert', 'error', Translation::get(
                        'address-invalid-'.str($fulfillmentProvider['name'])->slug().'-'.str($addressValid['message'])->slug(),
                        'checkout',
                        $addressValid['message']
                    ));
                }
            }
        }

        $cartItems = cartHelper()->getCartItems();

        if (! $cartItems) {
            Notification::make()
                ->danger()
                ->title(Translation::get('no-items-in-cart', 'cart', 'You dont have any products in your shopping cart'))
                ->send();

            return $this->dispatch('showAlert', 'error', Translation::get('no-items-in-cart', 'cart', 'You dont have any products in your shopping cart'));
        }

        $extraOptionIds = [];
        foreach ($cartItems as $cartItem) {
            foreach ($cartItem->options['options'] ?? [] as $optionId => $option) {
                if (! str($optionId)->contains('product-extra-')) {
                    $extraOptionIds[] = $optionId;
                }
            }
        }

        $extraOptionIds = array_values(array_unique($extraOptionIds));

        $productExtraOptions = $extraOptionIds
            ? ProductExtraOption::whereIn('id', $extraOptionIds)->get()->keyBy('id')
            : collect();

        $paymentMethods = ShoppingCart::getPaymentMethods();
        $paymentMethod = '';
        foreach ($paymentMethods as $thisPaymentMethod) {
            if ($thisPaymentMethod['id'] == $this->paymentMethod) {
                $paymentMethod = $thisPaymentMethod;
            }
        }

        $paymentMethodPresent = (bool) $paymentMethod;
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

        $depositAmount = cartHelper()->getDepositAmount();
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
        $order->btw_id = $this->normalizeTaxId($this->taxId);
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

        $shippingCosts = cartHelper()->getShippingCosts();
        $paymentCosts = cartHelper()->getPaymentCosts();

        $order->total = cartHelper()->getTotal();
        $order->subtotal = cartHelper()->getSubtotal();
        $order->btw = cartHelper()->getTax();
        $order->vat_percentages = cartHelper()->getTaxPercentages();
        $order->discount = cartHelper()->getDiscount();
        $order->status = 'pending';
        $order->vat_reverse_charge = $this->shouldApplyVatReverseCharge(validateTaxId: true);

        $gaUserId = preg_replace("/^.+\.(.+?\..+?)$/", '\\1', @$_COOKIE['_ga']);
        $order->ga_user_id = $gaUserId;
        $order->abandoned_cart_recovery = (bool) session()->pull('abandoned_cart_recovery', false);

        if (cartHelper()->getDiscount() && cartHelper()->getDiscountCode()) {
            $order->discount_code_id = cartHelper()->getDiscountCode()->id;
        }

        $order->shipping_method_id = $shippingMethod['id'];
        $order->payment_method_id = $paymentMethod['id'];

        if (auth()->check()) {
            $order->user_id = auth()->user()->id;
        }

        $cartForVatMode = cartHelper()->getCart();
        $order->cart_id = $cartForVatMode->id;
        $order->prices_ex_vat = (bool) ($cartForVatMode->prices_ex_vat ?? false);

        $order->save();

        CartActivityLogger::orderConverted($order->cart_id, $order->id);

        $this->writeAbandonedCartSummaryLog($order);

        // Link abandoned cart email to conversion
        $abandonedCartEmailId = session()->pull('abandoned_cart_email_id');
        if ($abandonedCartEmailId) {
            AbandonedCartEmail::where('id', $abandonedCartEmailId)->update([
                'order_id' => $order->id,
                'converted_at' => now(),
            ]);
        }

        $orderContainsPreOrders = false;

        foreach ($cartItems as $cartItem) {
            if (! $cartItem->model) {
                continue;
            }

            $orderProduct = new OrderProduct();
            $orderProduct->quantity = $cartItem->qty;
            $orderProduct->product_id = $cartItem->model->id;
            $orderProduct->order_id = $order->id;
            $orderProduct->name = $cartItem->model->name;
            $orderProduct->sku = $cartItem->model->sku;

            $originalPrice = (float) Product::getShoppingCartItemPrice($cartItem);
            $discountedPrice = (float) Product::getShoppingCartItemPrice($cartItem, cartHelper()->getDiscountCode());

            if ($order->vat_reverse_charge) {
                $vatRate = $this->getVatRateForProduct($cartItem->model);

                $originalPrice = $this->getPriceExcludingVat($originalPrice, $vatRate);
                $discountedPrice = $this->getPriceExcludingVat($discountedPrice, $vatRate);
            }

            $orderProduct->price = $discountedPrice;
            $orderProduct->discount = round($originalPrice - $discountedPrice, 2);
            $orderProduct->vat_rate = $order->vat_reverse_charge
                ? 0
                : $this->getVatRateForProduct($cartItem->model);

            $productExtras = [];
            foreach ($cartItem->options['options'] ?? [] as $optionId => $option) {
                $price = 0;

                if (! str($optionId)->contains('product-extra-')) {
                    $price = optional($productExtraOptions->get($optionId))->price ?? 0;
                }

                $productExtras[] = [
                    'id' => $optionId,
                    'name' => $option['name'],
                    'value' => $option['value'],
                    'path' => $option['path'] ?? '',
                    'price' => $price,
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
                $bundleOrderProduct = new OrderProduct();
                $bundleOrderProduct->quantity = $cartItem->qty;
                $bundleOrderProduct->product_id = $bundleProduct->id;
                $bundleOrderProduct->order_id = $order->id;
                $bundleOrderProduct->name = $bundleProduct->name;
                $bundleOrderProduct->sku = $bundleProduct->sku;
                $bundleOrderProduct->price = 0;
                $bundleOrderProduct->discount = 0;

                if ($bundleProduct->isPreorderable() && $bundleProduct->stock < $cartItem->qty) {
                    $bundleOrderProduct->is_pre_order = true;
                    $bundleOrderProduct->pre_order_restocked_date = $bundleProduct->expected_in_stock_date;
                    $orderContainsPreOrders = true;
                }

                $bundleOrderProduct->save();
            }
        }

        if ($paymentCosts) {
            $orderProduct = new OrderProduct();
            $orderProduct->quantity = 1;
            $orderProduct->product_id = null;
            $orderProduct->order_id = $order->id;
            $orderProduct->name = $paymentMethod['name'];
            $orderProduct->price = $paymentCosts;
            $orderProduct->vat_rate = $order->vat_reverse_charge ? 0 : 21;

            if ($order->paymentMethod) {
                $orderProduct->btw = cartHelper()->getVatForPaymentMethod();
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
            $orderProduct->btw = cartHelper()->getVatForShippingMethod();
            $orderProduct->vat_rate = cartHelper()->getVatRateForShippingMethod();
            $orderProduct->discount = 0;
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

                $depositPaymentMethod = '';
                foreach ($paymentMethods as $thisPaymentMethod) {
                    if ($thisPaymentMethod['id'] == $this->depositPaymentMethod) {
                        $depositPaymentMethod = $thisPaymentMethod;
                    }
                }

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
        $orderLog->user_id = auth()->check() ? auth()->user()->id : null;
        $orderLog->tag = 'order.created';
        $orderLog->save();

        // Cancel any pending abandoned cart emails for this cart
        $currentCart = CartHelper::$cart;
        if ($currentCart) {
            AbandonedCartEmail::cancelAllForCart($currentCart->id);
        }

        OrderCreatedEvent::dispatch($order);

        if ($orderPayment->psp == 'own' && $orderPayment->status == 'paid') {
            $newPaymentStatus = 'waiting_for_confirmation';
            $order->changeStatus($newPaymentStatus);

            return redirect(url(ShoppingCart::getCompleteUrl()).'?paymentId='.$orderPayment->hash);
        }

        try {
            $transaction = ecommerce()->builder('paymentServiceProviders')[$orderPayment->psp]['class']::startTransaction($orderPayment);
        } catch (Exception $exception) {
            if (app()->isLocal()) {
                throw new Exception('Cannot start payment: '.$exception->getMessage());
            }

            $orderLog = new OrderLog();
            $orderLog->order_id = $order->id;
            $orderLog->user_id = Auth::check() ? auth()->user()->id : null;
            $orderLog->tag = 'order.note.created';
            $orderLog->note = Translation::get('failed-to-start-payment-try-again', 'cart', 'The payment could not be started:').' '.$exception->getMessage();
            $orderLog->save();

            Notification::make()
                ->danger()
                ->title(Translation::get('failed-to-start-payment-try-again', 'cart', 'The payment could not be started, please try again'))
                ->send();

            return $this->dispatch('showAlert', 'error', Translation::get('failed-to-start-payment-try-again', 'cart', 'The payment could not be started, please try again'));
        }

        return redirect($transaction['redirectUrl'], 303);
    }

    protected function normalizeTaxId(?string $taxId): string
    {
        return strtoupper(preg_replace('/\s+/', '', (string) $taxId));
    }

    protected function getCountryCodeForVies(?string $country): ?string
    {
        if (! $country) {
            return null;
        }

        $country = trim((string) $country);

        $map = [
            'nederland' => 'NL',
            'netherlands' => 'NL',
            'belgië' => 'BE',
            'belgie' => 'BE',
            'belgium' => 'BE',
            'duitsland' => 'DE',
            'germany' => 'DE',
            'frankrijk' => 'FR',
            'france' => 'FR',
            'spanje' => 'ES',
            'spain' => 'ES',
            'italië' => 'IT',
            'italie' => 'IT',
            'italy' => 'IT',
            'oostenrijk' => 'AT',
            'austria' => 'AT',
            'polen' => 'PL',
            'poland' => 'PL',
            'ierland' => 'IE',
            'ireland' => 'IE',
            'portugal' => 'PT',
            'zweden' => 'SE',
            'sweden' => 'SE',
            'denemarken' => 'DK',
            'denmark' => 'DK',
            'finland' => 'FI',
            'roemenië' => 'RO',
            'roemenie' => 'RO',
            'romania' => 'RO',
            'tsjechië' => 'CZ',
            'tsjechie' => 'CZ',
            'czech republic' => 'CZ',
            'slowakije' => 'SK',
            'slovakia' => 'SK',
            'hongarije' => 'HU',
            'hungary' => 'HU',
            'bulgarije' => 'BG',
            'bulgaria' => 'BG',
            'griekenland' => 'EL',
            'greece' => 'EL',
            'kroatië' => 'HR',
            'kroatie' => 'HR',
            'croatia' => 'HR',
            'slovenië' => 'SI',
            'slovenie' => 'SI',
            'slovenia' => 'SI',
            'litouwen' => 'LT',
            'lithuania' => 'LT',
            'letland' => 'LV',
            'latvia' => 'LV',
            'estland' => 'EE',
            'estonia' => 'EE',
            'luxemburg' => 'LU',
            'luxembourg' => 'LU',
            'malta' => 'MT',
            'cyprus' => 'CY',
        ];

        $key = mb_strtolower($country);

        if (isset($map[$key])) {
            return $map[$key];
        }

        if (strlen($country) === 2) {
            return strtoupper($country);
        }

        return null;
    }

    protected function validateTaxIdWithVies(): bool
    {
        $taxId = $this->normalizeTaxId($this->taxId);
        $countryCode = $this->getCountryCodeForVies($this->country);

        if (! $taxId || ! $countryCode) {
            $this->taxIdValidated = false;
            $this->taxIdValidationMessage = Translation::get(
                'invalid-tax-id-missing-data',
                'checkout',
                'Vul een geldig btw-nummer en land in.'
            );

            return false;
        }

        if (
            $this->lastValidatedTaxId === $taxId
            && $this->lastValidatedTaxCountry === $countryCode
            && $this->taxIdValidated !== null
        ) {
            return (bool) $this->taxIdValidated;
        }

        $this->lastValidatedTaxId = $taxId;
        $this->lastValidatedTaxCountry = $countryCode;
        $this->taxIdValidationMessage = null;

        try {
            $number = $taxId;

            if (str_starts_with($number, $countryCode)) {
                $number = substr($number, 2);
            }

            $number = preg_replace('/[^A-Z0-9]/', '', $number);

            $response = Http::timeout(15)
                ->acceptJson()
                ->get("https://ec.europa.eu/taxation_customs/vies/rest-api/ms/{$countryCode}/vat/{$number}", []);

            if (! $response->successful()) {
                $this->taxIdValidated = false;
                $this->taxIdValidationMessage = Translation::get(
                    'invalid-tax-id-vies-unavailable',
                    'checkout',
                    'Het btw-nummer kon niet worden gecontroleerd. Controleer het nummer en probeer het opnieuw.'
                );

                return false;
            }

            $data = $response->json();

            $isValid = (bool) data_get($data, 'isValid', false);

            $this->taxIdValidated = $isValid;
            $this->taxIdValidationMessage = $isValid
                ? null
                : Translation::get(
                    'invalid-tax-id-vies',
                    'checkout',
                    'Het ingevulde btw-nummer is niet geldig.'
                );

            return $isValid;
        } catch (\Throwable $e) {
            $this->taxIdValidated = false;
            $this->taxIdValidationMessage = Translation::get(
                'invalid-tax-id-vies-unavailable',
                'checkout',
                'Het btw-nummer kon niet worden gecontroleerd. Controleer het nummer en probeer het opnieuw.'
            );

            return false;
        }
    }

    protected function getPriceExcludingVat(float $price, float $vatRate): float
    {
        if ($vatRate <= 0) {
            return round($price, 2);
        }

        if (! Customsetting::get('taxes_prices_include_taxes')) {
            return round($price, 2);
        }

        return round($price / (100 + $vatRate) * 100, 2);
    }

    protected function getVatRateForProduct(Product $product): float
    {
        return (float) ($product->options['vat_rate'] ?? $product->vat_rate ?? 0);
    }

    public function render()
    {
        return view(config('dashed-core.site_theme', 'dashed').'.checkout.checkout');
    }

    protected function writeAbandonedCartSummaryLog(Order $order): void
    {
        if (! $order->cart_id) {
            return;
        }

        $logs = CartLog::query()
            ->where('cart_id', $order->cart_id)
            ->whereIn('event', ['cart.abandoned-email.scheduled', 'cart.abandoned-email.sent'])
            ->orderBy('created_at')
            ->get();

        if ($logs->isEmpty()) {
            return;
        }

        $sent = $logs->where('event', 'cart.abandoned-email.sent');
        $scheduled = $logs->firstWhere('event', 'cart.abandoned-email.scheduled');
        $flowName = $scheduled?->data['flow_name'] ?? null;
        $discountCode = $sent->pluck('data.discount_code')->filter()->last();

        $parts = [];
        if ($flowName) {
            $parts[] = sprintf('flow "%s"', $flowName);
        }
        $parts[] = sprintf('%d mail(s) verzonden', $sent->count());
        if ($discountCode) {
            $parts[] = sprintf('kortingscode %s', $discountCode);
        }

        OrderLog::createLog(
            orderId: $order->id,
            note: 'Order volgt uit abandoned cart flow — '.implode(', ', $parts),
            tag: 'abandoned-cart.converted',
        );
    }
}
