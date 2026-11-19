@php
    $t = fn ($key, $default, $vars = null) => \Dashed\DashedTranslations\Models\Translation::get($key, 'product-reviews', $default, 'text', $vars);
@endphp
<div class="dpr dpr-reviews" id="reviews">
    @if($enabled && $stats && $stats['count'] > 0)
        @include('dashed-ecommerce-core::product-reviews.partials.styles')
        <h2 class="dpr-title">{{ $t('reviews-title', 'Reviews') }}</h2>
        <div class="dpr-summary">
            <div>
                <div class="dpr-average">{{ number_format((float) $stats['average'], 1, ',', '') }}</div>
                @include('dashed-ecommerce-core::livewire.frontend.product-reviews.stars', ['rating' => (float) $stats['average']])
                <div class="dpr-muted">{{ $t('reviews-count', 'Gebaseerd op :count: reviews', ['count' => $stats['count']]) }}</div>
            </div>
            <div class="dpr-bars">
                @foreach($stats['distribution'] as $stars => $amount)
                    <div class="dpr-bar">
                        <span>{{ $stars }} &#9733;</span>
                        <span class="dpr-bar-track"><span class="dpr-bar-fill" style="width: {{ $stats['count'] ? round($amount / $stats['count'] * 100) : 0 }}%"></span></span>
                        <span class="dpr-muted">{{ $amount }}</span>
                    </div>
                @endforeach
            </div>
        </div>
        <ul class="dpr-list">
            @foreach($reviews as $review)
                <li class="dpr-item" wire:key="dpr-review-{{ $review->id }}">
                    <div class="dpr-item-head">
                        @include('dashed-ecommerce-core::livewire.frontend.product-reviews.stars', ['rating' => $review->rating])
                        <strong>{{ $review->name }}</strong>
                        @if($review->verified_buyer)
                            <span class="dpr-verified">&#10003; {{ $t('reviews-verified', 'Geverifieerde koper') }}</span>
                        @endif
                        <span class="dpr-muted">{{ ($review->approved_at ?? $review->created_at)?->format('d-m-Y') }}</span>
                    </div>
                    @if($review->title)
                        <p class="dpr-item-title">{{ $review->title }}</p>
                    @endif
                    @if($review->body)
                        <p class="dpr-item-body">{{ $review->body }}</p>
                    @endif
                    @if($review->reply)
                        <div class="dpr-reply">
                            <div class="dpr-reply-label">{{ $t('reviews-reply', 'Reactie van de winkel') }}</div>
                            <div class="dpr-item-body">{{ $review->reply }}</div>
                        </div>
                    @endif
                </li>
            @endforeach
        </ul>
        @if($hasMore)
            <button type="button" class="dpr-button dpr-button-light" wire:click="loadMore">{{ $t('reviews-more', 'Meer reviews tonen') }}</button>
        @endif
    @endif
</div>
