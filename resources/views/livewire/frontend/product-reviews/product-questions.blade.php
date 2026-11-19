@php
    $t = fn ($key, $default, $vars = null) => \Dashed\DashedTranslations\Models\Translation::get($key, 'product-reviews', $default, 'text', $vars);
@endphp
<div class="dpr dpr-questions" id="questions">
    @if($enabled)
        @include('dashed-ecommerce-core::product-reviews.partials.styles')
        <h2 class="dpr-title">{{ $t('questions-title', 'Vragen over dit product') }}</h2>
        @if($questions->isNotEmpty())
            <ul class="dpr-list">
                @foreach($questions as $item)
                    <li class="dpr-item" wire:key="dpr-question-{{ $item->id }}">
                        <p class="dpr-qa-q">{{ $item->question }}</p>
                        <p class="dpr-muted">{{ $item->name }}, {{ $item->created_at?->format('d-m-Y') }}</p>
                        <p class="dpr-qa-a">{{ $item->answer }}</p>
                    </li>
                @endforeach
            </ul>
            @if($hasMore)
                <button type="button" class="dpr-button dpr-button-light" wire:click="loadMore">{{ $t('questions-more', 'Meer vragen tonen') }}</button>
            @endif
        @endif

        @if($submitted)
            <p class="dpr-notice">{{ $t('questions-thanks', 'Bedankt voor je vraag. We mailen je het antwoord zodra we het weten.') }}</p>
        @else
            <form class="dpr-form" wire:submit="submit">
                <div class="dpr-field">
                    <label for="dpr-q-question-{{ $productId }}">{{ $t('questions-label-question', 'Je vraag') }}</label>
                    <textarea id="dpr-q-question-{{ $productId }}" wire:model="question" required maxlength="2000"></textarea>
                    @error('question') <div class="dpr-error">{{ $message }}</div> @enderror
                </div>
                <div class="dpr-field">
                    <label for="dpr-q-name-{{ $productId }}">{{ $t('questions-label-name', 'Je naam') }}</label>
                    <input id="dpr-q-name-{{ $productId }}" type="text" wire:model="name" required maxlength="80" autocomplete="name">
                    @error('name') <div class="dpr-error">{{ $message }}</div> @enderror
                </div>
                <div class="dpr-field">
                    <label for="dpr-q-email-{{ $productId }}">{{ $t('questions-label-email', 'Je e-mailadres (niet zichtbaar, voor het antwoord)') }}</label>
                    <input id="dpr-q-email-{{ $productId }}" type="email" wire:model="email" required maxlength="191" autocomplete="email">
                    @error('email') <div class="dpr-error">{{ $message }}</div> @enderror
                </div>
                <div class="dpr-hp" aria-hidden="true">
                    <label for="dpr-q-website-{{ $productId }}">Website</label>
                    <input id="dpr-q-website-{{ $productId }}" type="text" wire:model="website" tabindex="-1" autocomplete="off">
                </div>
                @if($retryAfter)
                    <div class="dpr-error">{{ $t('questions-too-many', 'Je hebt net al een vraag gesteld. Probeer het over :seconds: seconden opnieuw.', ['seconds' => $retryAfter]) }}</div>
                @endif
                <div>
                    <button type="submit" class="dpr-button" wire:loading.attr="disabled">{{ $t('questions-submit', 'Vraag versturen') }}</button>
                </div>
            </form>
        @endif
    @endif
</div>
