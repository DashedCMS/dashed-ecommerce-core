<div>
  @if (($progress['gap'] ?? 0) > 0)
    <div class="mb-4 rounded-md p-3 bg-amber-50 border border-amber-200">
      <div class="text-sm text-gray-700">
        {!! str_replace(':amount:', '<strong class="text-green-700">€'.number_format($progress['gap'], 2, ',', '.').'</strong>', \Dashed\DashedTranslations\Models\Translation::get('free-shipping.under', 'cart', 'Nog :amount: voor gratis verzending')) !!}
      </div>
      <div class="mt-2 h-2 rounded-full bg-gray-200 overflow-hidden">
        <div class="h-full bg-green-600 transition-all" style="width: {{ $progress['percentage'] }}%"></div>
      </div>
    </div>
  @endif

  @if ($suggestions->isNotEmpty())
    <div class="mt-6">
      <p class="text-xs uppercase tracking-wide font-semibold text-gray-500 mb-3">
        {{ \Dashed\DashedTranslations\Models\Translation::get(($progress['gap'] ?? 0) > 0 ? 'cart.suggestions.label_under_threshold' : 'cart.suggestions.label', 'cart', ($progress['gap'] ?? 0) > 0 ? 'Maak gratis verzending compleet' : 'Aanbevolen voor jou') }}
      </p>

      <div class="flex gap-3 overflow-x-auto pb-2 -mx-2 px-2">
        @foreach ($suggestions as $product)
          <div class="flex-shrink-0 w-32 sm:w-40 bg-white border border-gray-200 rounded-md p-2 relative" wire:key="suggestion-{{ $product->id }}">
            <div class="aspect-square bg-gray-100 rounded mb-2 relative overflow-hidden">
              @php
                $suggestionImage = $product->firstImage ?? $product->productGroup?->firstImage;
              @endphp
              @if ($suggestionImage)
                <x-dashed-files::image :mediaId="$suggestionImage" :alt="$product->name" class="w-full h-full object-cover" />
              @endif
              @if ($product->is_gap_closer ?? false)
                <span class="absolute top-1 right-1 bg-green-600 text-white text-[10px] font-bold uppercase px-2 py-0.5 rounded-full">
                  {{ \Dashed\DashedTranslations\Models\Translation::get('cart.suggestions.gap_closer_badge', 'cart', 'GRATIS VERZ') }}
                </span>
              @endif
            </div>
            <div class="text-xs text-gray-800 leading-tight mb-1 line-clamp-2">{{ $product->name }}</div>
            <div class="flex items-center justify-between">
              <span class="text-sm font-bold">€{{ number_format((float) $product->current_price, 2, ',', '.') }}</span>
              <button type="button" wire:click="addToCart({{ $product->id }})" wire:loading.attr="disabled" class="bg-black text-white w-6 h-6 rounded-full inline-flex items-center justify-center text-sm leading-none">+</button>
            </div>
          </div>
        @endforeach
      </div>
    </div>
  @endif
</div>
