{{--
    <x-dashed-ecommerce-core::order-add-ons.tracker-button :order="$order"/> in de ordertracker / Mijn bestellingen:
    een knop "Nog iets toevoegen" zolang het aanbod open is (ingang `tracker`); op een gekoppelde bijbestelling
    in plaats daarvan "Gaat mee met bestelling #X".
--}}
@props(['order' => null])

@php($tracker = \Dashed\DashedEcommerceCore\Classes\OrderAddOns\EntryPoints::tracker($order))

@if($tracker && $tracker['type'] === 'add_on')
    <p {{ $attributes->merge(['class' => 'text-sm']) }} data-order-add-on="goes-with">
        {{ str_replace(':order:', $tracker['parentNumber'], Translation::get('tracker-gaat-mee', 'bijbestellen', 'Gaat mee met bestelling #:order: in dezelfde doos.')) }}
    </p>
@elseif($tracker)
    <div {{ $attributes->merge(['class' => 'text-sm']) }} data-order-add-on="tracker">
        <a href="{{ $tracker['url'] }}" class="button">{{ Translation::get('tracker-knop', 'bijbestellen', 'Nog iets toevoegen zonder verzendkosten') }}</a>
        <p class="mt-2 opacity-70">{{ str_replace(':deadline:', $tracker['until'], Translation::get('tracker-uitleg', 'bijbestellen', 'Het gaat mee in dezelfde doos. Dat kan :deadline:.')) }}</p>
    </div>
@endif
