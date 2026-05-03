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
      @endif
    </div>
  @endif

  @if ($quickAddGroup && $quickAddProductId)
    <template x-teleport="body">
      <div class="fixed inset-0 flex items-center justify-center p-4" style="z-index: 2147483647;" wire:key="popup-quick-add-modal-{{ $quickAddProductId }}">
        <div class="absolute inset-0 bg-black/50" wire:click="closeQuickAdd"></div>
        <div x-data @click.stop class="relative bg-white rounded-lg shadow-xl max-w-lg w-full max-h-[90vh] overflow-y-auto">
          <button type="button" wire:click="closeQuickAdd" class="absolute top-3 right-3 text-gray-500 hover:text-gray-900 text-3xl leading-none z-10">&times;</button>
          <div class="p-6">
            <livewire:cart.quick-add-product :product="\Dashed\DashedEcommerceCore\Models\Product::find($quickAddProductId)" :key="'quick-add-popup-group-'.$quickAddGroupId" />
          </div>
        </div>
      </div>
    </template>
  @endif
</div>
