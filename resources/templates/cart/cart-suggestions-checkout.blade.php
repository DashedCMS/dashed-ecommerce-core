<div>
  @if ($suggestions->isNotEmpty())
    <div class="my-4 p-3 rounded-md {{ ($progress['gap'] ?? 0) > 0 ? 'bg-green-50 border border-green-200' : 'bg-gray-50 border border-gray-200' }}">
      <p class="text-xs uppercase tracking-wide font-semibold text-gray-500 mb-2">
        @if (($progress['gap'] ?? 0) > 0)
          {!! str_replace(':amount:', '<strong class="text-green-700">€'.number_format($progress['gap'], 2, ',', '.').'</strong>', \Dashed\DashedTranslations\Models\Translation::get('free-shipping.under', 'cart', 'Nog :amount: voor gratis verzending')) !!}
        @else
          {{ \Dashed\DashedTranslations\Models\Translation::get('cart.suggestions.label_above_threshold', 'cart', 'Vergeet je niets?') }}
        @endif
      </p>

      <div class="flex gap-2 overflow-x-auto pb-1">
        @foreach ($suggestions as $product)
          <div class="flex-shrink-0 w-24 sm:w-28 bg-white border border-gray-200 rounded p-2 relative" wire:key="checkout-suggestion-{{ $product->id }}">
            <div class="aspect-square bg-gray-100 rounded mb-1 relative overflow-hidden">
              @php
                $suggestionImage = $product->firstImage ?? $product->productGroup?->firstImage;
              @endphp
              @if ($suggestionImage)
                <x-dashed-files::image :mediaId="$suggestionImage" :alt="$product->name" class="w-full h-full object-cover" />
              @endif
              @if ($product->is_gap_closer ?? false)
                <span class="absolute top-0.5 right-0.5 bg-green-600 text-white text-[9px] font-bold px-1.5 py-0.5 rounded-full">FREE</span>
              @endif
            </div>
            <div class="text-[11px] text-gray-800 leading-tight mb-1 line-clamp-1">{{ $product->name }}</div>
            <div class="flex items-center justify-between">
              <span class="text-xs font-bold">€{{ number_format((float) $product->current_price, 2, ',', '.') }}</span>
              <button type="button" wire:click="addToCart({{ $product->id }})" wire:loading.attr="disabled" class="bg-black text-white w-5 h-5 rounded-full inline-flex items-center justify-center text-xs leading-none">+</button>
            </div>
          </div>
        @endforeach
      </div>
    </div>
  @endif
</div>
