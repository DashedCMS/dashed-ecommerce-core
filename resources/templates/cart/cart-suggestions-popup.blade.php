<div>
  @if (($progress['gap'] ?? 0) > 0 || ($progress['reached'] ?? false))
    <div class="mt-3 p-3 rounded-md bg-green-50 border border-green-200">
      <div class="text-xs text-gray-700 mb-2">
        @if (($progress['reached'] ?? false))
          <strong class="text-green-700">{{ \Dashed\DashedTranslations\Models\Translation::get('free-shipping.reached', 'cart', 'Je hebt gratis verzending!') }}</strong>
        @else
          {!! str_replace(':amount:', '<strong class="text-green-700">€'.number_format($progress['gap'], 2, ',', '.').'</strong>', \Dashed\DashedTranslations\Models\Translation::get('free-shipping.under', 'cart', 'Nog :amount: voor gratis verzending')) !!}
        @endif
      </div>
      <div class="h-1.5 rounded-full bg-gray-200 overflow-hidden mb-3">
        <div class="h-full bg-green-600 transition-all" style="width: {{ $progress['percentage'] }}%"></div>
      </div>

      @if ($suggestions->isNotEmpty())
        <div class="flex gap-2 overflow-x-auto pb-1">
          @foreach ($suggestions as $product)
            <div class="flex-shrink-0 w-20 bg-white border border-gray-200 rounded p-1.5 relative" wire:key="popup-suggestion-{{ $product->id }}">
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
              <div class="text-[10px] text-gray-800 leading-tight line-clamp-1">{{ $product->name }}</div>
              <div class="flex items-center justify-between mt-1">
                <span class="text-[11px] font-bold">€{{ number_format((float) $product->current_price, 2, ',', '.') }}</span>
                <button type="button" wire:click="addToCart({{ $product->id }})" wire:loading.attr="disabled" class="bg-black text-white w-4 h-4 rounded-full inline-flex items-center justify-center text-[10px] leading-none">+</button>
              </div>
            </div>
          @endforeach
        </div>
      @endif
    </div>
  @endif
</div>
