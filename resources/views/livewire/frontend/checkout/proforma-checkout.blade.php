<div class="proforma-checkout">
    @if (session('error'))
        <div class="proforma-checkout__error" role="alert">
            {{ session('error') }}
        </div>
    @endif

    <h1 class="proforma-checkout__title">{{ __('Proforma bestelling betalen') }}</h1>

    {{-- Vaste regels: read-only, geen aantal/verwijder/korting controls --}}
    <section class="proforma-checkout__lines">
        <table class="proforma-checkout__table">
            <thead>
                <tr>
                    <th>{{ __('Omschrijving') }}</th>
                    <th>{{ __('Aantal') }}</th>
                    <th>{{ __('Prijs') }}</th>
                    <th>{{ __('Btw') }}</th>
                    <th>{{ __('Totaal') }}</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($order->orderProducts as $orderProduct)
                    <tr>
                        <td>{{ $orderProduct->name }}</td>
                        <td>{{ $orderProduct->quantity }}</td>
                        <td>{{ CurrencyHelper::formatPrice($orderProduct->price) }}</td>
                        <td>{{ $orderProduct->vat_rate }}%</td>
                        <td>{{ CurrencyHelper::formatPrice($orderProduct->price * $orderProduct->quantity) }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </section>

    <form wire:submit.prevent="submit" class="proforma-checkout__form">
        <h2>{{ __('Jouw gegevens') }}</h2>

        <div class="proforma-checkout__grid">
            <label>
                {{ __('Voornaam') }}
                <input type="text" wire:model="firstName">
                @error('firstName') <span class="proforma-checkout__field-error">{{ $message }}</span> @enderror
            </label>

            <label>
                {{ __('Achternaam') }}
                <input type="text" wire:model="lastName">
                @error('lastName') <span class="proforma-checkout__field-error">{{ $message }}</span> @enderror
            </label>

            <label>
                {{ __('E-mailadres') }}
                <input type="email" wire:model="email">
                @error('email') <span class="proforma-checkout__field-error">{{ $message }}</span> @enderror
            </label>

            <label>
                {{ __('Telefoonnummer') }}
                <input type="text" wire:model="phoneNumber">
                @error('phoneNumber') <span class="proforma-checkout__field-error">{{ $message }}</span> @enderror
            </label>

            <label>
                {{ __('Bedrijfsnaam') }}
                <input type="text" wire:model="companyName">
                @error('companyName') <span class="proforma-checkout__field-error">{{ $message }}</span> @enderror
            </label>

            <label>
                {{ __('Btw-nummer') }}
                <input type="text" wire:model="btwId">
                @error('btwId') <span class="proforma-checkout__field-error">{{ $message }}</span> @enderror
            </label>

            <label>
                {{ __('Straat') }}
                <input type="text" wire:model="street">
                @error('street') <span class="proforma-checkout__field-error">{{ $message }}</span> @enderror
            </label>

            <label>
                {{ __('Huisnummer') }}
                <input type="text" wire:model="houseNr">
                @error('houseNr') <span class="proforma-checkout__field-error">{{ $message }}</span> @enderror
            </label>

            <label>
                {{ __('Postcode') }}
                <input type="text" wire:model="zipCode">
                @error('zipCode') <span class="proforma-checkout__field-error">{{ $message }}</span> @enderror
            </label>

            <label>
                {{ __('Plaats') }}
                <input type="text" wire:model="city">
                @error('city') <span class="proforma-checkout__field-error">{{ $message }}</span> @enderror
            </label>

            <label>
                {{ __('Land') }}
                <input type="text" wire:model.live="country">
                @error('country') <span class="proforma-checkout__field-error">{{ $message }}</span> @enderror
            </label>
        </div>

        @if ($shippingEnabled)
            <h2>{{ __('Verzending') }}</h2>
            <label>
                {{ __('Verzendmethode') }}
                <select wire:model="shippingMethod">
                    <option value="">{{ __('Kies een verzendmethode') }}</option>
                    @foreach ($shippingMethods as $method)
                        <option value="{{ $method['id'] }}">
                            {{ $method['correctName'] ?? ($method['name'] ?? '') }}
                            @isset($method['costsFormatted']) - {{ $method['costsFormatted'] }} @endisset
                        </option>
                    @endforeach
                </select>
                @error('shippingMethod') <span class="proforma-checkout__field-error">{{ $message }}</span> @enderror
            </label>
        @endif

        <h2>{{ __('Betaalmethode') }}</h2>
        @if (count($paymentProviders))
            <label>
                {{ __('Betaalprovider') }}
                <select wire:model="psp">
                    @foreach ($paymentProviders as $pspId => $pspName)
                        <option value="{{ $pspId }}">{{ $pspName }}</option>
                    @endforeach
                </select>
            </label>
        @else
            <p>{{ __('Er is op dit moment geen betaalprovider beschikbaar.') }}</p>
        @endif

        <button type="submit" wire:loading.attr="disabled" class="proforma-checkout__submit">
            {{ __('Betalen') }}
        </button>
    </form>
</div>
