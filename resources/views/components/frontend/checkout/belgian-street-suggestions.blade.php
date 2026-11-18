{{--
    Belgische straatsuggesties rond een straatveld in de checkout:

    <x-dashed-ecommerce-core::frontend.checkout.belgian-street-suggestions zip="zipCode" street="street" city="city" country="country">
        <x-fields.input model="street" id="street" ... />
    </x-dashed-ecommerce-core::frontend.checkout.belgian-street-suggestions>

    zip/street/city/country zijn de element-id's van de velden; `country-fallback` voor het
    factuuradres (leeg factuurland = bezorgland). Bij een Belgische postcode zoekt hij
    terwijl de klant typt (/ecommerce/address/belgian-streets); een keuze vult straat en
    plaats. Staat de instelling uit, dan geeft hij alleen het veld terug.
--}}
@props(['zip' => 'zipCode', 'street' => 'street', 'city' => 'city', 'country' => 'country', 'countryFallback' => null])
@php
    $active = \Dashed\DashedEcommerceCore\Services\Address\BelgianAddresses::active();
    $belgianNames = $active
        ? collect(\Dashed\DashedEcommerceCore\Classes\Countries::getAllSelectedCountries())
            ->filter(fn ($name) => \Dashed\DashedEcommerceCore\Services\Address\BelgianAddresses::isBelgium($name))
            ->push('BE')->values()->all()
        : [];
@endphp
@if(! $active)
    {{ $slot }}
@else
    @once
        <script>
            window.dashedBelgianStreets = window.dashedBelgianStreets || function (cfg) {
                return {
                    items: [],
                    active: -1,
                    last: '',
                    el(id) { return id ? document.getElementById(id) : null; },
                    value(id) { const e = this.el(id); return e ? String(e.value || '').trim() : ''; },
                    isBelgium() {
                        const country = this.value(cfg.country) || this.value(cfg.countryFallback);
                        return cfg.belgium.includes(country);
                    },
                    init() {
                        const street = this.el(cfg.street);
                        if (! street) return;
                        street.setAttribute('role', 'combobox');
                        street.setAttribute('aria-autocomplete', 'list');
                        street.setAttribute('aria-expanded', 'false');
                        street.setAttribute('aria-controls', cfg.street + '-belgian-streets');
                        this.$watch('items', (list) => street.setAttribute('aria-expanded', list.length ? 'true' : 'false'));
                        this.$watch('active', (i) => i >= 0
                            ? street.setAttribute('aria-activedescendant', cfg.street + '-belgian-street-' + i)
                            : street.removeAttribute('aria-activedescendant'));
                    },
                    async search(event) {
                        if (event.target.id !== cfg.street || this.filling) return;
                        const postcode = this.value(cfg.zip).replace(/\s+/g, '');
                        const q = this.value(cfg.street);
                        if (! this.isBelgium() || ! /^[1-9]\d{3}$/.test(postcode) || q.length < 2) { this.close(); return; }
                        const key = postcode + '|' + q.toLowerCase();
                        if (key === this.last) return;
                        this.last = key;
                        try {
                            const response = await fetch(cfg.url + '?' + new URLSearchParams({ postcode, q, locale: cfg.locale }), { headers: { Accept: 'application/json' } });
                            const data = response.ok ? await response.json() : null;
                            if (key !== this.last) return;
                            const list = (data && data.streets) || [];
                            this.items = list.length === 1 && list[0].street.toLowerCase() === q.toLowerCase() ? [] : list;
                            this.active = -1;
                        } catch (e) {
                            this.close();
                        }
                    },
                    set(id, value) {
                        const e = this.el(id);
                        if (! e) return;
                        e.value = value;
                        e.dispatchEvent(new Event('input', { bubbles: true }));
                        e.dispatchEvent(new Event('change', { bubbles: true }));
                    },
                    choose(i) {
                        const item = this.items[i];
                        if (! item) return;
                        this.filling = true;
                        this.set(cfg.street, item.street);
                        this.set(cfg.city, item.city);
                        this.filling = false;
                        this.last = this.value(cfg.zip).replace(/\s+/g, '') + '|' + item.street.toLowerCase();
                        this.close();
                    },
                    close() { this.items = []; this.active = -1; },
                    key(event) {
                        if (event.target.id !== cfg.street || ! this.items.length) return;
                        if (event.key === 'ArrowDown') { event.preventDefault(); this.active = (this.active + 1) % this.items.length; }
                        else if (event.key === 'ArrowUp') { event.preventDefault(); this.active = this.active <= 0 ? this.items.length - 1 : this.active - 1; }
                        else if (event.key === 'Enter' && this.active >= 0) { event.preventDefault(); this.choose(this.active); }
                        else if (event.key === 'Escape') { this.close(); }
                    },
                };
            };
        </script>
    @endonce
    <div {{ $attributes->merge(['class' => 'relative']) }}
         x-data="dashedBelgianStreets(@js(['zip' => $zip, 'street' => $street, 'city' => $city, 'country' => $country, 'countryFallback' => $countryFallback, 'belgium' => $belgianNames, 'url' => route('dashed.frontend.address.belgian-streets'), 'locale' => app()->getLocale()]))"
         x-on:input.debounce.300ms="search($event)"
         x-on:keydown="key($event)"
         x-on:focusout="if (! $el.contains($event.relatedTarget)) close()">
        {{ $slot }}
        <ul x-show="items.length" x-cloak id="{{ $street }}-belgian-streets" role="listbox"
            aria-label="{{ \Dashed\DashedTranslations\Models\Translation::get('belgian-street-suggestions', 'checkout', 'Straten bij deze postcode') }}"
            class="absolute left-0 top-full z-20 mt-1 max-h-72 w-full overflow-y-auto rounded-md border border-black/10 bg-white py-1 text-sm shadow-lg">
            <template x-for="(item, i) in items" :key="item.street + '|' + item.city">
                <li role="option" :id="@js($street) + '-belgian-street-' + i" :aria-selected="active === i"
                    class="flex cursor-pointer items-baseline justify-between gap-3 px-3 py-2"
                    x-bind:class="active === i ? 'bg-black/5' : 'hover:bg-black/5'"
                    @mousedown.prevent="choose(i)" @mouseenter="active = i">
                    <span class="truncate" x-text="item.street"></span>
                    <span class="shrink-0 text-xs opacity-60" x-text="item.city"></span>
                </li>
            </template>
        </ul>
    </div>
@endif
