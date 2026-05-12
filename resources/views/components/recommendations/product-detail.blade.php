@props([
    'product',
    'limit' => 4,
    'heading' => 'Misschien vind je dit ook leuk',
])

@php
    use Dashed\DashedCore\Models\Customsetting;
    use Dashed\DashedEcommerceCore\Services\Recommendations\Context\RecommendationContext;
    use Dashed\DashedEcommerceCore\Services\Recommendations\RecommendationPlacement;
    use Dashed\DashedEcommerceCore\Services\Recommendations\RecommendationService;

    $enabled = (string) Customsetting::get('recommendations_product_detail_enabled', null, '1');
    if ($enabled !== '1' || ! $product) {
        $products = collect();
    } else {
        $result = app(RecommendationService::class)->for(
            RecommendationContext::for(RecommendationPlacement::ProductDetail)
                ->withCurrentProducts([$product])
                ->withLimit((int) $limit)
                ->build()
        );
        $products = $result->products;
    }
@endphp

@if($products->isNotEmpty())
    <section class="mt-12">
        <div class="mb-4 flex items-center justify-between">
            <h2 class="text-lg font-semibold text-gray-900 dark:text-gray-100">{{ $heading }}</h2>
        </div>
        <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-4">
            @foreach($products as $rec)
                <a href="{{ method_exists($rec, 'getUrl') ? $rec->getUrl() : '#' }}" class="group block rounded-lg border border-gray-200 p-3 hover:shadow-md dark:border-white/10">
                    @if(method_exists($rec, 'firstImageUrl') && $rec->firstImageUrl())
                        <img src="{{ $rec->firstImageUrl() }}" alt="{{ $rec->name }}" class="mb-3 aspect-square w-full rounded object-cover" loading="lazy" />
                    @endif
                    <div class="text-sm font-medium text-gray-900 dark:text-gray-100 line-clamp-2">{{ $rec->name }}</div>
                    @if(isset($rec->current_price))
                        <div class="mt-1 text-sm text-gray-700 dark:text-gray-300">€ {{ number_format((float) $rec->current_price, 2, ',', '.') }}</div>
                    @endif
                </a>
            @endforeach
        </div>
    </section>
@endif
