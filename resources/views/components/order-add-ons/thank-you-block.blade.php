{{--
    <x-dashed-ecommerce-core::order-add-ons.thank-you-block :order="$order"/> op de bedankpagina:
    drie voorstellen die zonder verzendkosten in dezelfde doos meegaan, met een knop naar de ondertekende
    bijbestelpagina. Toont niets als het aanbod dicht is, de ingang `thank_you` uitstaat of er niets past.
--}}
@props(['order' => null])

@php($offer = \Dashed\DashedEcommerceCore\Classes\OrderAddOns\EntryPoints::thankYou($order))

@if($offer)
    <div {{ $attributes->merge(['class' => 'mt-6 rounded-xl border p-4 md:p-6']) }} data-order-add-on="thank-you">
        <h2 class="mb-2 text-lg font-bold">{{ Translation::get('bedank-titel', 'bijbestellen', 'Nog iets vergeten? Voeg het toe zonder verzendkosten') }}</h2>
        <p class="text-sm opacity-70">
            {{ str_replace(':deadline:', $offer['until'], Translation::get('bedank-uitleg-tot', 'bijbestellen', 'Het gaat mee in dezelfde doos. Dat kan :deadline:.')) }}
        </p>

        <div class="mt-4 grid gap-2">
            @foreach($offer['suggestions'] as $suggestion)
                @php($image = $suggestion->firstImage ?? $suggestion->productGroup?->firstImage)
                <a href="{{ $offer['url'] }}" class="flex items-center gap-3 rounded-lg border p-2">
                    <span class="block shrink-0 overflow-hidden rounded" style="width: 44px; height: 44px;">
                        @if($image)
                            <x-dashed-files::image class="h-full w-full object-cover" config="dashed" :mediaId="$image" :alt="$suggestion->name" :manipulations="['fit' => [120, 120]]"/>
                        @endif
                    </span>
                    <span class="min-w-0 flex-1">
                        <span class="block truncate text-sm font-bold">{{ $suggestion->name }}</span>
                        <span class="block text-sm font-semibold">{{ CurrencyHelper::formatPrice($suggestion->current_price) }}</span>
                    </span>
                </a>
            @endforeach
        </div>

        <div class="mt-4">
            <a href="{{ $offer['url'] }}" class="button button--primary">{{ Translation::get('bedank-knop', 'bijbestellen', 'Bekijk wat er nog bij kan') }}</a>
        </div>
    </div>
@endif
