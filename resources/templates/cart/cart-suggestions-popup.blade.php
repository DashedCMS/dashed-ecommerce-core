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
          <div class="flex-shrink-0 w-24 relative" wire:key="popup-suggestion-{{ $product->id }}">
            <a href="{{ $href }}" class="block bg-white border border-gray-200 rounded p-2 no-underline text-inherit hover:border-gray-400 transition-colors">
              <div class="aspect-square bg-gray-100 rounded mb-1 relative overflow-hidden">
                @if ($suggestionImage)
                  <x-dashed-files::image :mediaId="$suggestionImage" :alt="$displayName" class="w-full h-full object-cover" />
                @endif
                @if ($product->is_gap_closer ?? false)
                  <span class="absolute top-0 left-0 bg-green-600 text-white text-[8px] font-bold px-1 rounded-br uppercase">
                    {{ \Dashed\DashedTranslations\Models\Translation::get('cart.suggestions.gap_closer_badge_short', 'cart', 'Gratis verz.') }}
                  </span>
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
            <button type="button"
                    wire:click.stop="openQuickAdd({{ $product->id }})"
                    wire:loading.attr="disabled"
                    class="absolute top-1 right-1 bg-black text-white w-6 h-6 rounded-full inline-flex items-center justify-center text-sm leading-none shadow-md">+</button>
          </div>
        @endforeach
      </div>
    </div>
  @endif

  @if ($quickAddGroup && ! empty($quickAddVariants))
    <div class="fixed inset-0 z-[200] flex items-center justify-center p-4" wire:key="popup-quick-add-modal">
      <div class="absolute inset-0 bg-black/40" wire:click="closeQuickAdd"></div>
      <div class="relative bg-white rounded-lg shadow-xl max-w-md w-full max-h-[80vh] overflow-y-auto">
        <div class="flex items-center justify-between p-4 border-b border-gray-200">
          <p class="font-semibold text-gray-900">
            {{ \Dashed\DashedTranslations\Models\Translation::get('cart.suggestions.quick_add_title', 'cart', 'Kies een variant') }}: {{ $quickAddGroup['name'] }}
          </p>
          <button type="button" wire:click="closeQuickAdd" class="text-gray-500 hover:text-gray-900 text-2xl leading-none">&times;</button>
        </div>
        <div class="p-4 grid grid-cols-2 gap-3">
          @foreach ($quickAddVariants as $variant)
            <div class="border border-gray-200 rounded-md p-2 hover:border-black transition-colors">
              <div class="aspect-square bg-gray-100 rounded mb-2 overflow-hidden">
                @if ($variant['image'])
                  <x-dashed-files::image :mediaId="$variant['image']" :alt="$variant['name']" class="w-full h-full object-cover" />
                @endif
              </div>
              <div class="text-xs text-gray-800 leading-tight mb-1 line-clamp-2">{{ $variant['name'] }}</div>
              @if (! empty($variant['filters']))
                <div class="text-[10px] text-gray-500 mb-1">
                  @foreach ($variant['filters'] as $f)
                    <span>{{ $f['name'] }}: <strong>{{ $f['value'] }}</strong></span>
                    @if (! $loop->last) <span>·</span> @endif
                  @endforeach
                </div>
              @endif
              <div class="flex items-center justify-between mt-2">
                <span class="text-sm font-bold">{{ $variant['price'] }}</span>
                <button type="button" wire:click="addToCart({{ $variant['id'] }})" wire:loading.attr="disabled" class="bg-black text-white text-xs font-semibold px-3 py-1 rounded hover:bg-gray-800">
                  {{ \Dashed\DashedTranslations\Models\Translation::get('cart.suggestions.add', 'cart', 'Toevoegen') }}
                </button>
              </div>
            </div>
          @endforeach
        </div>
        @if ($quickAddTotalVariants > count($quickAddVariants))
          <div class="px-4 pb-4 text-center">
            <a href="{{ $quickAddGroupUrl }}" class="text-xs text-gray-600 underline">
              {{ \Dashed\DashedTranslations\Models\Translation::get('cart.suggestions.show_all_variants', 'cart', 'Bekijk alle :count: varianten', 'text', ['count' => $quickAddTotalVariants]) }}
            </a>
          </div>
        @endif
      </div>
    </div>
  @endif
</div>
