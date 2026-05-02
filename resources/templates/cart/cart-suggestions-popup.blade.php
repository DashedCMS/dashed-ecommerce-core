<div>
  @if ($suggestions->isNotEmpty())
    <div class="mt-3">
      <p class="text-[10px] uppercase tracking-wide font-semibold text-gray-500 mb-2">
        {{ \Dashed\DashedTranslations\Models\Translation::get(($progress['gap'] ?? 0) > 0 ? 'cart.suggestions.label_under_threshold' : 'cart.suggestions.label', 'cart', ($progress['gap'] ?? 0) > 0 ? 'Maak gratis verzending compleet' : 'Aanbevolen voor jou') }}
      </p>

      <div class="flex gap-2 overflow-x-auto pb-1">
        @foreach ($suggestions as $product)
          @php
            $group = $product->productGroup;
            $suggestionImage = $product->firstImage ?? $group?->firstImage;
            $displayName = $group && ! $group->showSingleProduct() ? $group->name : $product->name;
            $href = $group ? $group->getUrl() : ($product->getUrl() ?? '#');
          @endphp
          <a href="{{ $href }}" class="flex-shrink-0 w-24 bg-white border border-gray-200 rounded p-2 relative no-underline text-inherit hover:border-gray-400 transition-colors" wire:key="popup-suggestion-{{ $product->id }}">
            <div class="aspect-square bg-gray-100 rounded mb-1 relative overflow-hidden">
              @if ($suggestionImage)
                <x-dashed-files::image :mediaId="$suggestionImage" :alt="$displayName" class="w-full h-full object-cover" />
              @endif
              @if ($product->is_gap_closer ?? false)
                <span class="absolute top-0 right-0 bg-green-600 text-white text-[8px] font-bold px-1 rounded-bl">FREE</span>
              @endif
            </div>
            <div class="text-[11px] text-gray-800 leading-tight line-clamp-1">{{ $displayName }}</div>
            <div class="text-[11px] font-bold mt-1">
              @if ($group && ! $group->showSingleProduct())
                {{ $group->fromPrice() }}
              @else
                €{{ number_format((float) $product->current_price, 2, ',', '.') }}
              @endif
            </div>
          </a>
        @endforeach
      </div>
    </div>
  @endif
</div>
