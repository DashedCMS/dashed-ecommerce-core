<div>
  @if ($suggestions->isNotEmpty())
    <div class="mt-3">
      <p class="text-[10px] uppercase tracking-wide font-semibold text-gray-500 mb-2">
        {{ \Dashed\DashedTranslations\Models\Translation::get(($progress['gap'] ?? 0) > 0 ? 'cart.suggestions.label_under_threshold' : 'cart.suggestions.label', 'cart', ($progress['gap'] ?? 0) > 0 ? 'Maak gratis verzending compleet' : 'Aanbevolen voor jou') }}
      </p>

      <div class="flex gap-2 overflow-x-auto pb-1">
        @foreach ($suggestions as $product)
          <div class="flex-shrink-0 w-24 bg-white border border-gray-200 rounded p-2 relative" wire:key="popup-suggestion-{{ $product->id }}">
            <div class="aspect-square bg-gray-100 rounded mb-1 relative overflow-hidden">
              @php
                $suggestionImage = $product->firstImage ?? $product->productGroup?->firstImage;
              @endphp
              @if ($suggestionImage)
                <x-dashed-files::image :mediaId="$suggestionImage" :alt="$product->name" class="w-full h-full object-cover" />
              @endif
              @if ($product->is_gap_closer ?? false)
                <span class="absolute top-0 right-0 bg-green-600 text-white text-[8px] font-bold px-1 rounded-bl">FREE</span>
              @endif
            </div>
            <div class="text-[11px] text-gray-800 leading-tight line-clamp-1">{{ $product->name }}</div>
            <div class="flex items-center justify-between mt-1">
              <span class="text-[11px] font-bold">€{{ number_format((float) $product->current_price, 2, ',', '.') }}</span>
              <button type="button" wire:click="addToCart({{ $product->id }})" wire:loading.attr="disabled" class="bg-black text-white w-5 h-5 rounded-full inline-flex items-center justify-center text-xs leading-none">+</button>
            </div>
          </div>
        @endforeach
      </div>
    </div>
  @endif
</div>
