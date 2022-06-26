@if($this->cartItems)
    <x-container>
        <form wire:submit.prevent="submit">
            <div class="grid grid-cols-6 gap-8">
                <div class="col-span-6 lg:col-span-4">
                    <div class="pt-10 md:pt-12">
                        <div class="flex flex-col-reverse sm:flex-row items-center justify-between">
                            <h1 class="font-medium text-primary-500 text-xl md:text-2xl">{{Translation::get('contact-information', 'checkout', 'Contact informatie')}}</h1>
                            @if(Auth::guest() && Customsetting::get('checkout_account') != 'disabled')
                                <p class="text-primary-500">
                                    {{Translation::get('already-have-account', 'checkout', 'Heb je al een account?')}}
                                    <a href="{{AccountHelper::getAccountUrl()}}"
                                       class="text-primary-500">{{Translation::get('login', 'checkout', 'Inloggen')}}</a>
                                </p>
                            @endif
                        </div>
                        <div class="pt-4 md:pt-5 grid grid-cols-4 md:grid-cols-8 lg:grid-cols-12 gap-4">
                            <div class="col-span-4">
                                <label class="block text-sm font-medium text-gray-700">
                                    {{Translation::get('enter-email-address', 'checkout', 'Vul je email adres in')}}
                                    <span class="text-red-500">*</span>
                                </label>
                                <div class="mt-1">
                                    <input type="email" class="form-input" id="email" name="email" required
                                           wire:model.lazy="email"
                                           placeholder="{{Translation::get('email', 'checkout', 'Email')}}">
                                </div>
                            </div>
                            @if(Customsetting::get('checkout_form_phone_number_delivery_address') != 'hidden')
                                <div class="col-span-4">
                                    <label class="block text-sm font-medium text-gray-700">
                                        {{Translation::get('enter-phone-number', 'checkout', 'Vul je telefoonnummer in')}}
                                        @if(Customsetting::get('checkout_form_phone_number_delivery_address') == 'required')
                                            <span class="text-red-500">*</span>
                                        @endif
                                    </label>
                                    <div class="mt-1">
                                        <input type="text" class="form-input" id="phone_number" name="phone_number"
                                               value="{{old('phone_number') ? old('phone_number') : optional(Auth::user())->lastOrder()->phone_number ?? ''}}"
                                               wire:model.lazy="phoneNumber"
                                               placeholder="{{Translation::get('phone-number', 'checkout', 'Telefoonnummer')}}">
                                    </div>
                                </div>
                            @endif
                            @if(Auth::guest() && Customsetting::get('checkout_account') != 'disabled')
                                <div class="col-span-8">
                                    <label class="block text-sm font-medium text-gray-700 font-bold">
                                        {{Translation::get('enter-password-to-create-account', 'checkout', 'Vul een wachtwoord in om gelijk een account aan te maken')}}@if(Customsetting::get('checkout_account') == 'required')
                                            <span class="text-red-500">*</span> @endif
                                    </label>
                                    <div class="grid grid-cols-2 gap-4">
                                        <div class="mt-1">
                                            <input type="password" class="form-input" id="password" name="password"
                                                   placeholder="{{Translation::get('password', 'checkout', 'Wachtwoord')}}"
                                                   wire:model.lazy="password"
                                                   @if(Customsetting::get('checkout_account') == 'required') required @endif>
                                        </div>
                                        <div class="mt-1">
                                            <input type="password" class="form-input" id="password_confirmation"
                                                   name="password_confirmation"
                                                   wire:model.lazy="passwordConfirmation"
                                                   placeholder="{{Translation::get('password-repeat', 'checkout', 'Wachtwoord herhalen')}}"
                                                   @if(Customsetting::get('checkout_account') == 'required') required @endif>
                                        </div>
                                    </div>
                                </div>
                            @endif
                        </div>
                        <div class="flex items-center pt-4">
                            <input type="checkbox" class="form-checkbox" id="marketing" name="marketing">
                            <label class="text-sm pl-3 text-primary-500"
                                   wire:model="marketing"
                                   for="marketing">{{Translation::get('accept-marketing-text', 'checkout', 'Wil je onze nieuwsbrief ontvangen?')}}</label>
                        </div>
                    </div>
                    <hr class="my-4">
                    <div>
                        <h4 class="font-medium text-primary-500 text-xl md:text-2xl text-center sm:text-left">{{Translation::get('shipping-address', 'checkout', 'Verzendadres')}}</h4>
                        <div class="pt-4 md:pt-5">
                            <div class="grid grid-cols-4 md:grid-cols-8 lg:grid-cols-12 gap-4">
                                <div class="col-span-4">
                                    <label class="block text-sm font-medium text-gray-700">
                                        {{Translation::get('enter-first-name', 'checkout', 'Vul je voornaam in')}}@if(Customsetting::get('checkout_form_name') == 'full' || $postpayPaymentMethod)
                                            <span class="text-red-500">*</span>
                                        @endif
                                    </label>
                                    <div class="mt-1">
                                        <input type="text" class="form-input" id="first_name" name="first_name"
                                               @if(Customsetting::get('checkout_form_name') == 'full' || $postpayPaymentMethod) required
                                               @endif
                                               wire:model.lazy="firstName"
                                               placeholder="{{Translation::get('first-name', 'checkout', 'Voornaam')}}">
                                    </div>
                                </div>
                                <div class="col-span-4">
                                    <label class="block text-sm font-medium text-gray-700">
                                        {{Translation::get('enter-last-name', 'checkout', 'Vul je achternaam in')}}
                                        <span class="text-red-500">*</span>
                                    </label>
                                    <div class="mt-1">
                                        <input type="text" class="form-input" id="last_name" name="last_name"
                                               required
                                               wire:model.lazy="lastName"
                                               placeholder="{{Translation::get('last-name', 'checkout', 'Achternaam')}}">
                                    </div>
                                </div>
                            </div>
                            <div class="grid grid-cols-4 md:grid-cols-8 lg:grid-cols-12 gap-4 mt-4">
                                <div class="col-span-4">
                                    <label class="block text-sm font-medium text-gray-700">
                                        {{Translation::get('enter-street', 'checkout', 'Vul je straat in')}}<span
                                            class="text-red-500">*</span>
                                    </label>
                                    <div class="mt-1">
                                        <input type="text" class="form-input" id="street" name="street" required
                                               onFocus="geolocate()"
                                               wire:model.lazy="street"
                                               placeholder="{{Translation::get('street', 'checkout', 'Straat')}}">
                                    </div>
                                </div>
                                <div class="col-span-4">
                                    <label class="block text-sm font-medium text-gray-700">
                                        {{Translation::get('enter-house-number', 'checkout', 'Vul je huisnummer in')}}
                                        <span class="text-red-500">*</span>
                                    </label>
                                    <div class="mt-1">
                                        <input type="text" class="form-input" id="house_nr" name="house_nr" required
                                               wire:model.lazy="houseNr"
                                               placeholder="{{Translation::get('house-number', 'checkout', 'Huisnummer')}}">
                                    </div>
                                </div>
                            </div>
                            <div class="grid grid-cols-4 md:grid-cols-8 lg:grid-cols-12 gap-4 mt-4">
                                <div class="col-span-4">
                                    <label class="block text-sm font-medium text-gray-700">
                                        {{Translation::get('enter-zip-code', 'checkout', 'Vul je postcode in')}}
                                        <span class="text-red-500">*</span>
                                    </label>
                                    <div class="mt-1">
                                        <input type="text" class="form-input" id="zip_code" name="zip_code" required
                                               wire:model.lazy="zipCode"
                                               placeholder="{{Translation::get('zip-code', 'checkout', 'Postcode')}}">
                                    </div>
                                </div>
                                <div class="col-span-4">
                                    <label class="block text-sm font-medium text-gray-700">
                                        {{Translation::get('enter-city', 'checkout', 'Vul je stad in')}}<span
                                            class="text-red-500">*</span>
                                    </label>
                                    <div class="mt-1">
                                        <input type="text" class="form-input" id="city" name="city" required
                                               wire:model.lazy="city"
                                               placeholder="{{Translation::get('city', 'checkout', 'Stad')}}">
                                    </div>
                                </div>
                            </div>
                            <div class="grid grid-cols-4 md:grid-cols-8 lg:grid-cols-12 gap-4 mt-4">
                                <div class="col-span-8">
                                    <label class="block text-sm font-medium text-gray-700">
                                        {{Translation::get('enter-country', 'checkout', 'Vul je land in')}}<span
                                            class="text-red-500">*</span>
                                    </label>
                                    <div class="mt-1">
                                        <input type="text" class="form-input" id="country" name="country" required
                                               wire:model.lazy="country"
                                               placeholder="{{Translation::get('country', 'checkout', 'Land')}}">
                                    </div>
                                </div>
                            </div>
                            @if(Customsetting::get('checkout_form_company_name') != 'hidden')
                                <div class="grid grid-cols-4 md:grid-cols-8 lg:grid-cols-12 gap-4">
                                    <div
                                        class="p-4 space-y-4 bg-gray-50 mt-4 col-span-8">
                                        <div class="flex items-center space-x-2">
                                            <input wire:model="isCompany" id="is_company" name="is_company"
                                                   class="rounded-none form-checkbox" type="checkbox">
                                            <label class="text-sm font-medium" for="is_company">Bestellen als
                                                bedrijf</label>
                                        </div>

                                        @if($isCompany)
                                            <div class="grid gap-4 md:grid-cols-2">
                                                <div>
                                                    <label class="block text-sm font-medium text-gray-700">
                                                        {{Translation::get('enter-company-name', 'checkout', 'Vul je bedrijfsnaam in')}}@if(Customsetting::get('checkout_form_company_name') == 'required')
                                                            <span class="text-red-500">*</span> @endif
                                                    </label>
                                                    <div class="mt-1">
                                                        <input type="text" class="form-input" id="company_name"
                                                               name="company_name"
                                                               @if(Customsetting::get('checkout_form_company_name') == 'required') required
                                                               @endif
                                                               wire:model.lazy="company"
                                                               placeholder="{{Translation::get('company-name', 'checkout', 'Bedrijfsnaam')}}">
                                                    </div>
                                                </div>
                                                <div>
                                                    <label class="block text-sm font-medium text-gray-700">
                                                        {{Translation::get('enter-btw-id', 'checkout', 'Vul je BTW id in')}}
                                                    </label>
                                                    <div class="mt-1">
                                                        <input type="text" class="form-input" id="btw_id"
                                                               name="btw_id"
                                                               wire:model.lazy="taxId"
                                                               placeholder="{{Translation::get('btw-id', 'checkout', 'BTW ID')}}">
                                                    </div>
                                                </div>
                                            </div>
                                        @endif
                                    </div>
                                </div>
                            @endif
                            <div class="grid grid-cols-4 md:grid-cols-8 lg:grid-cols-12 gap-4">
                                <div
                                    class="p-4 space-y-4 bg-gray-50 mt-4 col-span-8">
                                    <div class="flex items-center space-x-2">
                                        <input
                                            wire:model="invoiceAddress"
                                            id="invoice_address" name="invoice_address"
                                            class="rounded-none form-checkbox" type="checkbox">
                                        <label class="text-sm font-medium"
                                               for="invoice_address">{{Translation::get('seperate-invoice-address', 'checkout', 'Afwijkend factuur adres')}}</label>
                                    </div>

                                    @if($invoiceAddress)
                                        <div>
                                            <div class="grid grid-cols-12 gap-4 mt-4">
                                                <div class="col-span-6">
                                                    <label class="block text-sm font-medium text-gray-700">
                                                        {{Translation::get('enter-street', 'checkout', 'Vul je straat in')}}
                                                        <span class="text-red-500">*</span>
                                                    </label>
                                                    <div class="mt-1">
                                                        <input type="text" class="form-input"
                                                               id="invoice_street"
                                                               required
                                                               onFocus="geolocate()"
                                                               wire:model.lazy="invoiceStreet"
                                                               placeholder="{{Translation::get('street', 'checkout', 'Straat')}}">
                                                    </div>
                                                </div>
                                                <div class="col-span-6">
                                                    <label class="block text-sm font-medium text-gray-700">
                                                        {{Translation::get('enter-house-number', 'checkout', 'Vul je huisnummer in')}}
                                                        <span class="text-red-500">*</span>
                                                    </label>
                                                    <div class="mt-1">
                                                        <input type="text" class="form-input"
                                                               id="invoice_house_nr"
                                                               name="invoice_house_nr"
                                                               required
                                                               wire:model.lazy="invoiceHouseNr"
                                                               placeholder="{{Translation::get('house-number', 'checkout', 'Huisnummer')}}">
                                                    </div>
                                                </div>
                                            </div>
                                            <div class="grid grid-cols-12 gap-4 mt-4">
                                                <div class="col-span-6">
                                                    <label class="block text-sm font-medium text-gray-700">
                                                        {{Translation::get('enter-zip-code', 'checkout', 'Vul je postcode in')}}
                                                        <span class="text-red-500">*</span>
                                                    </label>
                                                    <div class="mt-1">
                                                        <input type="text" class="form-input"
                                                               id="invoice_zip_code"
                                                               name="invoice_zip_code"
                                                               required
                                                               wire:model.lazy="invoiceZipCode"
                                                               placeholder="{{Translation::get('zip-code', 'checkout', 'Postcode')}}">
                                                    </div>
                                                </div>
                                                <div class="col-span-6">
                                                    <label class="block text-sm font-medium text-gray-700">
                                                        {{Translation::get('enter-city', 'checkout', 'Vul je stad in')}}
                                                        <span class="text-red-500">*</span>
                                                    </label>
                                                    <div class="mt-1">
                                                        <input type="text" class="form-input" id="invoice_city"
                                                               name="invoice_city"
                                                               required
                                                               wire:model.lazy="invoiceCity"
                                                               placeholder="{{Translation::get('city', 'checkout', 'Stad')}}">
                                                    </div>
                                                </div>
                                                <div class="col-span-12">
                                                    <label class="block text-sm font-medium text-gray-700">
                                                        {{Translation::get('enter-country', 'checkout', 'Vul je land in')}}
                                                        <span class="text-red-500">*</span>
                                                    </label>
                                                    <div class="mt-1">
                                                        <input type="text" class="form-input"
                                                               id="invoice_country"
                                                               name="invoice_country"
                                                               required
                                                               wire:model.lazy="invoiceCountry"
                                                               placeholder="{{Translation::get('country', 'checkout', 'Land')}}">
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    @endif
                                </div>
                            </div>
                            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4 mt-4">
                                <div>
                                    @if(count($paymentMethods))
                                        <div class="bg-primary-500 rounded-md px-4 py-2 text-white mt-1">
                                            <fieldset role="group">
                                                <label class="block text-sm font-bold">
                                                    {{Translation::get('select-payment-method', 'checkout', 'Kies een betaalmethode')}}
                                                    <span class="text-red-500">*</span>
                                                </label>
                                                <div class="mt-4 space-y-4">
                                                    @foreach($paymentMethods as $thisPaymentMethod)
                                                        <div>
                                                            {{--                                                        <div @click="paymentMethod = '{{$paymentMethod['id']}}'">--}}
                                                            <div class="flex items-center cursor-pointer">
                                                                <input id="payment_method{{$thisPaymentMethod['id']}}"
                                                                       name="payment_method" required
                                                                       type="radio"
                                                                       wire:model="paymentMethod"
                                                                       value="{{$thisPaymentMethod['id']}}"
                                                                       class="focus:ring-primary h-4 w-4 border-gray-300">
                                                                <label for="payment_method{{$thisPaymentMethod['id']}}"
                                                                       class="ml-3 block text-sm font-medium inline-flex items-center">
                                                                    @if($thisPaymentMethod['image'])
                                                                        <img
                                                                            src="/storage{{$thisPaymentMethod['image']}}"
                                                                            class="h-10 mr-2">
                                                                    @endif
                                                                    {{ $thisPaymentMethod['name'] }}
                                                                    @if($thisPaymentMethod['extra_costs'] > 0)
                                                                        <span>
                                                                        (+ {{ CurrencyHelper::formatPrice($thisPaymentMethod['extra_costs']) }})
                                                                    </span>
                                                                    @endif
                                                                </label>
                                                            </div>

                                                            @if($paymentMethod == $thisPaymentMethod['id'] && ($thisPaymentMethod['additional_info'] || count($depositPaymentMethods)))
                                                                <div
                                                                    class="bg-white rounded-md px-4 py-2 mt-2 text-primary-500">
                                                                    @if($thisPaymentMethod['additional_info'])
                                                                        <p class="payment-method-content">
                                                                            {{ $thisPaymentMethod['additional_info'] }}
                                                                        </p>
                                                                    @endif
                                                                    @if(count($depositPaymentMethods))
                                                                        <div>
                                                                            <fieldset role="group">
                                                                                <label
                                                                                    class="block text-sm font-bold mt-1">
                                                                                    {{Translation::get('select-deposit-payment-method', 'checkout', 'Kies een betaalmethode voor de aanbetaling')}}
                                                                                    <span class="text-red-500">*</span>
                                                                                </label>
                                                                                <div class="mt-4 space-y-4">
                                                                                    @foreach($depositPaymentMethods as $thisDepositPaymentMethod)
                                                                                        <div>
                                                                                            <div
                                                                                                class="flex items-center cursor-pointer">
                                                                                                <input
                                                                                                    id="deposit_payment_method{{ $thisDepositPaymentMethod['id']}}"
                                                                                                    name="deposit_payment_method"
                                                                                                    required
                                                                                                    type="radio"
                                                                                                    value="{{ $thisDepositPaymentMethod['id']}}"
                                                                                                    wire:model="depositPaymentMethod"
                                                                                                    class="focus:ring-primary h-4 w-4 border-gray-300">
                                                                                                <label
                                                                                                    for="deposit_payment_method{{ $thisDepositPaymentMethod['id']}}"
                                                                                                    class="ml-3 block text-sm font-medium inline-flex items-center">
                                                                                                    @if($thisDepositPaymentMethod['image'])
                                                                                                        <img
                                                                                                            src="/storage{{ $thisDepositPaymentMethod['image'] }}"
                                                                                                            class="h-10 mr-2">
                                                                                                    @endif
                                                                                                    {{ $thisDepositPaymentMethod['name'] }}
                                                                                                </label>
                                                                                            </div>
                                                                                        </div>
                                                                                    @endforeach
                                                                                </div>
                                                                            </fieldset>
                                                                        </div>
                                                                    @endif
                                                                </div>
                                                            @endif
                                                        </div>
                                                    @endforeach
                                                </div>
                                            </fieldset>
                                        </div>
                                    @else
                                        <p class="bg-red-400 border-2 border-red-800 px-4 py-2 text-white mt-4">{{Translation::get('no-payment-methods-available', 'checkout', 'Er zijn geen betaalmethodes beschikbaar, neem contact met ons op.')}}</p>
                                    @endif
                                </div>

                                @if($country)
                                    <div>
                                        <div class="bg-primary-500 rounded-md px-4 py-2 text-white mt-1">
                                            <fieldset role="group">
                                                <label class="block text-sm font-bold">
                                                    {{Translation::get('select-shipping-method', 'checkout', 'Kies een verzendmethode')}}
                                                    <span class="text-red-500">*</span>
                                                </label>
                                                <div class="mt-4 space-y-4">
                                                    @if(count($shippingMethods))
                                                        @foreach($shippingMethods as $thisShippingMethod)
                                                            <div class="flex items-center cursor-pointer">
                                                                <input id="shipping_method{{$thisShippingMethod['id']}}"
                                                                       name="shipping_method" required
                                                                       type="radio"
                                                                       value="{{ $thisShippingMethod['id'] }}"
                                                                       wire:model="shippingMethod"
                                                                       class="focus:ring-primary h-4 w-4 border-gray-300">
                                                                <label for="shipping_method{{$thisShippingMethod['id']}}"
                                                                       class="ml-3 block text-sm font-medium">
                                                                    {{ $thisShippingMethod['name'] }} ({{ CurrencyHelper::formatPrice($thisShippingMethod['costs']) }})
                                                                </label>
                                                            </div>
                                                        @endforeach
                                                    @else
                                                        <p class="bg-red-400 border-2 border-red-800 px-4 py-2 text-white">{{Translation::get('no-shipping-methods-available', 'checkout', 'Er zijn geen verzendmethodes beschikbaar, vul een land in.')}}</p>
                                                    @endif
                                                </div>
                                            </fieldset>
                                        </div>
                                    </div>
                                @endif
                            </div>
                            <div class="grid grid-cols-4 md:grid-cols-8 lg:grid-cols-12 gap-4 mt-4">
                                <div class="col-span-4">
                                    <label class="block text-sm font-medium text-gray-700">
                                        {{Translation::get('enter-dob', 'checkout', 'Vul je geboortedatum in')}}
                                        @if($postpayPaymentMethod)
                                            <span class="text-red-500">*</span>
                                        @endif
                                    </label>
                                    <div class="mt-1">
                                        <input type="date" class="form-input" id="date_of_birth"
                                               @if($postpayPaymentMethod) required @endif
                                               name="date_of_birth"
                                               wire:model.lazy="dateOfBirth"
                                               placeholder="{{Translation::get('date-of-birth', 'checkout', 'Geboortedatum')}}">
                                    </div>
                                </div>
                                <div class="col-span-4">
                                    <label class="block text-sm font-medium text-gray-700">
                                        {{Translation::get('enter-gender', 'checkout', 'Kies je geslacht')}}
                                        @if($postpayPaymentMethod)
                                            <span class="text-red-500">*</span>
                                        @endif
                                    </label>
                                    <div class="mt-1">
                                        <select wire:model="gender" type="text" class="form-input" id="gender"
                                                name="gender"
                                                @if($postpayPaymentMethod) required @endif
                                        >
                                            <option
                                                value="">{{Translation::get('enter-gender', 'checkout', 'Kies je geslacht')}}</option>
                                            <option
                                                value="m">{{Translation::get('enter-gender-male', 'checkout', 'Man')}}</option>
                                            <option
                                                value="f">{{Translation::get('enter-gender-female', 'checkout', 'Vrouw')}}</option>
                                        </select>
                                    </div>
                                </div>
                            </div>
                            @if($postpayPaymentMethod)
                                <small
                                    class="leading-4 text-xs">{{Translation::get('required-with-postpay', 'checkout', 'Deze velden zijn verplicht in combinatie met achteraf betalen')}}</small>
                            @endif
                        </div>
                    </div>
                    <hr class="my-4">
                    <div>
                        <div class="flex flex-col sm:flex-row justify-between items-center pt-4 sm:pt-8">
                            <a href="{{ShoppingCart::getCartUrl()}}"
                               class="flex items-center mb-3 sm:mb-0 group-hover:font-bold  text-sm text-primary-500 hover:text-primary-500 group transition-all">
                                <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"
                                     xmlns="http://www.w3.org/2000/svg">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                          d="M15 19l-7-7 7-7"></path>
                                </svg> {{Translation::get('back-to-cart', 'checkout', 'Terug naar winkelwagen')}}
                            </a>
                        </div>
                    </div>
                </div>

                <div class="col-span-6 lg:col-span-2">
                    <div class="p-4 bg-primary-500 rounded-md text-white sticky top-52">
                        <h2 class="text-2xl">{{Translation::get('your-order', 'cart', 'Jouw bestelling')}}</h2>
                        <hr class="my-4">
                        @foreach($this->cartItems as $item)
                            <div class="grid grid-cols-3 gap-4 border-b border-primary py-4">
                                <div class="flex items-center space-x-4 col-span-3">
                                    @if($item->model->image)
                                        <x-drift::image
                                            class="mx-auto"
                                            config="qcommerce"
                                            :path="$item->model->firstImageUrl"
                                            :alt=" $item->model->name"
                                            :manipulations="[
                                                    'widen' => 100,
                                                ]"
                                        />
                                    @endif
                                    <div
                                        class="rounded-full h-8 w-8 flex items-center justify-center text-primary-500 bg-white">
                                        {{$item->qty}}x
                                    </div>
                                    <div class="truncate">
                                        {{$item->model->name}}
                                    </div>
                                </div>
                            </div>
                        @endforeach
                        <hr class="my-4">
                        <p>{{Translation::get('subtotal', 'cart', 'Subtotaal')}}: <span
                                class="float-right">{{ CurrencyHelper::formatPrice($this->subtotal) }}</span></p>
                        <hr class="my-2">
                        @if($discount > 0.00)
                            <div>
                                <p>
                                    {{Translation::get('discount', 'cart', 'Korting')}}: <span
                                        class="float-right">{{ CurrencyHelper::formatPrice($this->discount) }}</span>
                                </p>
                                <hr class="my-2">
                            </div>
                        @endif
                        @if($shippingMethod)
                            <div>
                                @if($shippingCosts > 0)
                                    <p>
                                        {{Translation::get('shipping-costs', 'checkout', 'Verzendkosten')}}: <span
                                            class="float-right">{{ CurrencyHelper::formatPrice($shippingCosts) }}</span>
                                    </p>
                                @else
                                    <p>
                                        {{Translation::get('shipping-costs', 'checkout', 'Verzendkosten')}}:
                                        <span
                                            class="float-right">{{Translation::get('not-applicable', 'checkout', 'Niet van toepassing')}}</span>
                                    </p>
                                @endif
                                <hr class="my-2">
                            </div>
                        @endif
                        @if($paymentCosts)
                            <div>
                                {{Translation::get('payment-costs', 'checkout', 'Betaalmethode kosten')}}: <span
                                    class="float-right">{{ CurrencyHelper::formatPrice($paymentCosts) }}</span>
                                <hr class="my-2">
                            </div>
                        @endif
                        <p>{{Translation::get('btw', 'cart', 'BTW')}}: <span
                                class="float-right">{{ CurrencyHelper::formatPrice($tax) }}</span></p>
                        <hr class="my-2">
                        <p>{{Translation::get('total', 'cart', 'Totaal')}}: <span
                                class="float-right">{{ CurrencyHelper::formatPrice($total) }}</span></p>

                        <textarea class="form-input mt-4" id="note" name="note" rows="3"
                                  wire:model.lazy="note"
                                  placeholder="{{Translation::get('leave-a-note', 'checkout', 'Laat een notitie achter')}}"></textarea>

                        <div>
                            <div class="relative flex items-start">
                                <div class="flex items-center h-5">
                                    <input id="general_condition" required name="general_condition" type="checkbox"
                                           wire:model="generalCondition"
                                           class="focus:ring-primary h-4 w-4 text-primary-500 border-gray-300 rounded">
                                </div>
                                <div class="ml-3 text-sm">
                                    <label for="general_condition"
                                           class="font-medium text-white checkout-content">{!! Translation::get('accept-general-conditions', 'checkout', 'Ja, ik ga akkoord met de <a href="/algemene-voorwaarden">Algemene Voorwaarden</a> en <a href="/privacy-beleid">Privacy Statement</a>') !!}</label>
                                </div>
                            </div>
                        </div>

                        <div class="flex">
                            <button
                                type="submit"
                                class="button--white mt-5 py-2 px-2 w-full uppercase text-center">
                                {{Translation::get('pay-now', 'cart', 'Afrekenen')}}
                            </button>
                        </div>
                    </div>
                </div>
            </div>

        </form>

        @if(Customsetting::get('checkout_google_api_key'))
            <script
                src="https://maps.googleapis.com/maps/api/js?key={{Customsetting::get('checkout_google_api_key')}}&callback=initAutocomplete&libraries=places&v=weekly"
                defer></script>

            <script>
                let placeSearch;
                let autocomplete;
                let autocompleteInvoice;

                const componentForm = {
                    street_number: "short_name",
                    route: "long_name",
                    locality: "long_name",
                    country: "long_name",
                    postal_code: "short_name",
                };

                function initAutocomplete() {
                    autocomplete = new google.maps.places.Autocomplete(
                        document.getElementById("street"), {
                            types: ["geocode"]
                        }
                    );
                    autocomplete.setFields(["address_component"]);
                    autocomplete.addListener("place_changed", fillInAddress);


                }

                function initAutocompleteInvoice() {
                    setTimeout(function () {
                        autocompleteInvoice = new google.maps.places.Autocomplete(
                            document.getElementById("invoice_street"), {
                                types: ["geocode"]
                            }
                        );
                        autocompleteInvoice.setFields(["address_component"]);
                        autocompleteInvoice.addListener("place_changed", fillInAddressInvoice);
                    }, 300);
                }

                function fillInAddress() {
                    const place = autocomplete.getPlace();

                    // for (const component in componentForm) {
                    document.getElementById('house_nr').value = "";
                    document.getElementById('street').value = "";
                    document.getElementById('city').value = "";
                    document.getElementById('country').value = "";
                    document.getElementById('zip_code').value = "";
                    // document.getElementById(component).value = "";
                    // document.getElementById(component).disabled = false;
                    // }

                    for (const component of place.address_components) {
                        const addressType = component.types[0];

                        if (componentForm[addressType]) {
                            const val = component[componentForm[addressType]];
                            if (addressType == 'street_number') {
                                document.getElementById('house_nr').value = val;
                            } else if (addressType == 'route') {
                                document.getElementById('street').value = val;
                            } else if (addressType == 'locality') {
                                document.getElementById('city').value = val;
                            } else if (addressType == 'country') {
                                app.country = val;
                            } else if (addressType == 'postal_code') {
                                document.getElementById('zip_code').value = val;
                            }
                        }
                    }
                }

                function fillInAddressInvoice() {
                    const place = autocompleteInvoice.getPlace();

                    // for (const component in componentForm) {
                    document.getElementById('invoice_house_nr').value = "";
                    document.getElementById('invoice_street').value = "";
                    document.getElementById('invoice_city').value = "";
                    document.getElementById('invoice_country').value = "";
                    document.getElementById('invoice_zip_code').value = "";
                    // document.getElementById(component).value = "";
                    // document.getElementById(component).disabled = false;
                    // }

                    for (const component of place.address_components) {
                        const addressType = component.types[0];

                        if (componentForm[addressType]) {
                            const val = component[componentForm[addressType]];
                            if (addressType == 'street_number') {
                                document.getElementById('invoice_house_nr').value = val;
                            } else if (addressType == 'route') {
                                document.getElementById('invoice_street').value = val;
                            } else if (addressType == 'locality') {
                                document.getElementById('invoice_city').value = val;
                            } else if (addressType == 'country') {
                                document.getElementById('invoice_country').value = val;
                            } else if (addressType == 'postal_code') {
                                document.getElementById('invoice_zip_code').value = val;
                            }
                        }
                    }
                }

                function geolocate() {
                    if (navigator.geolocation) {
                        navigator.geolocation.getCurrentPosition((position) => {
                            const geolocation = {
                                lat: position.coords.latitude,
                                lng: position.coords.longitude,
                            };
                            const circle = new google.maps.Circle({
                                center: geolocation,
                                radius: position.coords.accuracy,
                            });
                            autocomplete.setBounds(circle.getBounds());
                        });
                    }
                }

            </script>
        @endif
        <script src="https://cdn.jsdelivr.net/npm/vue@2"></script>
        <script src="https://cdnjs.cloudflare.com/ajax/libs/axios/0.21.1/axios.min.js"
                integrity="sha512-bZS47S7sPOxkjU/4Bt0zrhEtWx0y0CRkhEp8IckzK+ltifIIE9EMIMTuT/mEzoIMewUINruDBIR/jJnbguonqQ=="
                crossorigin="anonymous"></script>
        <script>
            var app = new Vue({
                el: '#app',
                data: {
                    invoiceAddress: {{ old('invoice_street') && old('invoice_street') != '' ? true : (Auth::user() && Auth::user()->lastOrder() && Auth::user()->lastOrder()->invoice_street ? 'true' : (Customsetting::get('checkout_delivery_address_standard_invoice_address') ? 'false' : 'true')) }},
                    isCompany: {{  old('company_name') && old('company_name') != '' ? true : (Auth::user() && Auth::user()->lastOrder() && Auth::user()->lastOrder()->company_name ? 'true' : (Customsetting::get('checkout_form_company_name') == 'required' ? 'true' : 'false')) }},
                    country: '{{ old('country') ? old('country') : (Auth::user() && Auth::user()->lastOrder() && Auth::user()->lastOrder()->country ? Auth::user()->lastOrder()->country : 'Nederland')}}',
                    shippingMethod: '',
                    shippingMethods: [],
                    subTotal: '{{ShoppingCart::subtotal(true)}}',
                    discount: '{{ShoppingCart::totalDiscount(true)}}',
                    btw: '{{ShoppingCart::btw(true)}}',
                    total: '{{ShoppingCart::total(true)}}',
                    shippingCosts: '',
                    shippingCostsFormatted: '',
                    paymentMethods: {!! json_encode(ShoppingCart::getPaymentMethods()) !!},
                    depositPaymentMethods: [],
                    depositPaymentMethod: '',
                    paymentMethod: '',
                    postpayPaymentMethod: false,
                    paymentCosts: '',
                },
                mounted() {
                    this.$watch(
                        "invoiceAddress",
                        (newValue, oldValue) => {
                            initAutocompleteInvoice();
                        }
                    )
                },
            })
        </script>
    </x-container>
@else
    <x-container>
        <h1 class="text-2xl font-bold">{{Translation::get('no-items-in-cart', 'cart', 'Geen items in je winkelwagen?')}}</h1>
        <p>{{Translation::get('go-shop-furter', 'cart', 'Ga snel verder shoppen!')}}</p>
    </x-container>
@endif
