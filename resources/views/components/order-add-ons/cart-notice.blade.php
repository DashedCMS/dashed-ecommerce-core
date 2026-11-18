{{--
    <x-dashed-ecommerce-core::order-add-ons.cart-notice/> in wagen en checkout (in de checkout met :locked="true"):
    "Gaat mee met bestelling #X, geen verzendkosten", of waarom dat (nog) niet geldt. Toont niets buiten de
    bijbestel-modus. Is het aanbod intussen dicht, dan één keer melden en stopt de modus.
--}}
@props(['locked' => false])

@php($notice = \Dashed\DashedEcommerceCore\Classes\OrderAddOns\EntryPoints::cartNotice())

@if($notice && $notice['state'] === 'active')
    <div {{ $attributes->merge(['class' => 'rounded-lg border p-4 text-sm']) }} data-order-add-on="active">
        <strong>{{ str_replace(':order:', $notice['orderNumber'], Translation::get('modus-actief', 'bijbestellen', 'Gaat mee met bestelling #:order:, geen verzendkosten.')) }}</strong>
        {{ str_replace(':deadline:', $notice['until'], Translation::get('modus-tot-moment', 'bijbestellen', 'Reken af :deadline:.')) }}
        {{ str_replace(':address:', $notice['address'], Translation::get('modus-adres-naar', 'bijbestellen', 'We sturen het naar :address:.')) }}
        @if($locked)
            {{ Translation::get('modus-adres', 'bijbestellen', 'We sturen het naar het adres van die bestelling, daarom staan je gegevens vast.') }}
        @endif
    </div>
    @if($locked)
        <script>
            (function () {
                var locked = @json(array_keys(\Dashed\DashedEcommerceCore\Classes\OrderAddOns\CheckoutMode::FIXED_FIELDS));
                var apply = function () {
                    document.querySelectorAll('input, select, textarea').forEach(function (el) {
                        var field = null;
                        for (var i = 0; i < el.attributes.length; i++) {
                            if (el.attributes[i].name.indexOf('wire:model') === 0) { field = el.attributes[i].value; }
                        }
                        if (locked.indexOf(field) === -1) { return; }
                        if (el.tagName === 'SELECT') { el.style.pointerEvents = 'none'; el.setAttribute('tabindex', '-1'); } else { el.readOnly = true; }
                        el.style.opacity = '0.7';
                    });
                };
                apply();
                document.addEventListener('livewire:initialized', function () { apply(); window.Livewire && Livewire.hook('morph.updated', apply); });
            })();
        </script>
    @endif
@elseif($notice && $notice['state'] === 'excluded')
    <div {{ $attributes->merge(['class' => 'rounded-lg border p-4 text-sm']) }} data-order-add-on="excluded">
        {{ str_replace([':product:', ':order:'], [$notice['product'], $notice['orderNumber']], Translation::get('modus-past-niet', 'bijbestellen', ':product: past niet in de doos van bestelling #:order: of is later klaar. Haal het uit je winkelwagen om zonder verzendkosten bij te bestellen, of bestel alles samen met normale verzending.')) }}
    </div>
@elseif($notice && $notice['state'] === 'closed')
    <div {{ $attributes->merge(['class' => 'rounded-lg border p-4 text-sm']) }} data-order-add-on="closed">
        {{ Translation::get('modus-gesloten', 'bijbestellen', 'Je eerdere bestelling is al ingepakt of de tijd is voorbij: er kan niets meer bij in die doos. Je kunt gewoon bestellen, we versturen dit dan apart.') }}
    </div>
@endif
