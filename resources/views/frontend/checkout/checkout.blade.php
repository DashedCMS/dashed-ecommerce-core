<div class="bg-white relative">
    <div class="absolute left-0 top-0 hidden h-full w-1/2 bg-white lg:block" aria-hidden="true"></div>
    <div class="absolute right-0 top-0 hidden h-full w-1/2 bg-gradient-to-tr from-primary-200/50 to-primary-300 lg:block"
         aria-hidden="true"></div>

    <x-checkout-container>
        <form wire:submit.prevent="submit">
            <main class="relative grid grid-cols-1 gap-x-16 lg:grid-cols-2">
                <h1 class="sr-only">{{ Translation::get('checkout-title', 'checkout', 'Checkout') }}</h1>

                <section aria-labelledby="summary-heading"
                         class="bg-gradient-to-tr from-primary-200 lg:from-transparent to-primary-300 lg:to-transparent pb-12 pt-6 text-primary-600 md:px-10 lg:col-start-2 lg:row-start-1 lg:mx-auto lg:w-full lg:max-w-lg lg:px-0 lg:py-24">
                    @if(count($this->cartItems))
                        <div class="mx-auto max-w-2xl px-4 lg:max-w-none lg:px-0">
                            <h2 id="summary-heading"
                                class="sr-only">{{ Translation::get('order-summary', 'checkout', 'Order summary') }}</h2>

                            <dl>
                                <dt class="text-sm font-medium">{{ Translation::get('amount-due', 'checkout', 'Amount due') }}</dt>
                                <dd class="mt-1 text-3xl font-bold tracking-tight text-white">{{ CurrencyHelper::formatPrice($total) }}</dd>
                            </dl>

                            <ul role="list" class="divide-y divide-white divide-opacity-10 text-sm font-medium">
                                @foreach($this->cartItems as $item)
                                    <li class="flex items-start space-x-4 py-6">
                                        @if($item->model->firstImage)
                                            <x-drift::image
                                                class="h-36 flex-none rounded-md object-cover object-center"
                                                config="dashed"
                                                :path="$item->model->firstImage"
                                                :alt=" $item->model->name"
                                                :manipulations="[
                                                    'widen' => 200,
                                                ]"
                                            />
                                        @endif
                                        <div class="flex-auto space-y-1">
                                            <h3 class="text-white">{{$item->model->name}} - {{$item->qty}}x</h3>
                                            <p>{{$item->model->category->name ?? Translation::get('no-category', 'products', 'No category')}}</p>
                                            @foreach($item->options as $option)
                                                <p>{{$option['name']}}: {{$option['value']}}</p>
                                            @endforeach
                                        </div>
                                        <p class="flex-none text-base font-medium text-white">{{CurrencyHelper::formatPrice($item->model->currentPrice * $item->qty)}}</p>
                                        <button
                                            wire:click="changeQuantity('{{ $item->rowId }}', '0')"
                                            class="border-2 border-primary text-primary-500 hover:text-white hover:bg-primary-500">
                                            <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"
                                                 xmlns="http://www.w3.org/2000/svg">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                      d="M6 18L18 6M6 6l12 12"></path>
                                            </svg>
                                        </button>
                                    </li>
                                @endforeach
                            </ul>

                            <dl class="space-y-6 border-t border-white border-opacity-10 pt-6 text-sm font-medium">
                                <div class="flex items-center justify-between">
                                    <dt class="font-bold">{{Translation::get('subtotal', 'checkout', 'Subtotaal')}}</dt>
                                    <dd>{{ CurrencyHelper::formatPrice($this->subtotal) }}</dd>
                                </div>

                                @if($discount > 0.00)
                                    <div class="flex items-center justify-between">
                                        <dt class="font-bold">{{Translation::get('discount', 'checkout', 'Korting')}}</dt>
                                        <dd>{{ CurrencyHelper::formatPrice($this->discount) }}</dd>
                                    </div>
                                @endif

                                @if($paymentCosts > 0.00)
                                    <div class="flex items-center justify-between">
                                        <dt class="font-bold">{{Translation::get('payment-costs', 'checkout', 'Betaalmethode kosten')}}</dt>
                                        <dd>{{ CurrencyHelper::formatPrice($paymentCosts) }}</dd>
                                    </div>
                                @endif

                                @if($shippingMethod)
                                    @if($shippingCosts > 0)
                                        <div class="flex items-center justify-between">
                                            <dt class="font-bold">{{Translation::get('shipping-costs', 'checkout', 'Verzendkosten')}}</dt>
                                            <dd>{{ CurrencyHelper::formatPrice($shippingCosts) }}</dd>
                                        </div>
                                    @else
                                        <div class="flex items-center justify-between">
                                            <dt class="font-bold"> {{Translation::get('shipping-costs', 'checkout', 'Verzendkosten')}}</dt>
                                            <dd>{{Translation::get('not-applicable', 'checkout', 'Niet van toepassing')}}</dd>
                                        </div>
                                    @endif
                                @endif

                                <div class="flex items-center justify-between">
                                    <dt class="font-bold">{{Translation::get('btw', 'checkout', 'BTW')}}</dt>
                                    <dd>{{ CurrencyHelper::formatPrice($tax) }}</dd>
                                </div>

                                <div class="flex items-center justify-between border-t border-white border-opacity-10 pt-6 text-white">
                                    <dt class="text-base font-bold">{{Translation::get('total', 'checkout', 'Totaal')}}</dt>
                                    <dd class="text-base">{{ CurrencyHelper::formatPrice($total) }}</dd>
                                </div>
                            </dl>
                        </div>

                        <div class="hidden lg:grid gap-4">
                            <x-fields.textarea
                                :placeholder="Translation::get('leave-a-note', 'checkout', 'Leave a note')"
                                model="note"
                                rows="3"
                                id="note"
                            />

                            <x-fields.checkbox
                                model="generalCondition"
                                id="generalCondition"
                                :label='Translation::get("accept-general-conditions", "checkout", "Yes, i agree with the <a href=\"/terms-and-conditions\">Terms and Conditions</a> and <a href=\"/privacy-policy\">Privacy Policy</a>")'
                            />

                            <button type="submit"
                                    class="button button-primary-on-white w-full">
                                {{Translation::get('pay-now', 'checkout', 'Pay now')}}
                            </button>
                        </div>
                    @endif
                </section>

                <section aria-labelledby="payment-and-shipping-heading"
                         class="py-16 lg:col-start-1 lg:row-start-1 lg:mx-auto lg:w-full lg:max-w-lg lg:py-24">
                    @if(count($this->cartItems))
                        <h2 id="payment-and-shipping-heading"
                            class="sr-only">{{Translation::get('payment-and-shipping-details', 'checkout', 'Payment and shipping details')}}</h2>

                        <div class="mx-auto max-w-2xl px-4 lg:max-w-none lg:px-0">
                            <div>
                                <h3 class="text-lg font-medium text-gray-900">
                                    {{Translation::get('contact-information', 'checkout', 'Contact information')}}
                                </h3>

                                @if(Auth::guest() && Customsetting::get('checkout_account') != 'disabled')
                                    <p class="text-black">
                                        {{Translation::get('already-have-account', 'checkout', 'Heb je al een account?')}}
                                        <a href="{{AccountHelper::getAccountUrl()}}"
                                           class="text-primary-500">{{Translation::get('login', 'checkout', 'Inloggen')}}</a>
                                    </p>
                                @endif

                                <div class="grid md:grid-cols-2 gap-4 mt-6">
                                    <div class="">
                                        <x-fields.input
                                            required
                                            placeholder="{{Translation::get('email', 'checkout', 'Email')}}"
                                            type="email"
                                            model="email"
                                            id="email"
                                            :label="Translation::get('enter-email-address', 'checkout', 'Vul je email adres in')"
                                        />
                                    </div>
                                    @if(Customsetting::get('checkout_form_phone_number_delivery_address') != 'hidden')
                                        <div class="">
                                            <x-fields.input
                                                :required="Customsetting::get('checkout_form_phone_number_delivery_address') == 'required'"
                                                placeholder="{{Translation::get('phone-number', 'checkout', 'Telefoonnummer')}}"
                                                type="text"
                                                model="phoneNumber"
                                                id="phone_number"
                                                :label="Translation::get('enter-phone-number', 'checkout', 'Vul je telefoonnummer in')"
                                            />
                                        </div>
                                    @endif
                                    @if(Auth::guest() && Customsetting::get('checkout_account') != 'disabled')
                                        <div class="md:col-span-2 grid md:grid-cols-2 gap-4">
                                            <div class="md:col-span-2 -mb-4">
                                                <label class="block text-sm font-medium text-gray-700">
                                                    {{ Translation::get('enter-password-to-create-account', 'checkout', 'Enter a password to create an account') }}
                                                    @if(Customsetting::get('checkout_account') == 'required')
                                                        <span class="text-red-500">*</span>
                                                    @endif
                                                </label>
                                            </div>
                                            <x-fields.input
                                                :required="Customsetting::get('checkout_account') == 'required'"
                                                placeholder="{{Translation::get('password', 'checkout', 'Password')}}"
                                                type="password"
                                                model="password"
                                                id="password"
                                            />
                                            <x-fields.input
                                                :required="Customsetting::get('checkout_account') == 'required'"
                                                placeholder="{{Translation::get('password-repeat', 'checkout', 'Repeat password')}}"
                                                type="password"
                                                model="passwordConfirmation"
                                                id="passwordConfirmation"
                                            />
                                        </div>
                                    @endif

                                    <div class="md:col-span-2">
                                        <x-fields.checkbox
                                            model="marketing"
                                            id="marketing"
                                            :label="Translation::get('accept-marketing-text', 'checkout', 'Do you want to receive our newsletter?')"
                                        />
                                    </div>
                                </div>

                                <div class="my-6">
                                    <hr/>
                                </div>

                                <h3 class="text-lg font-medium text-gray-900">
                                    {{Translation::get('shipping-information', 'checkout', 'Shipping information')}}
                                </h3>

                                <div class="grid md:grid-cols-2 gap-4 mt-6">
                                    <div class="">
                                        <x-fields.input
                                            :required="Customsetting::get('checkout_form_name') == 'full' || $postpayPaymentMethod"
                                            type="text"
                                            model="firstName"
                                            id="firstName"
                                            :label="Translation::get('enter-first-name', 'checkout', 'Enter your first name')"
                                            placeholder="{{Translation::get('first-name', 'checkout', 'First name')}}"
                                        />
                                    </div>
                                    <div class="">
                                        <x-fields.input
                                            required
                                            type="text"
                                            model="lastName"
                                            id="lastName"
                                            :label="Translation::get('enter-last-name', 'checkout', 'Enter your last name')"
                                            placeholder="{{Translation::get('last-name', 'checkout', 'Last name')}}"
                                        />
                                    </div>
                                    <div class="">
                                        <x-fields.input
                                            required
                                            type="text"
                                            model="zipCode"
                                            id="zipCode"
                                            :label="Translation::get('enter-zip-code', 'checkout', 'Enter your zip code')"
                                            placeholder="{{Translation::get('zip-code', 'checkout', 'Zip code')}}"
                                        />
                                    </div>
                                    <div class="">
                                        <x-fields.input
                                            required
                                            type="text"
                                            model="houseNr"
                                            id="houseNr"
                                            :label="Translation::get('enter-house-number', 'checkout', 'Enter your house number')"
                                            placeholder="{{Translation::get('house-number', 'checkout', 'House number')}}"
                                        />
                                    </div>
                                    <div class="">
                                        <x-fields.input
                                            required
                                            type="text"
                                            model="street"
                                            id="street"
                                            :label="Translation::get('enter-street', 'checkout', 'Enter your street')"
                                            placeholder="{{Translation::get('street', 'checkout', 'Street')}}"
                                        />
                                    </div>
                                    <div class="">
                                        <x-fields.input
                                            required
                                            type="text"
                                            model="city"
                                            id="city"
                                            :label="Translation::get('enter-city', 'checkout', 'Enter your city')"
                                            placeholder="{{Translation::get('city', 'checkout', 'City')}}"
                                        />
                                    </div>
                                    <div class="">
                                        <x-fields.input
                                            required
                                            type="text"
                                            model="country"
                                            id="country"
                                            :label="Translation::get('enter-country', 'checkout', 'Enter your country')"
                                            placeholder="{{Translation::get('country', 'checkout', 'Country')}}"
                                        />
                                    </div>

                                    @if(Customsetting::get('checkout_form_company_name') != 'hidden')
                                        <div class="md:col-span-2">
                                            <div
                                                class="p-4 space-y-4 bg-primary-300 rounded-md">

                                                <x-fields.checkbox
                                                    model="isCompany"
                                                    id="isCompany"
                                                    labelClass="text-white"
                                                    :label="Translation::get('order-as-company', 'checkout', 'Order as company')"
                                                />

                                                @if($isCompany)
                                                    <div class="grid gap-4 md:grid-cols-2">
                                                        <div>
                                                            <x-fields.input
                                                                labelClass="text-white"
                                                                :required="Customsetting::get('checkout_form_company_name') == 'required'"
                                                                type="text"
                                                                model="company"
                                                                id="company"
                                                                :label="Translation::get('enter-company-name', 'checkout', 'Enter your company name')"
                                                                placeholder="{{Translation::get('company-name', 'checkout', 'Company name')}}"
                                                            />
                                                        </div>
                                                        <div>
                                                            <x-fields.input
                                                                labelClass="text-white"
                                                                type="text"
                                                                model="taxId"
                                                                id="taxId"
                                                                :label="Translation::get('enter-tax-id', 'checkout', 'Enter TAX ID')"
                                                                placeholder="{{Translation::get('tax-id', 'checkout', 'TAX ID')}}"
                                                            />
                                                        </div>
                                                    </div>
                                                @endif
                                            </div>
                                        </div>
                                    @endif

                                    <div class="md:col-span-2">
                                        <div
                                            class="p-4 space-y-4 bg-primary-300 rounded-md">

                                            <x-fields.checkbox
                                                model="invoiceAddress"
                                                id="invoiceAddress"
                                                labelClass="text-white"
                                                :label="Translation::get('seperate-invoice-address', 'checkout', 'Seperate invoice address')"
                                            />

                                            @if($invoiceAddress)
                                                <div class="grid gap-4 md:grid-cols-2">
                                                    <div class="">
                                                        <x-fields.input
                                                            labelClass="text-white"
                                                            required
                                                            type="text"
                                                            model="invoiceZipCode"
                                                            id="invoiceZipCode"
                                                            :label="Translation::get('enter-zip-code', 'checkout', 'Enter zip code')"
                                                            placeholder="{{Translation::get('zip-code', 'checkout', 'Zip code')}}"
                                                        />
                                                    </div>
                                                    <div class="">
                                                        <x-fields.input
                                                            labelClass="text-white"
                                                            required
                                                            type="text"
                                                            model="invoiceHouseNr"
                                                            id="invoiceHouseNr"
                                                            :label="Translation::get('enter-house-number', 'checkout', 'Enter house number')"
                                                            placeholder="{{Translation::get('house-number', 'checkout', 'House number')}}"
                                                        />
                                                    </div>
                                                    <div class="">
                                                        <x-fields.input
                                                            labelClass="text-white"
                                                            required
                                                            type="text"
                                                            model="invoiceStreet"
                                                            id="invoiceStreet"
                                                            :label="Translation::get('enter-street', 'checkout', 'Enter street')"
                                                            placeholder="{{Translation::get('street', 'checkout', 'Street')}}"
                                                        />
                                                    </div>
                                                    <div class="">
                                                        <x-fields.input
                                                            labelClass="text-white"
                                                            required
                                                            type="text"
                                                            model="invoiceCity"
                                                            id="invoiceCity"
                                                            :label="Translation::get('enter-city', 'checkout', 'Enter city')"
                                                            placeholder="{{Translation::get('city', 'checkout', 'City')}}"
                                                        />
                                                    </div>
                                                    <div class="md:col-span-2">
                                                        <x-fields.input
                                                            labelClass="text-white"
                                                            required
                                                            type="text"
                                                            model="invoiceCountry"
                                                            id="invoiceCountry"
                                                            :label="Translation::get('enter-country', 'checkout', 'Enter country')"
                                                            placeholder="{{Translation::get('country', 'checkout', 'Country')}}"
                                                        />
                                                    </div>
                                                </div>
                                            @endif
                                        </div>
                                    </div>

                                    <div class="md:col-span-2">
                                        @if(count($paymentMethods))
                                            <div class="bg-primary-300 rounded-md px-4 py-2 text-white">
                                                <fieldset role="group">
                                                    <label class="block text-sm font-bold">
                                                        {{Translation::get('select-payment-method', 'checkout', 'Choose a payment method')}}
                                                        <span class="text-red-500">*</span>
                                                    </label>
                                                    <div class="mt-4 space-y-4">
                                                        @foreach($paymentMethods as $thisPaymentMethod)
                                                            <div>
                                                                <div class="flex items-center cursor-pointer">
                                                                    <input id="payment_method{{$thisPaymentMethod['id']}}"
                                                                           name="payment_method" required
                                                                           type="radio"
                                                                           wire:model.live="paymentMethod"
                                                                           value="{{$thisPaymentMethod['id']}}"
                                                                           class="focus:ring-primary h-4 w-4 border-gray-300">
                                                                    <label for="payment_method{{$thisPaymentMethod['id']}}"
                                                                           class="ml-3 block text-sm font-medium inline-flex items-center">
                                                                        @if($thisPaymentMethod['image'])
                                                                            <img
                                                                                src="{{ mediaHelper()->getSingleMedia($paymentMethod['image'], 'original')->url ?? '' }}"
                                                                                class="h-20 mr-2">
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
                                                                                        {{Translation::get('select-deposit-payment-method', 'checkout', 'Choose a payment method for the deposit')}}
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
                                                                                                        wire:model.live="depositPaymentMethod"
                                                                                                        class="focus:ring-primary h-4 w-4 border-gray-300">
                                                                                                    <label
                                                                                                        for="deposit_payment_method{{ $thisDepositPaymentMethod['id']}}"
                                                                                                        class="ml-3 block text-sm font-medium inline-flex items-center">
                                                                                                        @if($thisDepositPaymentMethod['image'])
                                                                                                            <img
                                                                                                                src="{{ app(\Dashed\Drift\UrlBuilder::class)->url('dashed', $thisDepositPaymentMethod['image'], [
                                                                                                                    'widen' => 300,
                                                                                                                ]) }}"
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
                                            <p class="bg-red-400 border-2 border-red-800 px-4 py-2 text-white mt-4">{{Translation::get('no-payment-methods-available', 'checkout', 'There are no payment methods available, please contact us.')}}</p>
                                        @endif
                                    </div>

                                    @if($country)
                                        <div class="md:col-span-2">
                                            <div class="bg-primary-300 rounded-md px-4 py-2 text-white mt-1">
                                                <fieldset role="group">
                                                    <label class="block text-sm font-bold">
                                                        {{Translation::get('select-shipping-method', 'checkout', 'Choose a shipping method')}}
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
                                                                           wire:model.live="shippingMethod"
                                                                           class="focus:ring-primary h-4 w-4 border-gray-300">
                                                                    <label for="shipping_method{{$thisShippingMethod['id']}}"
                                                                           class="ml-3 block text-sm font-medium">
                                                                        {{ $thisShippingMethod['name'] }}
                                                                        ({{ CurrencyHelper::formatPrice($thisShippingMethod['costs']) }}
                                                                        )
                                                                    </label>
                                                                </div>
                                                            @endforeach
                                                        @else
                                                            <p class="bg-red-400 border-2 border-red-800 px-4 py-2 text-white">{{Translation::get('no-shipping-methods-available', 'checkout', 'There are not shipping methods available, enter a country')}}</p>
                                                        @endif
                                                    </div>
                                                </fieldset>
                                            </div>
                                        </div>
                                    @endif

                                    <div class="">
                                        <x-fields.input
                                            :required="$postpayPaymentMethod"
                                            type="date"
                                            model="dateOfBirth"
                                            id="dateOfBirth"
                                            :label="Translation::get('enter-dob', 'checkout', 'Enter date of birth')"
                                            placeholder="{{Translation::get('date-of-birth', 'checkout', 'Date of birth')}}"
                                        />
                                    </div>

                                    <div class="">
                                        <x-fields.select
                                            :required="$postpayPaymentMethod"
                                            model="gender"
                                            id="gender"
                                            :label="Translation::get('enter-gender', 'checkout', 'Choose gender')"
                                        >
                                            <option
                                                value="">{{Translation::get('enter-gender', 'checkout', 'Choose a gender')}}</option>
                                            <option
                                                value="m">{{Translation::get('enter-gender-male', 'checkout', 'Male')}}</option>
                                            <option
                                                value="f">{{Translation::get('enter-gender-female', 'checkout', 'Female')}}</option>
                                        </x-fields.select>
                                    </div>
                                </div>
                                @if($postpayPaymentMethod)
                                    <small
                                        class="leading-4 text-xs">{{Translation::get('required-with-postpay', 'checkout', 'These fields are required in combination with AfterPay')}}</small>
                                @endif
                            </div>

                            <div class="grid lg:hidden gap-4 mt-4">
                                <x-fields.textarea
                                    :placeholder="Translation::get('leave-a-note', 'checkout', 'Leave a note')"
                                    model="note"
                                    rows="3"
                                    id="note"
                                />

                                <x-fields.checkbox
                                    model="generalCondition"
                                    id="generalCondition"
                                    :label='Translation::get("accept-general-conditions", "checkout", "Yes, i agree with the <a href=\"/terms-and-conditions\">Terms and Conditions</a> and <a href=\"/privacy-policy\">Privacy Policy</a>")'
                                />

                                <button type="submit"
                                        class="button button-white-on-primary w-full">
                                    {{Translation::get('pay-now', 'checkout', 'Pay now')}}
                                </button>
                            </div>
                        </div>
                    @else
                        <h1 class="text-2xl font-bold">{{Translation::get('no-items-in-cart', 'checkout', 'Geen items in je winkelwagen?')}}</h1>
                        <p>{{Translation::get('go-shop-furter', 'checkout', 'Ga snel verder shoppen!')}}</p>
                    @endif
                </section>
            </main>
        </form>
    </x-checkout-container>
</div>
