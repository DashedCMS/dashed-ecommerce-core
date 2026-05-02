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
          @php
            $group = $product->productGroup;
            $suggestionImage = $product->firstImage ?? $group?->firstImage;
            $displayName = $group && ! $group->showSingleProduct() ? $group->name : $product->name;
            $href = $group ? $group->getUrl() : ($product->getUrl() ?? '#');
          @endphp
          <div class="flex-shrink-0 w-32 sm:w-40 relative" wire:key="suggestion-{{ $product->id }}">
            <a href="{{ $href }}" class="block bg-white border border-gray-200 rounded-md p-2 no-underline text-inherit hover:border-gray-400 transition-colors">
              <div class="aspect-square bg-gray-100 rounded mb-2 relative overflow-hidden">
                @if ($suggestionImage)
                  <x-dashed-files::image :mediaId="$suggestionImage" :alt="$displayName" class="w-full h-full object-cover" />
                @endif
                @if ($product->is_gap_closer ?? false)
                  <span class="absolute top-1 left-1 bg-green-600 text-white text-[10px] font-bold uppercase px-2 py-0.5 rounded-full">
                    {{ \Dashed\DashedTranslations\Models\Translation::get('cart.suggestions.gap_closer_badge', 'cart', 'Gratis verzending') }}
                  </span>
                @endif
              </div>
              <div class="text-xs text-gray-800 leading-tight mb-1 line-clamp-2">{{ $displayName }}</div>
              <div class="text-sm font-bold">
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
                    class="absolute top-2 right-2 bg-black text-white w-7 h-7 rounded-full inline-flex items-center justify-center text-base leading-none shadow-md hover:scale-110 transition-transform">+</button>
          </div>
        @endforeach
      </div>
    </div>
  @endif

  @if ($quickAddGroup && $quickAddProductId)
    <template x-teleport="body">
    <div class="fixed inset-0 z-[1000] flex items-center justify-center p-4" wire:key="cart-quick-add-modal-{{ $quickAddProductId }}">
      <div class="absolute inset-0 bg-black/50" wire:click="closeQuickAdd"></div>
      <div class="relative bg-white rounded-lg shadow-xl max-w-lg w-full max-h-[90vh] overflow-y-auto">
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
            <livewire:cart.add-to-cart :product="\Dashed\DashedEcommerceCore\Models\Product::find($quickAddProductId)" :key="'quick-add-'.$quickAddProductId" />
          </div>
        </div>
      </div>
    </div>
    </template>
  @endif
</div>
