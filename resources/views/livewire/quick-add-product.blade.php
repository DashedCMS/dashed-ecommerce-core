@php
    $group = $heroProduct?->productGroup;
    $heroImage = $heroProduct?->firstImage ?? $group?->firstImage;
    $heroName = $heroProduct?->name ?? $group?->name;
    $heroPrice = $heroProduct?->currentPrice;
    $heroDiscount = $heroProduct?->discountPrice;
@endphp

<div>
    <div class="flex gap-4 mb-4 items-start">
        @if ($heroImage)
            <div class="w-24 h-24 bg-gray-100 rounded overflow-hidden flex-shrink-0">
                <x-dashed-files::image :mediaId="$heroImage" :alt="$heroName" class="w-full h-full object-cover" />
            </div>
        @endif
        <div class="flex-1 min-w-0">
            <h3 class="font-bold text-gray-900 mb-1 leading-tight">{{ $heroName }}</h3>
            @if ($heroPrice)
                <p class="text-sm text-gray-700">
                    @if ($heroDiscount)
                        <span class="line-through text-red-500 mr-2">{{ \Dashed\DashedEcommerceCore\Classes\CurrencyHelper::formatPrice($heroDiscount) }}</span>
                    @endif
                    <span class="font-semibold">{{ \Dashed\DashedEcommerceCore\Classes\CurrencyHelper::formatPrice($heroPrice) }}</span>
                </p>
            @endif
            @if ($group)
                <a href="{{ $group->getUrl() }}" class="text-xs text-gray-500 underline mt-1 inline-block">
                    {{ \Dashed\DashedTranslations\Models\Translation::get('cart.suggestions.go_to_product', 'cart', 'Naar productpagina') }}
                </a>
            @endif
        </div>
    </div>

    <div class="border-t border-gray-200 pt-4">
        <x-cart.add-to-cart
            :product="$product"
            :filters="$filters"
            :productExtras="$productExtras"
            :extras="$extras"
            :quantity="$quantity"
            :volumeDiscounts="$volumeDiscounts"
            :price="$price"
            :discountPrice="$discountPrice"
        />
    </div>
</div>
