<div>
    @if(count($this->cartItems))
        <div class="relative overflow-hidden">
            <div class="absolute right-0 top-0 hidden h-full w-1/2 bg-gradient-to-tr from-primary-700 to-primary-300 lg:block"
                 aria-hidden="true"></div>
            <section class="relative py-12 overflow-hidden lg:py-24">
                <x-container>
                    <div class="relative">
                        <header>
                            <h1 class="text-2xl font-bold tracking-tight lg:text-4xl">{{ Translation::get('checkout-now', 'checkout', 'Afrekenen') }}</h1>
                            <a href="{{ShoppingCart::getCartUrl()}}" class="text-primary-500 hover:text-primary-500/70">
                                {{Translation::get('back-to-cart', 'checkout', 'Terug naar winkelwagen')}}
                            </a>
                        </header>

                        <main class="grid items-start gap-8 mt-6 lg:gap-16 lg:mt-12 lg:grid-cols-5">
                            <aside class="relative order-2 lg:col-span-3">
                                <div
                                    class="absolute inset-y-0 right-0 w-[calc(100vw+1rem)] lg:w-screen -mb-24 -mr-8 border-r border-black/5 bg-white/10 backdrop-blur-2xl rounded-xl">
                                </div>

                                <div class="relative py-6">
                                    <form
                                        class="grid gap-4 lg:grid-cols-2"
                                        wire:submit="submit"
                                    >
                                        <div class="lg:col-span-2">
                                            <h2 class="text-xl font-bold text-primary-500">{{ Translation::get('contact-information', 'checkout', 'Contact informatie') }}</h2>

                                            @if(Auth::guest() && $accountRequired == 2)
                                                <p class="text-black">
                                                    {{Translation::get('already-have-account', 'checkout', 'Heb je al een account?')}}
                                                    <a href="{{AccountHelper::getAccountUrl()}}"
                                                       class="text-primary-500 hover:text-primary-500/70">{{Translation::get('login', 'checkout', 'Inloggen')}}</a>
                                                </p>
                                            @endif
                                        </div>

                                        <x-fields.input
                                            :required="$firstAndLastnameRequired || $postpayPaymentMethod"
                                            type="text"
                                            model="firstName"
                                            id="firstName"
                                            :label="Translation::get('enter-first-name', 'checkout', 'Vul je voornaam in')"
                                            placeholder="{{Translation::get('first-name', 'checkout', 'Voornaam')}}"
                                        />

                                        <x-fields.input
                                            required
                                            type="text"
                                            model="lastName"
                                            id="lastName"
                                            :label="Translation::get('enter-last-name', 'checkout', 'Vul je achternaam in')"
                                            placeholder="{{Translation::get('last-name', 'checkout', 'Achternaam')}}"
                                        />

                                        <x-fields.input
                                            required
                                            placeholder="{{Translation::get('email', 'checkout', 'Email')}}"
                                            type="email"
                                            model="email"
                                            id="email"
                                            :label="Translation::get('enter-email-address', 'checkout', 'Vul je email adres in')"
                                        />

                                        @if($phoneNumberRequired != 0)
                                            <x-fields.input
                                                :required="$phoneNumberRequired == 1"
                                                placeholder="{{Translation::get('phone-number', 'checkout', 'Telefoonnummer')}}"
                                                type="text"
                                                model="phoneNumber"
                                                id="phone_number"
                                                :label="Translation::get('enter-phone-number', 'checkout', 'Vul je telefoonnummer in')"
                                            />
                                        @endif

                                        @if(Auth::guest() && $accountRequired == 2)
                                            <div class="space-y-2 lg:col-span-2">
                                                <label class="inline-block text-sm font-bold">
                                                    {{Translation::get('enter-password-to-create-account', 'checkout', 'Vul een wachtwoord in om gelijk een account aan te maken')}}@if(Customsetting::get('checkout_account') == 'required')
                                                        <span class="text-red-500">*</span>
                                                    @endif
                                                </label>
                                                <div class="grid gap-4 lg:grid-cols-2">
                                                    <x-fields.input
                                                        :required="$accountRequired == 1"
                                                        placeholder="{{Translation::get('password', 'checkout', 'Wachtwoord')}}"
                                                        type="password"
                                                        model="password"
                                                        id="password"
                                                    />
                                                    <x-fields.input
                                                        :required="$accountRequired == 1"
                                                        placeholder="{{Translation::get('password-repeat', 'checkout', 'Wachtwoord herhalen')}}"
                                                        type="password"
                                                        model="passwordConfirmation"
                                                        id="passwordConfirmation"
                                                    />
                                                </div>
                                            </div>
                                        @endif

                                        <div class="lg:col-span-2">
                                            <x-fields.checkbox
                                                model="marketing"
                                                id="marketing"
                                                :label="Translation::get('accept-marketing-text', 'checkout', 'Wil je onze nieuwsbrief ontvangen?')"
                                            />
                                        </div>

                                        @foreach($customFields as $key => $customField)
                                            <x-fields.input
                                                :required="$customField['required']"
                                                :type="$customField['type']"
                                                :model="'customFieldValues.' . $key"
                                                :id="$key"
                                                :label="$customField['label']"
                                                placeholder="{{ $customField['placeholder'] }}"
                                            />
                                        @endforeach

                                        <div class="grid lg:grid-cols-2 gap-2 my-6 lg:col-span-2">
                                            <input placeholder="{{Translation::get('add-discount-code', 'cart', 'Voeg kortingscode toe')}}"
                                                   class="form-input"
                                                   wire:model="discountCode">
                                            <button type="button" wire:click="applyDiscountCode"
                                                    class="w-full button button--primary"
                                                    aria-label="Apply button">{{Translation::get('add-discount', 'cart', 'Add discount code')}}</button>
                                        </div>

                                        <h2 class="pt-4 mt-4 text-xl font-bold border-t lg:col-span-2 text-primary-500 border-black/5">
                                            {{ Translation::get('shipping-information', 'checkout', 'Verzend informatie') }}
                                        </h2>

                                        <x-fields.input
                                            required
                                            type="text"
                                            model="zipCode"
                                            id="zipCode"
                                            :label="Translation::get('enter-zip-code', 'checkout', 'Vul je postcode in')"
                                            placeholder="{{Translation::get('zip-code', 'checkout', 'Postcode')}}"
                                        />

                                        <x-fields.input
                                            required
                                            type="text"
                                            model="houseNr"
                                            id="houseNr"
                                            :label="Translation::get('enter-house-number', 'checkout', 'Vul je huisnummer in')"
                                            placeholder="{{Translation::get('house-number', 'checkout', 'Huisnummer')}}"
                                        />

                                        <x-fields.input
                                            required
                                            type="text"
                                            model="street"
                                            id="street"
                                            :label="Translation::get('enter-street', 'checkout', 'Vul je straat in')"
                                            placeholder="{{Translation::get('street', 'checkout', 'Straat')}}"
                                        />

                                        <x-fields.input
                                            required
                                            type="text"
                                            model="city"
                                            id="city"
                                            :label="Translation::get('enter-city', 'checkout', 'Vul je stad in')"
                                            placeholder="{{Translation::get('city', 'checkout', 'Stad')}}"
                                        />

                                        <x-fields.select
                                            required
                                            model="country"
                                            id="country"
                                            :label="Translation::get('enter-country', 'checkout', 'Vul je land in')"
                                            placeholder="{{Translation::get('country', 'checkout', 'Land')}}"
                                        >
                                            @foreach($countryList as $country)
                                                <option value="{{ $country }}">{{ $country }}</option>
                                            @endforeach
                                        </x-fields.select>

                                        @if($companyRequired != 0)
                                            @if($companyRequired == 2)
                                                <div class="lg:col-span-2">
                                                    <x-fields.checkbox
                                                        model="isCompany"
                                                        id="isCompany"
                                                        :label="Translation::get('order-as-company', 'checkout', 'Bestellen als bedrijf')"
                                                    />
                                                </div>
                                            @endif
                                            @if($isCompany || $companyRequired == 1)
                                                <x-fields.input
                                                    :required="$companyRequired == 1"
                                                    type="text"
                                                    model="company"
                                                    id="company"
                                                    :label="Translation::get('enter-company-name', 'checkout', 'Vul je bedrijfsnaam in')"
                                                    placeholder="{{Translation::get('company-name', 'checkout', 'Bedrijfsnaam')}}"
                                                />
                                                <x-fields.input
                                                    type="text"
                                                    model="taxId"
                                                    id="taxId"
                                                    :label="Translation::get('enter-tax-id', 'checkout', 'Vul BTW ID in')"
                                                    placeholder="{{Translation::get('tax-id', 'checkout', 'BTW ID')}}"
                                                />
                                            @endif
                                        @endif

                                        <div class="lg:col-span-2">
                                            <x-fields.checkbox
                                                model="invoiceAddress"
                                                id="invoiceAddress"
                                                :label="Translation::get('seperate-invoice-address', 'checkout', 'Afwijkend factuur adres')"
                                            />
                                        </div>
                                        @if($invoiceAddress)
                                            <h2 class="pt-4 mt-4 text-xl font-bold border-t lg:col-span-2 text-primary-500 border-black/5">
                                                {{ Translation::get('invoice-address', 'checkout', 'Factuur adres') }}
                                            </h2>
                                            <x-fields.input
                                                required
                                                type="text"
                                                model="invoiceZipCode"
                                                id="invoiceZipCode"
                                                :label="Translation::get('enter-zip-code', 'checkout', 'Vul je postcode in')"
                                                placeholder="{{Translation::get('zip-code', 'checkout', 'Postcode')}}"
                                            />
                                            <x-fields.input
                                                required
                                                type="text"
                                                model="invoiceHouseNr"
                                                id="invoiceHouseNr"
                                                :label="Translation::get('enter-house-number', 'checkout', 'Vul je huisnummer in')"
                                                placeholder="{{Translation::get('house-number', 'checkout', 'Huisnummer')}}"
                                            />
                                            <x-fields.input
                                                required
                                                type="text"
                                                model="invoiceStreet"
                                                id="invoiceStreet"
                                                :label="Translation::get('enter-street', 'checkout', 'Vul je straat in')"
                                                placeholder="{{Translation::get('street', 'checkout', 'Straat')}}"
                                            />
                                            <x-fields.input
                                                required
                                                type="text"
                                                model="invoiceCity"
                                                id="invoiceCity"
                                                :label="Translation::get('enter-city', 'checkout', 'Vul je stad in')"
                                                placeholder="{{Translation::get('city', 'checkout', 'Stad')}}"
                                            />
                                            <x-fields.input
                                                required
                                                type="text"
                                                model="invoiceCountry"
                                                id="invoiceCountry"
                                                :label="Translation::get('enter-country', 'checkout', 'Vul je land in')"
                                                placeholder="{{Translation::get('country', 'checkout', 'Land')}}"
                                            />
                                        @endif

                                        <h2 class="pt-4 mt-4 text-xl font-bold border-t lg:col-span-2 text-primary-500 border-black/5">
                                            {{ Translation::get('payment-method', 'checkout', 'Betaalmethode') }}
                                        </h2>

                                        <div class="grid gap-4 lg:grid-cols-2 lg:col-span-2">
                                            @if(count($paymentMethods))
                                                @foreach($paymentMethods as $thisPaymentMethod)
                                                    <div class="grid gap-2">
                                                        <label class="p-4 cursor-pointer hover:bg-primary-500/50 rounded-lg relative flex items-center">
                                                            <input required
                                                                   id="payment_method{{$thisPaymentMethod['id']}}"
                                                                   class="transition shadow-inner focus:ring-primary-500 text-primary-500 border-black/20 shadow-black/5 peer"
                                                                   name="payment_method" type="radio"
                                                                   wire:model.live="paymentMethod"
                                                                   value="{{$thisPaymentMethod['id']}}">

                                                            <div
                                                                class="absolute inset-0 transition rounded-lg peer-checked:ring-2 peer-checked:ring-primary-500 peer-checked:shadow-xl peer-checked:shadow-black/5"></div>

                                                            <span class="ml-3 mr-auto">
                                                            {{ $thisPaymentMethod['name'] }}
                                                                @if($thisPaymentMethod['extra_costs'] > 0)
                                                                    <span>
                                                                        (+ {{ CurrencyHelper::formatPrice($thisPaymentMethod['extra_costs']) }})
                                                                    </span>
                                                                @endif
                                            </span>

                                                            @if($thisPaymentMethod['image'])
                                                                <img
                                                                    src="{{ mediaHelper()->getSingleMedia($thisPaymentMethod['image'])->url ?? '' }}"
                                                                    class="object-contain h-12">
                                                            @endif
                                                        </label>
                                                        @if($paymentMethod == $thisPaymentMethod['id'] && ($thisPaymentMethod['additional_info'] || count($depositPaymentMethods)))
                                                            <div class="bg-white ring-2 ring-primary-500 rounded-lg px-4 py-2 mt-2 text-black">
                                                                @if($thisPaymentMethod['additional_info'])
                                                                    <p class="payment-method-content">
                                                                        {!! nl2br($thisPaymentMethod['additional_info']) !!}
                                                                    </p>
                                                                @endif
                                                            </div>
                                                            @if(count($depositPaymentMethods) && $depositAmount > 0)
                                                                <div class="bg-white ring-2 ring-primary-500 rounded-lg px-4 py-2 mt-2 text-black">
                                                                    <div>
                                                                        <fieldset role="group">
                                                                            <label
                                                                                class="block text-md mt-1">
                                                                                {{Translation::get('select-deposit-payment-method', 'checkout', 'Kies een betaalmethode voor de aanbetaling van :price:', 'text', [
                                                                                    'price' => CurrencyHelper::formatPrice($depositAmount)
                                                                                ])}}
                                                                                <span
                                                                                    class="text-red-500">*</span>
                                                                            </label>
                                                                            <div class="mt-4 space-y-4">
                                                                                @foreach($depositPaymentMethods as $thisDepositPaymentMethod)
                                                                                    <label class="p-4 cursor-pointer hover:bg-primary-500/50 rounded-lg relative flex items-center">
                                                                                        <input required
                                                                                               id="depositPaymentMethod{{$thisDepositPaymentMethod['id']}}"
                                                                                               class="transition shadow-inner focus:ring-primary-500 text-primary-500 border-black/20 shadow-black/5 peer"
                                                                                               name="depositPaymentMethod"
                                                                                               type="radio"
                                                                                               wire:model.live="depositPaymentMethod"
                                                                                               value="{{$thisDepositPaymentMethod['id']}}">

                                                                                        <div
                                                                                            class="absolute inset-0 transition rounded-lg peer-checked:ring-2 peer-checked:ring-primary-500 peer-checked:shadow-xl peer-checked:shadow-black/5"></div>

                                                                                        <span class="ml-3 mr-auto">
                                                            {{ $thisDepositPaymentMethod['name'] }}
                                                                                            @if($thisDepositPaymentMethod['extra_costs'] > 0)
                                                                                                <span>
                                                                        (+ {{ CurrencyHelper::formatPrice($thisDepositPaymentMethod['extra_costs']) }})
                                                                    </span>
                                                                                            @endif
                                            </span>

                                                                                        @if($thisDepositPaymentMethod['image'])
                                                                                            <img
                                                                                                src="{{ mediaHelper()->getSingleMedia($thisDepositPaymentMethod['image'])->url ?? '' }}"
                                                                                                class="object-contain h-12">
                                                                                        @endif
                                                                                    </label>
                                                                                @endforeach
                                                                            </div>
                                                                        </fieldset>
                                                                    </div>
                                                                </div>
                                                            @endif
                                                        @endif
                                                    </div>

                                                @endforeach
                                            @else
                                                <p class="bg-red-400 border-2 border-red-800 px-4 py-2 text-white mt-4">{{Translation::get('no-payment-methods-available', 'checkout', 'Er zijn geen betaalmethodes beschikbaar, neem contact met ons op.')}}</p>
                                            @endif
                                        </div>

                                        <h2 class="pt-4 mt-4 text-xl font-bold border-t lg:col-span-2 text-primary-500 border-black/5">
                                            {{ Translation::get('shipping-method', 'checkout', 'Verzend methode') }}
                                        </h2>

                                        @if($country && count($shippingMethods))
                                            <div class="grid gap-4 lg:grid-cols-2 lg:col-span-2">
                                                @foreach($shippingMethods as $thisShippingMethod)
                                                    <label class="relative flex items-center p-4 cursor-pointer hover:bg-primary-500/50 rounded-lg">
                                                        <input id="shipping_method{{$thisShippingMethod['id']}}"
                                                               name="shipping_method" required
                                                               class="transition shadow-inner focus:ring-primary-500 text-primary-500 border-black/20 shadow-black/5 peer"
                                                               type="radio"
                                                               value="{{ $thisShippingMethod['id'] }}"
                                                               wire:model.live="shippingMethod">

                                                        <div
                                                            class="absolute inset-0 transition rounded-lg peer-checked:ring-2 peer-checked:ring-primary-500 peer-checked:shadow-xl peer-checked:shadow-black/5"></div>

                                                        <span class="ml-3 mr-auto">
                                                        {{ $thisShippingMethod['name'] }}
                                                            @if($thisShippingMethod['costs'] > 0)
                                                                ({{ CurrencyHelper::formatPrice($thisShippingMethod['costs']) }}
                                                                )
                                                            @endif
                                            </span>
                                                    </label>
                                                @endforeach
                                            </div>
                                        @else
                                            <p class="bg-red-400 border-2 border-red-800 px-4 py-2 text-white">{{Translation::get('no-shipping-methods-available', 'checkout', 'Er zijn geen verzendmethodes beschikbaar, vul een land in.')}}</p>
                                        @endif

                                        @if($postpayPaymentMethod)
                                            <x-fields.input
                                                :required="$postpayPaymentMethod"
                                                type="date"
                                                model="dateOfBirth"
                                                id="dateOfBirth"
                                                :label="Translation::get('enter-dob', 'checkout', 'Vul je geboortedatum in')"
                                                placeholder="{{Translation::get('date-of-birth', 'checkout', 'Geboortedatum')}}"
                                            />
                                            <x-fields.select
                                                :required="$postpayPaymentMethod"
                                                model="gender"
                                                id="gender"
                                                :label="Translation::get('enter-gender', 'checkout', 'Kies een geslacht')"
                                            >
                                                <option
                                                    value="">{{Translation::get('enter-gender', 'checkout', 'Kies een geslacht')}}</option>
                                                <option
                                                    value="m">{{Translation::get('enter-gender-male', 'checkout', 'Man')}}</option>
                                                <option
                                                    value="f">{{Translation::get('enter-gender-female', 'checkout', 'Vrouw')}}</option>
                                            </x-fields.select>
                                            <small
                                                class="leading-4 text-xs lg:col-span-2">{{Translation::get('required-with-postpay', 'checkout', 'Deze velden zijn verplicht in combinatie met achteraf betalen')}}</small>
                                        @endif

                                        <hr class="mt-4 lg:col-span-2 border-black/5"/>

                                        <div class="lg:col-span-2">
                                            <x-fields.textarea
                                                :placeholder="Translation::get('leave-a-note', 'checkout', 'Laat een notitie achter')"
                                                model="note"
                                                rows="3"
                                                id="note"
                                            />
                                        </div>

                                        <div class="lg:col-span-2">
                                            <x-fields.checkbox
                                                required
                                                model="generalCondition"
                                                id="generalCondition"
                                                labelClass="checkout-content"
                                                :label='Translation::get("accept-general-conditions", "checkout", "Ja, ik ga akkoord met de <a href=\"/algemene-voorwaarden\">Algemene Voorwaarden</a> en <a href=\"/privacy-beleid\">Privacy Statement</a>", "editor")'
                                            />
                                        </div>

                                        <button type="submit"
                                                class="inline-flex items-center justify-between gap-4 mt-4 button button--primary w-full lg:col-span-2">
                                            <span>{{Translation::get('pay-now', 'cart', 'Afrekenen')}}</span>

                                            <x-lucide-lock class="w-5 h-5"/>
                                        </button>
                                    </form>
                                </div>
                            </aside>

                            <aside
                                class="order-1 text-white lg:order-3 lg:col-span-2 rounded-xl">
                                <h2 class="text-lg font-bold text-primary-500 lg:text-white">{{Translation::get('your-order', 'cart', 'Jouw bestelling')}}</h2>

                                <div class="mt-4 rounded-lg border border-gray-200 text-white shadow-sm bg-primary-500 lg:bg-transparent">
                                    <h3 class="sr-only">{{Translation::get('items-in-your-cart', 'cart', 'Producten in je winkelwagen')}}</h3>
                                    <div class="px-4">
                                        <x-cart.cart-items :items="$this->cartItems" forceWhite="true"/>
                                    </div>
                                    <dl class="space-y-6 px-4 pb-6 sm:px-6 text-white">
                                        <div class="flex items-center justify-between border-t border-gray-200 pt-6">
                                            <dt class="text-sm">{{Translation::get('subtotal', 'cart', 'Subtotaal')}}</dt>
                                            <dd class="text-sm font-bold">{{ CurrencyHelper::formatPrice($this->subtotal) }}</dd>
                                        </div>
                                        @if($discount > 0.00)
                                            <div class="flex items-center justify-between">
                                                <dt class="text-sm">{{Translation::get('discount', 'cart', 'Korting')}}</dt>
                                                <dd class="text-sm font-bold">{{ CurrencyHelper::formatPrice($this->discount) }}</dd>
                                            </div>
                                        @endif
                                        @if($shippingMethod)
                                            <div class="flex items-center justify-between">
                                                @if($shippingCosts > 0)
                                                    <dt class="text-sm">{{Translation::get('shipping-costs', 'checkout', 'Verzendkosten')}}</dt>
                                                    <dd class="text-sm font-bold">{{ CurrencyHelper::formatPrice($shippingCosts) }}</dd>
                                                @else
                                                    <dt class="text-sm">{{Translation::get('shipping-costs', 'checkout', 'Verzendkosten')}}</dt>
                                                    <dd class="text-sm font-bold">{{Translation::get('free', 'checkout', 'Gratis')}}</dd>
                                                @endif
                                            </div>
                                        @endif
                                        @if($paymentCosts)
                                            <div class="flex items-center justify-between">
                                                <dt class="text-sm">{{Translation::get('payment-costs', 'checkout', 'Betaalmethode kosten')}}</dt>
                                                <dd class="text-sm font-bold">{{ CurrencyHelper::formatPrice($paymentCosts) }}</dd>
                                            </div>
                                        @endif
                                        <div class="flex items-center justify-between">
                                            <dt class="text-sm">{{Translation::get('btw', 'cart', 'BTW')}}</dt>
                                            <dd class="text-sm font-bold">{{ CurrencyHelper::formatPrice($tax) }}</dd>
                                        </div>
                                        <div class="flex items-center justify-between border-t border-gray-200 pt-6">
                                            <dt class="text-base font-bold">{{Translation::get('total', 'cart', 'Totaal')}}</dt>
                                            <dd class="text-base font-bold">{{ CurrencyHelper::formatPrice($total) }}</dd>
                                        </div>
                                        @if($depositAmount > 0)
                                            <div class="flex items-center justify-between border-t border-gray-200 pt-6">
                                                <dt class="text-base font-bold">{{Translation::get('pre-payment', 'cart', 'Aanbetaling')}}</dt>
                                                <dd class="text-base font-bold">{{ CurrencyHelper::formatPrice($depositAmount) }}</dd>
                                            </div>
                                        @endif
                                    </dl>
                                </div>
                            </aside>
                        </main>
                    </div>
                </x-container>
            </section>
        </div>
    @else
        <x-blocks.header :data="[
            'title' => Translation::get('no-items-in-cart', 'cart', 'Geen items in je winkelwagen!'),
            'subtitle' => Translation::get('keep-shopping', 'cart', 'Verder shoppen!'),
            'image' => Translation::get('image', 'cart', '', 'image'),
        ]"></x-blocks.header>
    @endif
</div>

<x-dashed-core::global-blocks name="checkout-page"/>
