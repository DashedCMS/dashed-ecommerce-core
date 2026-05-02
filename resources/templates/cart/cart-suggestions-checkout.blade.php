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
          @php
            $group = $product->productGroup;
            $suggestionImage = $product->firstImage ?? $group?->firstImage;
            $displayName = $group && ! $group->showSingleProduct() ? $group->name : $product->name;
            $href = $group ? $group->getUrl() : ($product->getUrl() ?? '#');
          @endphp
          <div class="flex-shrink-0 w-24 sm:w-28 relative" wire:key="checkout-suggestion-{{ $product->id }}">
            <a href="{{ $href }}" class="block bg-white border border-gray-200 rounded p-2 no-underline text-inherit hover:border-gray-400 transition-colors">
              <div class="aspect-square bg-gray-100 rounded mb-1 relative overflow-hidden">
                @if ($suggestionImage)
                  <x-dashed-files::image :mediaId="$suggestionImage" :alt="$displayName" class="w-full h-full object-cover" />
                @endif
                @if ($product->is_gap_closer ?? false)
                  <span class="absolute top-0.5 left-0.5 bg-green-600 text-white text-[9px] font-bold px-1.5 py-0.5 rounded-full uppercase">
                    {{ \Dashed\DashedTranslations\Models\Translation::get('cart.suggestions.gap_closer_badge_short', 'cart', 'Gratis verz.') }}
                  </span>
                @endif
              </div>
              <div class="text-[11px] text-gray-800 leading-tight mb-1 line-clamp-1">{{ $displayName }}</div>
              <div class="text-xs font-bold">
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

  @if ($quickAddGroup && $quickAddProductId)
    <template x-teleport="body">
    <div class="fixed inset-0 flex items-center justify-center p-4" style="z-index: 2147483647;" wire:key="checkout-quick-add-modal-{{ $quickAddProductId }}">
      <div class="absolute inset-0 bg-black/50" wire:click="closeQuickAdd"></div>
      <div x-data @click.stop class="relative bg-white rounded-lg shadow-xl max-w-lg w-full max-h-[90vh] overflow-y-auto">
        <button type="button" wire:click="closeQuickAdd" class="absolute top-3 right-3 text-gray-500 hover:text-gray-900 text-3xl leading-none z-10">&times;</button>
        <div class="p-6">
          <div class="flex gap-4 mb-4">
            @if ($quickAddGroup['image'])
              <div class="w-24 h-24 bg-gray-100 rounded overflow-hidden flex-shrink-0">
                <x-dashed-files::image :mediaId="$quickAddGroup['image']" :alt="$quickAddGroup['name']" class="w-full h-full object-cover" />
              </div>
            @endif
            <div class="flex-1 min-w-0">
              <h3 class="font-bold text-gray-900 mb-1">{{ $quickAddGroup['name'] }}</h3>
              @if ($quickAddPriceFrom)
                <p class="text-sm text-gray-600">{{ $quickAddPriceFrom }}</p>
              @endif
              <a href="{{ $quickAddGroupUrl }}" class="text-xs text-gray-500 underline mt-1 inline-block">
                {{ \Dashed\DashedTranslations\Models\Translation::get('cart.suggestions.go_to_product', 'cart', 'Naar productpagina') }}
              </a>
            </div>
          </div>
          <div class="border-t border-gray-200 pt-4">
            <livewire:cart.add-to-cart :product="\Dashed\DashedEcommerceCore\Models\Product::find($quickAddProductId)" :key="'quick-add-checkout-'.$quickAddProductId" />
          </div>
        </div>
      </div>
    </div>
    </template>
  @endif
</div>
