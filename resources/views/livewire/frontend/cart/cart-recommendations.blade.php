<div class="cart-recommendations" wire:key="cart-recommendations-{{ $view }}">
    @if($recommendations->isNotEmpty())
        <div class="mt-6">
            <h3 class="mb-3 text-sm font-semibold text-gray-900 dark:text-gray-100">
                {{ $heading ?? 'Aanbevolen voor jou' }}
            </h3>
            <div class="grid {{ $view === 'checkout' ? 'grid-cols-2' : 'grid-cols-2 sm:grid-cols-3 lg:grid-cols-4' }} gap-3">
                @foreach($recommendations as $product)
                    <a href="{{ method_exists($product, 'getUrl') ? $product->getUrl() : '#' }}"
                       class="group block rounded-lg border border-gray-200 p-2 hover:shadow dark:border-white/10"
                       wire:key="rec-{{ $product->id }}">
                        @if(method_exists($product, 'firstImageUrl') && $product->firstImageUrl())
                            <img src="{{ $product->firstImageUrl() }}"
                                 alt="{{ $product->name }}"
                                 class="mb-2 aspect-square w-full rounded object-cover"
                                 loading="lazy" />
                        @endif
                        <div class="text-xs font-medium text-gray-900 dark:text-gray-100 line-clamp-2">{{ $product->name }}</div>
                        @if(isset($product->current_price))
                            <div class="mt-1 text-xs text-gray-700 dark:text-gray-300">
                                € {{ number_format((float) $product->current_price, 2, ',', '.') }}
                            </div>
                        @endif
                    </a>
                @endforeach
            </div>
        </div>
    @endif
</div>
