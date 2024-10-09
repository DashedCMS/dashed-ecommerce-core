<div>
    @if(count($this->cartItems))
        <section class="relative py-12 overflow-hidden md:py-24">

            <x-container>
                <div class="relative">
                    <header>
                        <h1 class="text-2xl font-bold tracking-tight md:text-4xl">{{ Translation::get('checkout-now', 'checkout', 'Afrekenen') }}</h1>
                        <a href="{{ShoppingCart::getCartUrl()}}" class="text-primary-800 hover:text-primary-800/70">
                            {{Translation::get('back-to-cart', 'checkout', 'Terug naar winkelwagen')}}
                        </a>
                    </header>

                    <main class="grid items-start gap-8 mt-6 md:gap-16 md:mt-12 md:grid-cols-5">
                        <aside class="relative order-2 md:col-span-3">
                            <div
                                class="absolute inset-y-0 right-0 w-[calc(100vw+1rem)] md:w-screen -mb-24 -mr-8 border-r border-black/5 bg-white/10 backdrop-blur-lg">
                            </div>

                            <div class="relative py-6">
                                <form
                                    class="grid gap-4 md:grid-cols-2"
                                    wire:submit="submit"
                                >
                                    <div class="md:col-span-2">
                                        <h2 class="text-xl font-bold text-primary-800">{{ Translation::get('contact-information', 'checkout', 'Contact informatie') }}</h2>

                                        @if(Auth::guest() && Customsetting::get('checkout_account') != 'disabled')
                                            <p class="text-black">
                                                {{Translation::get('already-have-account', 'checkout', 'Heb je al een account?')}}
                                                <a href="{{AccountHelper::getAccountUrl()}}"
                                                   class="text-primary-800 hover:text-primary-800/70">{{Translation::get('login', 'checkout', 'Inloggen')}}</a>
                                            </p>
                                        @endif
                                    </div>

                                    <div class="space-y-2">
                                        <label class="inline-block text-sm font-bold">
                                            {{Translation::get('enter-first-name', 'checkout', 'Vul je voornaam in')}}@if(Customsetting::get('checkout_form_name') == 'full' || $postpayPaymentMethod)
                                                <span class="text-red-500">*</span>
                                            @endif
                                        </label>
                                        <input type="text" class="form-input" id="first_name" name="first_name"
                                               @if(Customsetting::get('checkout_form_name') == 'full' || $postpayPaymentMethod) required
                                               @endif
                                               wire:model.blur="firstName"
                                               placeholder="{{Translation::get('first-name', 'checkout', 'Voornaam')}}">
                                    </div>
                                    <div class="space-y-2">
                                        <label class="inline-block text-sm font-bold">
                                            {{Translation::get('enter-last-name', 'checkout', 'Vul je achternaam in')}}
                                            <span class="text-red-500">*</span>
                                        </label>
                                        <input type="text" class="form-input" id="last_name" name="last_name"
                                               required
                                               wire:model.blur="lastName"
                                               placeholder="{{Translation::get('last-name', 'checkout', 'Achternaam')}}">
                                    </div>

                                    <div class="space-y-2 md:col-span-2">
                                        <label
                                            class="inline-block text-sm font-bold">{{Translation::get('enter-email-address', 'checkout', 'Vul je email adres in')}}
                                            <span class="text-red-500">*</span></label>

                                        <input type="email" class="form-input" id="email" name="email" required
                                               wire:model.blur="email"
                                               placeholder="{{Translation::get('email', 'checkout', 'Email')}}">
                                    </div>

                                    @if(Auth::guest() && Customsetting::get('checkout_account') != 'disabled')
                                        <div class="space-y-2 md:col-span-2">
                                            <label class="inline-block text-sm font-bold">
                                                {{Translation::get('enter-password-to-create-account', 'checkout', 'Vul een wachtwoord in om gelijk een account aan te maken')}}@if(Customsetting::get('checkout_account') == 'required')
                                                    <span class="text-red-500">*</span>
                                                @endif
                                            </label>
                                            <div class="grid gap-4 md:grid-cols-2">
                                                <input type="password" class="form-input" id="password" name="password"
                                                       placeholder="{{Translation::get('password', 'checkout', 'Wachtwoord')}}"
                                                       wire:model.blur="password"
                                                       @if(Customsetting::get('checkout_account') == 'required') required @endif>
                                                <input type="password" class="form-input" id="password_confirmation"
                                                       name="password_confirmation"
                                                       wire:model.blur="passwordConfirmation"
                                                       placeholder="{{Translation::get('password-repeat', 'checkout', 'Wachtwoord herhalen')}}"
                                                       @if(Customsetting::get('checkout_account') == 'required') required @endif>
                                            </div>
                                        </div>
                                    @endif

                                    <h2 class="pt-4 mt-4 text-xl font-bold border-t md:col-span-2 text-primary-800 border-black/5">
                                        {{ Translation::get('shipping-address', 'checkout', 'Verzendmethode') }}
                                    </h2>

                                    <div class="space-y-2">
                                        <label
                                            class="inline-block text-sm font-bold">{{Translation::get('enter-street', 'checkout', 'Vul je straat in')}}
                                            <span
                                                class="text-red-500">*</span></label>

                                        <input type="text" class="form-input" id="street" name="street" required
                                               wire:model.blur="street"
                                               placeholder="{{Translation::get('street', 'checkout', 'Straat')}}">
                                    </div>

                                    <div class="space-y-2">
                                        <label
                                            class="inline-block text-sm font-bold">{{Translation::get('enter-house-number', 'checkout', 'Vul je huisnummer in')}}
                                            <span
                                                class="text-red-500">*</span></label>

                                        <input type="text" class="form-input" id="house_nr" name="house_nr" required
                                               wire:model.blur="houseNr"
                                               placeholder="{{Translation::get('house-number', 'checkout', 'Huisnummer')}}">
                                    </div>

                                    <div class="space-y-2">
                                        <label
                                            class="inline-block text-sm font-bold">{{Translation::get('enter-zip-code', 'checkout', 'Vul je postcode in')}}
                                            <span
                                                class="text-red-500">*</span></label>

                                        <input type="text" class="form-input" id="zip_code" name="zip_code" required
                                               wire:model.blur="zipCode"
                                               placeholder="{{Translation::get('zip-code', 'checkout', 'Postcode')}}">
                                    </div>

                                    <div class="space-y-2">
                                        <label
                                            class="inline-block text-sm font-bold">{{Translation::get('enter-city', 'checkout', 'Vul je stad in')}}
                                            <span
                                                class="text-red-500">*</span></label>

                                        <input type="text" class="form-input" id="city" name="city" required
                                               wire:model.blur="city"
                                               placeholder="{{Translation::get('city', 'checkout', 'Stad')}}">
                                    </div>

                                    <div class="space-y-2 md:col-span-2">
                                        <label
                                            class="inline-block text-sm font-bold">{{Translation::get('enter-country', 'checkout', 'Vul je land in')}}
                                            <span
                                                class="text-red-500">*</span></label>

                                        <input type="text" class="form-input" id="country" name="country" required
                                               wire:model.blur="country"
                                               placeholder="{{Translation::get('country', 'checkout', 'Land')}}">
                                    </div>

                                    <label class="flex items-center gap-2 text-sm font-bold md:col-span-2">
                                        <input
                                            wire:model.live="invoiceAddress"
                                            class="transition rounded-sm shadow-inner border-black/20 text-primary-800 shadow-black/5 focus:ring-primary-800"
                                            type="checkbox" name="invoice_address" id="invoice_address">

                                        <span>{!! Translation::get('seperate-invoice-address', 'checkout', 'Afwijkend factuur adres') !!}</span>
                                    </label>
                                    @if($invoiceAddress)
                                        <h2 class="pt-4 mt-4 text-xl font-bold border-t md:col-span-2 text-primary-800 border-black/5">
                                            {{ Translation::get('invoice-address', 'checkout', 'Factuur adres') }}
                                        </h2>
                                        <div class="space-y-2">
                                            <label class="inline-block text-sm font-bold">
                                                {{Translation::get('enter-street', 'checkout', 'Vul je straat in')}}
                                                <span class="text-red-500">*</span>
                                            </label>
                                            <input type="text" class="form-input"
                                                   id="invoice_street"
                                                   required
                                                   onFocus="geolocate()"
                                                   wire:model.blur="invoiceStreet"
                                                   placeholder="{{Translation::get('street', 'checkout', 'Straat')}}">
                                        </div>
                                        <div class="space-y-2">
                                            <label class="inline-block text-sm font-bold">
                                                {{Translation::get('enter-house-number', 'checkout', 'Vul je huisnummer in')}}
                                                <span class="text-red-500">*</span>
                                            </label>
                                            <input type="text" class="form-input"
                                                   id="invoice_house_nr"
                                                   name="invoice_house_nr"
                                                   required
                                                   wire:model.blur="invoiceHouseNr"
                                                   placeholder="{{Translation::get('house-number', 'checkout', 'Huisnummer')}}">
                                        </div>
                                        <div class="space-y-2">
                                            <label class="inline-block text-sm font-bold">
                                                {{Translation::get('enter-zip-code', 'checkout', 'Vul je postcode in')}}
                                                <span class="text-red-500">*</span>
                                            </label>
                                            <input type="text" class="form-input"
                                                   id="invoice_zip_code"
                                                   name="invoice_zip_code"
                                                   required
                                                   wire:model.blur="invoiceZipCode"
                                                   placeholder="{{Translation::get('zip-code', 'checkout', 'Postcode')}}">
                                        </div>
                                        <div class="space-y-2">
                                            <label class="inline-block text-sm font-bold">
                                                {{Translation::get('enter-city', 'checkout', 'Vul je stad in')}}
                                                <span class="text-red-500">*</span>
                                            </label>
                                            <input type="text" class="form-input" id="invoice_city"
                                                   name="invoice_city"
                                                   required
                                                   wire:model.blur="invoiceCity"
                                                   placeholder="{{Translation::get('city', 'checkout', 'Stad')}}">
                                        </div>
                                        <div class="space-y-2 md:col-span-2">
                                            <label class="inline-block text-sm font-bold">
                                                {{Translation::get('enter-country', 'checkout', 'Vul je land in')}}
                                                <span class="text-red-500">*</span>
                                            </label>
                                            <input type="text" class="form-input"
                                                   id="invoice_country"
                                                   name="invoice_country"
                                                   required
                                                   wire:model.blur="invoiceCountry"
                                                   placeholder="{{Translation::get('country', 'checkout', 'Land')}}">
                                        </div>
                                    @endif

                                    <h2 class="pt-4 mt-4 text-xl font-bold border-t md:col-span-2 text-primary-800 border-black/5">
                                        {{ Translation::get('payment-method', 'checkout', 'Betaalmethode') }}
                                    </h2>

                                    <div class="grid gap-4 md:grid-cols-2 md:col-span-2">
                                        @if(count($paymentMethods))
                                            @foreach($paymentMethods as $thisPaymentMethod)
                                                <label class="relative flex items-center p-4 cursor-pointer hover:bg-primary-500/50 rounded-lg">
                                                    <input required
                                                           id="payment_method{{$thisPaymentMethod['id']}}"
                                                           class="transition shadow-inner focus:ring-primary-800 text-primary-800 border-black/20 shadow-black/5 peer"
                                                           name="payment_method" type="radio"
                                                           wire:model.live="paymentMethod"
                                                           value="{{$thisPaymentMethod['id']}}">

                                                    <div
                                                        class="absolute inset-0 transition rounded-lg peer-checked:ring-2 peer-checked:ring-primary-800 peer-checked:shadow-xl peer-checked:shadow-black/5"></div>

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
                                                            src="{{ mediaHelper()->getSingleImage($thisPaymentMethod['image'])->url ?? '' }}"
                                                            class="object-contain h-6">
                                                    @endif
                                                </label>
                                            @endforeach
                                        @else
                                            <p class="bg-red-400 border-2 border-red-800 px-4 py-2 text-white mt-4">{{Translation::get('no-payment-methods-available', 'checkout', 'Er zijn geen betaalmethodes beschikbaar, neem contact met ons op.')}}</p>
                                        @endif
                                    </div>

                                    <h2 class="pt-4 mt-4 text-xl font-bold border-t md:col-span-2 text-primary-800 border-black/5">
                                        {{ Translation::get('shipping-method', 'checkout', 'Verzend methode') }}
                                    </h2>

                                    @if($country && count($shippingMethods))
                                        <div class="grid gap-4 md:grid-cols-2 md:col-span-2">
                                            @foreach($shippingMethods as $thisShippingMethod)
                                                <label class="relative flex items-center p-4 cursor-pointer hover:bg-primary-500/50 rounded-lg">
                                                    <input id="shipping_method{{$thisShippingMethod['id']}}"
                                                           name="shipping_method" required
                                                           class="transition shadow-inner focus:ring-primary-800 text-primary-800 border-black/20 shadow-black/5 peer"
                                                           type="radio"
                                                           value="{{ $thisShippingMethod['id'] }}"
                                                           wire:model.live="shippingMethod">

                                                    <div
                                                        class="absolute inset-0 transition rounded-lg peer-checked:ring-2 peer-checked:ring-primary-800 peer-checked:shadow-xl peer-checked:shadow-black/5"></div>

                                                    <span class="ml-3 mr-auto"> {{ $thisShippingMethod['name'] }}
                                                            ({{ CurrencyHelper::formatPrice($thisShippingMethod['costs']) }})
                                            </span>
                                                </label>
                                            @endforeach
                                        </div>
                                    @else
                                        <p class="bg-red-400 border-2 border-red-800 px-4 py-2 text-white">{{Translation::get('no-shipping-methods-available', 'checkout', 'Er zijn geen verzendmethodes beschikbaar, vul een land in.')}}</p>
                                    @endif

                                    <hr class="mt-4 md:col-span-2 border-black/5"/>

                                    <textarea wire:model.blur="note"
                                              placeholder="{{Translation::get('leave-a-note', 'checkout', 'Laat een notitie achter')}}"
                                              class="md:col-span-2 form-input" name="note" id="note"></textarea>

                                    <label class="flex items-center gap-2 text-sm font-bold md:col-span-2">
                                        <input
                                            wire:model.live="generalCondition" required
                                            class="transition rounded-sm shadow-inner border-black/20 text-primary-800 shadow-black/5 focus:ring-primary-800"
                                            type="checkbox" name="general_condition" id="general_condition">

                                        <span>{!! Translation::get('accept-general-conditions', 'checkout', 'Ja, ik ga akkoord met de <a href="/algemene-voorwaarden">Algemene Voorwaarden</a> en <a href="/privacy-beleid">Privacy Statement</a>', 'editor') !!}</span>
                                    </label>

                                    <button type="submit"
                                            class="inline-flex items-center justify-between gap-4 mt-4 button button--primary-dark w-full md:col-span-2">
                                        <span>{{Translation::get('pay-now', 'cart', 'Afrekenen')}}</span>

                                        <x-lucide-lock class="w-5 h-5"/>
                                    </button>
                                </form>
                            </div>
                        </aside>

                        <aside
                            class="order-1 text-white md:order-3 md:col-span-2 rounded-xl">
                            <h2 class="text-lg font-bold text-primary-800">{{Translation::get('your-order', 'cart', 'Jouw bestelling')}}</h2>

                            <div class="mt-4 rounded-lg border border-gray-200 text-white shadow-sm bg-primary-800">
                                <h3 class="sr-only">{{Translation::get('items-in-your-cart', 'cart', 'Producten in je winkelwagen')}}</h3>
                                <ul role="list" class="divide-y divide-gray-200">
                                    @foreach($this->cartItems as $item)
                                        <li class="flex px-4 py-6 sm:px-6">
                                            <div class="flex-shrink-0">
                                                @if($item->model->firstImage)
                                                    <x-drift::image
                                                        class="h-24 w-24 rounded-md object-cover object-center sm:h-48 sm:w-48"
                                                        config="dashed"
                                                        :path="$item->model->firstImage"
                                                        :alt=" $item->model->name"
                                                        :manipulations="[
                                                    'widen' => 400,
                                                ]"
                                                    />
                                                @endif
                                            </div>

                                            <div class="ml-4 flex flex-1 flex-col justify-between sm:ml-6">
                                                <div class="relative pr-9 sm:grid sm:gap-x-6 sm:pr-0">
                                                    <div>
                                                        <div class="flex justify-between">
                                                            <h3 class="text-sm">
                                                                <a href="{{ $item->model->getUrl() }}"
                                                                   class="font-bold text-primary-200 hover:text-primary-500 trans">
                                                                    {{ $item->model->name }}
                                                                </a>
                                                            </h3>
                                                        </div>
                                                        <div class="mt-1 flex text-sm">
                                                            @foreach($item->options as $option)
                                                                @if($loop->first)
                                                                    <p class="">{{$option['name'] . ':'}}{{$option['value']}}</p>
                                                                @else
                                                                    <p class="ml-4 border-l border-gray-200 pl-4">{{$option['name'] . ':'}}{{$option['value']}}</p>
                                                                @endif
                                                            @endforeach
                                                        </div>
                                                        <p class="mt-1 text-sm font-bold ">{{CurrencyHelper::formatPrice($item->model->currentPrice * $item->qty)}}</p>
                                                    </div>

                                                    <div class="mt-4">
                                                        <div
                                                            class="inline-flex items-center p-1 transition rounded bg-white focus-within:bg-white focus-within:ring-2 focus-within:ring-primary-800">
                                                            <button
                                                                wire:click="changeQuantity('{{ $item->rowId }}', '{{ $item->qty - 1 }}')"
                                                                class="grid w-6 h-6 bg-primary-500 rounded shadow-xl place-items-center text-white hover:bg-primary-800 hover:text-white shadow-primary-800/10 ring-1 ring-black/5"
                                                            >
                                                                <x-lucide-minus class="w-4 h-4"/>
                                                            </button>

                                                            <input
                                                                class="w-[4ch] px-0 py-0.5 focus:ring-0 text-center bg-transparent border-none text-primary-500 font-bold"
                                                                value="{{$item->qty}}"
                                                                disabled
                                                                min="0" max="{{$item->model->stock()}}">

                                                            <button
                                                                wire:click="changeQuantity('{{ $item->rowId }}', '{{ $item->qty + 1 }}')"
                                                                class="grid w-6 h-6 bg-primary-500 rounded shadow-xl place-items-center text-white hover:bg-primary-800 hover:text-white shadow-primary-800/10 ring-1 ring-black/5"
                                                            >
                                                                <x-lucide-plus class="w-4 h-4"/>
                                                            </button>

                                                            <div class="absolute right-0 top-0">
                                                                <button
                                                                    wire:click="changeQuantity('{{ $item->rowId }}', '0')"
                                                                    type="button"
                                                                    class="-m-2 inline-flex p-2 text-white hover:text-red-500 rounded-full bg-primary-800 trans">
                                                                    <span class="sr-only">{{ Translation::get('remove', 'cart', 'Verwijder') }}</span>
                                                                    <x-lucide-trash class="h-5 w-5"/>
                                                                </button>
                                                            </div>
                                                        </div>
                                                    </div>
                                                </div>

                                                <div class="mt-4 flex space-x-2 text-sm text-gray-700">
                                                    @if($item->model->inStock())
                                                        @if($item->model->hasDirectSellableStock())
                                                            @if($item->model->stock() > 10)
                                                                <p class="text-md tracking-wider text-white flex items-center font-bold"><span
                                                                        class="mr-1"><svg class="w-6 h-6" fill="none" stroke="currentColor"
                                                                                          viewBox="0 0 24 24"
                                                                                          xmlns="http://www.w3.org/2000/svg"><path
                                                                                stroke-linecap="round" stroke-linejoin="round"
                                                                                stroke-width="2"
                                                                                d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
                                            </span>
                                                                    {{Translation::get('product-in-stock', 'product', 'Op voorraad')}}
                                                                </p>
                                                            @else
                                                                <p class="text-md tracking-wider text-white flex items-center font-bold"><span
                                                                        class="mr-1"><svg class="w-6 h-6" fill="none" stroke="currentColor"
                                                                                          viewBox="0 0 24 24"
                                                                                          xmlns="http://www.w3.org/2000/svg"><path
                                                                                stroke-linecap="round" stroke-linejoin="round"
                                                                                stroke-width="2"
                                                                                d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
                                            </span>
                                                                    {{Translation::get('product-in-stock-specific', 'product', 'Nog :count: op voorraad', 'text', [
                                            'count' => $item->model->stock()
                                            ])}}
                                                                </p>
                                                            @endif
                                                        @else
                                                            @if($item->model->expectedDeliveryInDays())
                                                                <p class="font-bold italic text-md flex items-center gap-1 text-primary-800">
                                                                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="size-6">
                                                                        <path stroke-linecap="round" stroke-linejoin="round" d="M8.25 18.75a1.5 1.5 0 0 1-3 0m3 0a1.5 1.5 0 0 0-3 0m3 0h6m-9 0H3.375a1.125 1.125 0 0 1-1.125-1.125V14.25m17.25 4.5a1.5 1.5 0 0 1-3 0m3 0a1.5 1.5 0 0 0-3 0m3 0h1.125c.621 0 1.129-.504 1.09-1.124a17.902 17.902 0 0 0-3.213-9.193 2.056 2.056 0 0 0-1.58-.86H14.25M16.5 18.75h-2.25m0-11.177v-.958c0-.568-.422-1.048-.987-1.106a48.554 48.554 0 0 0-10.026 0 1.106 1.106 0 0 0-.987 1.106v7.635m12-6.677v6.677m0 4.5v-4.5m0 0h-12" />
                                                                    </svg>
                                                                    <span>{{ Translation::get('pre-order-product-static-delivery-time', 'product', 'Levering duurt circa :days: dagen', 'text', [
                                                'days' => $item->model->expectedDeliveryInDays()
                                            ]) }}</span>
                                                                </p>
                                                            @else
                                                                <p class="font-bold italic text-md flex items-center gap-1 text-primary-800">
                                                                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="size-6">
                                                                        <path stroke-linecap="round" stroke-linejoin="round" d="M8.25 18.75a1.5 1.5 0 0 1-3 0m3 0a1.5 1.5 0 0 0-3 0m3 0h6m-9 0H3.375a1.125 1.125 0 0 1-1.125-1.125V14.25m17.25 4.5a1.5 1.5 0 0 1-3 0m3 0a1.5 1.5 0 0 0-3 0m3 0h1.125c.621 0 1.129-.504 1.09-1.124a17.902 17.902 0 0 0-3.213-9.193 2.056 2.056 0 0 0-1.58-.86H14.25M16.5 18.75h-2.25m0-11.177v-.958c0-.568-.422-1.048-.987-1.106a48.554 48.554 0 0 0-10.026 0 1.106 1.106 0 0 0-.987 1.106v7.635m12-6.677v6.677m0 4.5v-4.5m0 0h-12" />
                                                                    </svg>
                                                                    <span>
                                                {{ Translation::get('pre-order-product-now', 'product', 'Pre order nu, levering op :date:', 'text', [
                                                'date' => $item->model->expectedInStockDate()
                                            ]) }}
                                            </span>
                                                                </p>
                                                            @endif
                                                        @endif
                                                    @else
                                                        <p class="font-bold text-red-500 text-md flex items-center gap-2">
                                                            <x-lucide-x-circle class="h-5 w-5"/>
                                                            {{ Translation::get('not-in-stock', 'product', 'Niet op voorraad') }}
                                                        </p>
                                                    @endif
                                                </div>
                                            </div>
                                        </li>
                                    @endforeach
                                </ul>
                                <dl class="space-y-6 border-t border-gray-200 px-4 py-6 sm:px-6 text-white">
                                    <div class="flex items-center justify-between">
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
                                                <dd class="text-sm font-bold">{{Translation::get('not-applicable', 'checkout', 'Niet van toepassing')}}</dd>
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
                                </dl>

                                {{--                                <div class="border-t border-gray-200 px-4 py-6 sm:px-6">--}}
                                {{--                                    <button type="submit"--}}
                                {{--                                            class="button button--dark">--}}
                                {{--                                        Confirm order--}}
                                {{--                                    </button>--}}
                                {{--                                </div>--}}
                            </div>
                        </aside>
                    </main>
                </div>
            </x-container>
        </section>
    @else
        <x-blocks.header :data="[
            'title' => Translation::get('no-items-in-cart', 'cart', 'Geen items in je winkelwagen!'),
            'subtitle' => Translation::get('keep-shopping', 'cart', 'Verder shoppen!'),
            'image' => Translation::get('image', 'cart', '', 'image'),
        ]"></x-blocks.header>
    @endif

    <hr class="border-t border-black/5"/>

</div>
