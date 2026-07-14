<div>
    <section class="py-[clamp(24px,4vw,56px)]">
        <x-container>
            {{-- Step bar --}}
            <div class="mb-8 flex flex-wrap items-center gap-x-3 gap-y-2 text-[13px]">
                @foreach ([Translation::get('step-details', 'checkout', 'Gegevens'), Translation::get('step-shipping', 'checkout', 'Verzending'), Translation::get('step-payment', 'checkout', 'Betalen')] as $i => $step)
                    <span class="flex items-center gap-2 text-black">
                        <span class="grid size-5 place-items-center rounded-full bg-primary text-[11px] font-bold text-white">{{ $i + 1 }}</span>
                        <span class="font-semibold">{{ $step }}</span>
                    </span>
                    @if (! $loop->last)<span class="text-[rgba(48,84,91,0.3)]">·</span>@endif
                @endforeach
            </div>

            @if (session('error'))
                <div class="mb-6 rounded-[14px] bg-red-50 px-4 py-3 text-[14px] text-red-600" role="alert">
                    {{ session('error') }}
                </div>
            @endif

            <form wire:submit="submit">
                <div class="grid items-start gap-[clamp(20px,3vw,40px)] lg:[grid-template-columns:minmax(0,1fr)_430px]">

                    {{-- LEFT: adres- en betaalformulier --}}
                    <div class="order-1 flex min-w-0 flex-col gap-10 lg:order-none lg:col-start-1 lg:row-start-1">

                        {{-- 1. Contactgegevens --}}
                        <div>
                            <div class="mb-5 flex items-baseline justify-between gap-4">
                                <h2 class="flex items-center gap-2 font-display text-[clamp(22px,2vw,28px)] text-black"><span class="text-primary">1</span> {{ Translation::get('contact-information', 'checkout', 'Contactgegevens') }}</h2>
                                @if (Auth::guest() && $accountRequired == 2)
                                    <span class="text-[13px] text-[rgba(48,84,91,0.7)]">{{ Translation::get('already-have-account', 'checkout', 'Al klant?') }} <a href="{{ AccountHelper::getAccountUrl() }}" class="font-semibold text-primary hover:underline">{{ Translation::get('login', 'checkout', 'Inloggen') }}</a></span>
                                @endif
                            </div>
                            <div class="grid gap-3">
                                <x-fields.input required type="email" model="email" id="email" placeholder="{{ Translation::get('email', 'checkout', 'E-mailadres') }}"/>
                                @if (Auth::guest() && $accountRequired == 2)
                                    <div class="grid gap-3 sm:grid-cols-2">
                                        <x-fields.input :required="$accountRequired == 1" type="password" model="password" id="password" placeholder="{{ Translation::get('password', 'checkout', 'Wachtwoord') }}"/>
                                        <x-fields.input :required="$accountRequired == 1" type="password" model="passwordConfirmation" id="passwordConfirmation" placeholder="{{ Translation::get('password-repeat', 'checkout', 'Wachtwoord herhalen') }}"/>
                                    </div>
                                @endif
                                <x-fields.checkbox model="marketing" id="marketing" :label="Translation::get('accept-marketing-text', 'checkout', 'Schrijf mij in voor de nieuwsbrief met nieuwe drops en acties')"/>
                            </div>
                        </div>

                        {{-- 2. Bezorgadres --}}
                        <div>
                            <h2 class="mb-5 flex items-center gap-2 font-display text-[clamp(22px,2vw,28px)] text-black"><span class="text-primary">2</span> {{ Translation::get('delivery-address', 'checkout', 'Bezorgadres') }}</h2>
                            <div class="grid gap-3">
                                <div class="grid gap-3 sm:grid-cols-2">
                                    <x-fields.input :required="$firstAndLastnameRequired == 1" type="text" model="firstName" id="firstName" placeholder="{{ Translation::get('first-name', 'checkout', 'Voornaam') }}"/>
                                    <x-fields.input required type="text" model="lastName" id="lastName" placeholder="{{ Translation::get('last-name', 'checkout', 'Achternaam') }}"/>
                                </div>
                                @if ($companyRequired == 2)
                                    <x-fields.checkbox model="isCompany" id="isCompany" :label="Translation::get('order-as-company', 'checkout', 'Bestellen als bedrijf')"/>
                                @endif
                                @if ($isCompany || $companyRequired == 1)
                                    <div class="grid gap-3 sm:grid-cols-2">
                                        <x-fields.input :required="$companyRequired == 1" type="text" model="company" id="company" placeholder="{{ Translation::get('company-name', 'checkout', 'Bedrijfsnaam') }}"/>
                                        <x-fields.input type="text" model="taxId" id="taxId" placeholder="{{ Translation::get('tax-id', 'checkout', 'BTW ID') }}"/>
                                    </div>
                                @endif
                                <div class="grid gap-3 sm:grid-cols-2">
                                    <x-fields.input required type="text" model="zipCode" id="zipCode" placeholder="{{ Translation::get('zip-code', 'checkout', 'Postcode') }}"/>
                                    <x-fields.input required type="text" model="houseNr" id="houseNr" placeholder="{{ Translation::get('house-number', 'checkout', 'Huisnummer') }}"/>
                                </div>
                                <div class="grid gap-3 sm:grid-cols-2">
                                    <x-fields.input required type="text" model="street" id="street" placeholder="{{ Translation::get('street', 'checkout', 'Straat') }}"/>
                                    <x-fields.input required type="text" model="city" id="city" placeholder="{{ Translation::get('city', 'checkout', 'Plaats') }}"/>
                                </div>
                                <x-fields.select required model="country" id="country" placeholder="{{ Translation::get('country', 'checkout', 'Land') }}">
                                    @foreach ($countryList as $countryOption)
                                        <option value="{{ $countryOption }}">{{ $countryOption }}</option>
                                    @endforeach
                                </x-fields.select>
                                @if ($phoneNumberRequired != 0)
                                    <x-fields.input :required="$phoneNumberRequired == 1" type="text" model="phoneNumber" id="phone_number" placeholder="{{ Translation::get('phone-number', 'checkout', 'Telefoonnummer') }}"/>
                                @endif
                                <x-fields.checkbox model="invoiceAddress" id="invoiceAddress" :label="Translation::get('seperate-invoice-address', 'checkout', 'Afwijkend factuuradres')"/>
                                @if ($invoiceAddress)
                                    <div class="mt-2 grid gap-3 rounded-[16px] border border-[rgba(48,84,91,0.1)] bg-white p-4">
                                        <p class="text-[13px] font-bold uppercase tracking-[0.1em] text-[rgba(48,84,91,0.55)]">{{ Translation::get('invoice-address', 'checkout', 'Factuuradres') }}</p>
                                        <div class="grid gap-3 sm:grid-cols-2">
                                            <x-fields.input required type="text" model="invoiceZipCode" id="invoiceZipCode" placeholder="{{ Translation::get('zip-code', 'checkout', 'Postcode') }}"/>
                                            <x-fields.input required type="text" model="invoiceHouseNr" id="invoiceHouseNr" placeholder="{{ Translation::get('house-number', 'checkout', 'Huisnummer') }}"/>
                                        </div>
                                        <div class="grid gap-3 sm:grid-cols-2">
                                            <x-fields.input required type="text" model="invoiceStreet" id="invoiceStreet" placeholder="{{ Translation::get('street', 'checkout', 'Straat') }}"/>
                                            <x-fields.input required type="text" model="invoiceCity" id="invoiceCity" placeholder="{{ Translation::get('city', 'checkout', 'Plaats') }}"/>
                                        </div>
                                        <x-fields.input required type="text" model="invoiceCountry" id="invoiceCountry" placeholder="{{ Translation::get('country', 'checkout', 'Land') }}"/>
                                    </div>
                                @endif
                            </div>
                        </div>

                        {{-- 3. Verzendmethode (alleen als de proforma verzending toestaat) --}}
                        @if ($shippingEnabled)
                            <div>
                                <h2 class="mb-5 flex items-center gap-2 font-display text-[clamp(22px,2vw,28px)] text-black">
                                    <span class="text-primary">3</span> {{ Translation::get('shipping-method', 'checkout', 'Verzendmethode') }}
                                </h2>
                                @if (count($shippingMethods))
                                    <div class="grid gap-3">
                                        @foreach ($shippingMethods as $thisShippingMethod)
                                            <label class="flex cursor-pointer items-center gap-3 rounded-[14px] border border-[rgba(48,84,91,0.12)] bg-white px-5 py-4 transition has-[:checked]:border-primary has-[:checked]:bg-secondary/20">
                                                <input type="radio" name="shipping_method" class="peer sr-only"
                                                       value="{{ $thisShippingMethod['id'] }}"
                                                       wire:model.live="shippingMethod">
                                                <span class="grid size-5 shrink-0 place-items-center rounded-full border-2 border-[rgba(48,84,91,0.3)] peer-checked:border-primary">
                                                    <span class="size-2.5 rounded-full bg-primary opacity-0 peer-checked:opacity-100"></span>
                                                </span>
                                                <span class="min-w-0 flex-1">
                                                    <span class="block font-semibold text-black">{{ $thisShippingMethod['correctName'] ?? ($thisShippingMethod['name'] ?? '') }}</span>
                                                    @if ($thisShippingMethod['description'] ?? null)
                                                        <span class="block text-[13px] text-[rgba(48,84,91,0.6)]">{{ $thisShippingMethod['description'] }}</span>
                                                    @endif
                                                </span>
                                                <span class="shrink-0 text-[14px] font-semibold {{ ($thisShippingMethod['costs'] ?? 0) > 0 ? 'text-black' : 'text-primary' }}">
                                                    {{ ($thisShippingMethod['costs'] ?? 0) > 0 ? CurrencyHelper::formatPrice($thisShippingMethod['costs']) : Translation::get('free', 'checkout', 'Gratis') }}
                                                </span>
                                            </label>
                                        @endforeach
                                    </div>
                                    @error('shippingMethod') <p class="mt-2 text-[13px] text-red-600">{{ $message }}</p> @enderror
                                @else
                                    <p class="rounded-[14px] bg-red-50 px-4 py-3 text-[14px] text-red-600">{{ Translation::get('no-shipping-methods-available', 'checkout', 'Er zijn geen verzendmethodes beschikbaar, vul een land in.') }}</p>
                                @endif
                            </div>
                        @endif

                        {{-- 4. Betaalmethode (echte DB-betaalmethodes met logo's + kosten) --}}
                        <div>
                            <h2 class="mb-5 flex items-center gap-2 font-display text-[clamp(22px,2vw,28px)] text-black">
                                <span class="text-primary">{{ $shippingEnabled ? 4 : 3 }}</span> {{ Translation::get('payment-method', 'checkout', 'Betaalmethode') }}
                            </h2>
                            @if (count($paymentMethods))
                                <div class="grid gap-3 sm:grid-cols-2">
                                    @foreach ($paymentMethods as $thisPaymentMethod)
                                        <label class="flex h-full cursor-pointer items-center gap-3 rounded-[14px] border border-[rgba(48,84,91,0.12)] bg-white px-4 py-3 transition has-[:checked]:border-primary has-[:checked]:bg-secondary/20">
                                            <input type="radio" name="payment_method" class="peer sr-only"
                                                   value="{{ $thisPaymentMethod['id'] }}"
                                                   wire:model.live="paymentMethod">
                                            <span class="grid size-5 shrink-0 place-items-center rounded-full border-2 border-[rgba(48,84,91,0.3)] peer-checked:border-primary">
                                                <span class="size-2.5 rounded-full bg-primary opacity-0 peer-checked:opacity-100"></span>
                                            </span>
                                            @if ($thisPaymentMethod['image'])
                                                <img src="{{ mediaHelper()->getSingleMedia($thisPaymentMethod['image'])->url ?? '' }}" alt="{{ $thisPaymentMethod['name'] }}" class="h-6 w-auto">
                                            @endif
                                            <span class="min-w-0 flex-1 truncate font-semibold text-black">{{ $thisPaymentMethod['name'] }}@if ($thisPaymentMethod['extra_costs'] > 0)<span class="font-normal text-[rgba(48,84,91,0.6)]"> (+{{ CurrencyHelper::formatPrice($thisPaymentMethod['extra_costs']) }})</span>@endif</span>
                                        </label>
                                    @endforeach
                                </div>
                                @error('paymentMethod') <p class="mt-2 text-[13px] text-red-600">{{ $message }}</p> @enderror
                            @else
                                <p class="rounded-[14px] bg-red-50 px-4 py-3 text-[14px] text-red-600">{{ Translation::get('no-payment-methods-available', 'checkout', 'Er zijn geen betaalmethodes beschikbaar, neem contact met ons op.') }}</p>
                            @endif
                        </div>

                        {{-- 5. Notitie --}}
                        <div>
                            <h2 class="mb-5 flex items-center gap-2 font-display text-[clamp(22px,2vw,28px)] text-black">
                                <span class="text-primary">{{ $shippingEnabled ? 5 : 4 }}</span> {{ Translation::get('note', 'checkout', 'Notitie') }}
                            </h2>
                            <x-fields.textarea :placeholder="Translation::get('leave-a-note', 'checkout', 'Laat een notitie achter bij je bestelling (optioneel)')" model="note" rows="3" id="note"/>
                        </div>
                    </div>

                    {{-- RIGHT: bestellingoverzicht (read-only; geen kortingscode, geen qty-controls) --}}
                    <aside class="order-2 rounded-[24px] border border-[rgba(48,84,91,0.08)] bg-white p-[clamp(20px,2.5vw,32px)] lg:order-none lg:col-start-2 lg:row-start-1 lg:sticky lg:top-28">
                        <h2 class="font-display text-[clamp(22px,2vw,28px)] text-black">{{ Translation::get('your-order', 'cart', 'Je bestelling') }}</h2>

                        <div class="mt-4 space-y-3">
                            @foreach ($order->orderProducts as $orderProduct)
                                <div class="flex items-start justify-between gap-3 text-[14px]">
                                    <span class="flex-1 text-black">
                                        {{ $orderProduct->name }}
                                        @if ($orderProduct->quantity > 1)
                                            <span class="text-[rgba(48,84,91,0.55)]">&times;{{ $orderProduct->quantity }}</span>
                                        @endif
                                    </span>
                                    <span class="shrink-0 font-semibold text-black">{{ CurrencyHelper::formatPrice($orderProduct->price) }}</span>
                                </div>
                            @endforeach
                        </div>

                        <dl class="mt-6 space-y-3 border-t border-[rgba(48,84,91,0.1)] pt-6 text-[14px]">
                            <div class="flex items-center justify-between">
                                <dt class="text-[rgba(48,84,91,0.7)]">{{ Translation::get('subtotal', 'cart', 'Subtotaal') }}</dt>
                                <dd class="font-semibold text-black">{{ CurrencyHelper::formatPrice($order->subtotal + $order->discount) }}</dd>
                            </div>
                            @if ($order->discount > 0)
                                <div class="flex items-center justify-between">
                                    <dt class="text-[rgba(48,84,91,0.7)]">{{ Translation::get('discount', 'cart', 'Korting') }}</dt>
                                    <dd class="font-semibold text-black">- {{ CurrencyHelper::formatPrice($order->discount) }}</dd>
                                </div>
                            @endif
                            <div class="flex items-center justify-between">
                                <dt class="text-[rgba(48,84,91,0.7)]">{{ Translation::get('vat', 'cart', 'Btw') }}</dt>
                                <dd class="font-semibold text-black">{{ CurrencyHelper::formatPrice($order->btw) }}</dd>
                            </div>
                        </dl>

                        <div class="mt-6 flex items-end justify-between border-t border-[rgba(48,84,91,0.1)] pt-6">
                            <span class="font-display text-[20px] text-black">{{ Translation::get('total', 'cart', 'Totaal') }}</span>
                            <span class="text-right">
                                <span class="block text-[clamp(22px,2.2vw,28px)] font-bold leading-none text-black">{{ CurrencyHelper::formatPrice($order->total) }}</span>
                                <span class="text-[12px] text-[rgba(48,84,91,0.55)]">{{ Translation::get('incl-vat', 'cart', 'Incl. btw') }}</span>
                            </span>
                        </div>

                        <div class="mt-6">
                            <x-fields.checkbox required model="generalCondition" id="generalCondition" labelClass="checkout-content !font-normal !text-[rgba(48,84,91,0.85)]" :label='Translation::get("accept-general-conditions", "checkout", "Ik ga akkoord met de <a href=\"/algemene-voorwaarden\" class=\"font-semibold text-primary underline\">algemene voorwaarden</a> en het <a href=\"/privacy-beleid\" class=\"font-semibold text-primary underline\">privacybeleid</a>", "editor")'/>
                        </div>

                        <button type="submit" wire:target="submit" wire:loading.attr="disabled"
                                class="mt-5 flex w-full items-center justify-center gap-2 rounded-full bg-black px-8 py-4 font-semibold text-white transition hover:bg-primary disabled:opacity-70">
                            <span wire:loading.remove wire:target="submit">{{ Translation::get('pay-now', 'cart', 'Betalen') }}</span>
                            <span wire:loading wire:target="submit">{{ Translation::get('payment-will-start-soon', 'cart', 'Betaling wordt opgestart') }}</span>
                            <x-lucide-arrow-right class="size-5" wire:loading.remove wire:target="submit"/>
                        </button>

                        <p class="mt-3 flex items-center justify-center gap-1.5 text-[12px] text-[rgba(48,84,91,0.55)]">
                            <x-lucide-lock class="size-3.5"/> {{ Translation::get('secure-ssl-payment', 'checkout', 'Veilig betalen met SSL-versleuteling') }}
                        </p>
                    </aside>
                </div>
            </form>
        </x-container>
    </section>

    {{-- Fullscreen loading overlay tijdens betaalstart --}}
    <div wire:loading.flex wire:target="submit" class="fixed inset-0 z-[9999] hidden items-center justify-center bg-black/50 backdrop-blur-sm">
        <div class="mx-4 flex max-w-sm flex-col items-center gap-4 rounded-2xl bg-white px-8 py-6 shadow-2xl">
            <svg class="h-10 w-10 animate-spin text-primary" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v4a4 4 0 00-4 4H4z"></path>
            </svg>
            <div class="text-center">
                <p class="text-lg font-bold text-primary">{{ Translation::get('processing-order', 'checkout', 'Betaling wordt gestart') }}</p>
                <p class="mt-1 text-sm text-black/70">{{ Translation::get('please-wait-processing-order', 'checkout', 'Een moment geduld, starten je betaling...') }}</p>
            </div>
        </div>
    </div>
</div>
