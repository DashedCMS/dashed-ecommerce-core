@php
    $t = fn ($key, $default, $vars = null) => \Dashed\DashedTranslations\Models\Translation::get($key, 'product-reviews', $default, 'text', $vars);
@endphp
<div class="dpr dpr-card">
    @include('dashed-ecommerce-core::product-reviews.partials.styles')
    <h1 class="dpr-title">{{ $t('write-title', 'Schrijf een review') }}</h1>
    @if($line)
        <div class="dpr-product">
            @php
                $image = rescue(fn () => $line->product?->firstImage ? (mediaHelper()->getSingleMedia($line->product->firstImage, 'small')->url ?? null) : null, null, false);
            @endphp
            @if($image)
                <img src="{{ $image }}" alt="">
            @endif
            <strong>{{ $line->name }}</strong>
        </div>
    @endif

    @if($submitted)
        <p class="dpr-notice">{{ $t('write-thanks', 'Bedankt voor je review! We plaatsen hem zodra we hem bekeken hebben.') }}</p>
    @elseif($alreadyReviewed)
        <p class="dpr-notice">{{ $t('write-already', 'Je hebt dit product al beoordeeld. Bedankt!') }}</p>
    @else
        <form class="dpr-form" wire:submit="submit">
            <div class="dpr-field">
                <label>{{ $t('write-label-rating', 'Je beoordeling') }}</label>
                <div class="dpr-rate" role="radiogroup">
                    @for($i = 1; $i <= 5; $i++)
                        <button type="button" role="radio" aria-checked="{{ $rating === $i ? 'true' : 'false' }}" aria-label="{{ $i }} / 5" class="{{ $i <= $rating ? 'dpr-on' : '' }}" wire:click="setRating({{ $i }})">&#9733;</button>
                    @endfor
                </div>
                @error('rating') <div class="dpr-error">{{ $message }}</div> @enderror
            </div>
            <div class="dpr-field">
                <label for="dpr-r-title">{{ $t('write-label-title', 'Titel (optioneel)') }}</label>
                <input id="dpr-r-title" type="text" wire:model="title" maxlength="120">
                @error('title') <div class="dpr-error">{{ $message }}</div> @enderror
            </div>
            <div class="dpr-field">
                <label for="dpr-r-body">{{ $t('write-label-body', 'Je ervaring') }}</label>
                <textarea id="dpr-r-body" wire:model="body" required maxlength="5000"></textarea>
                @error('body') <div class="dpr-error">{{ $message }}</div> @enderror
            </div>
            <div class="dpr-field">
                <label for="dpr-r-name">{{ $t('write-label-name', 'Naam zoals hij bij de review staat') }}</label>
                <input id="dpr-r-name" type="text" wire:model="name" required maxlength="80">
                @error('name') <div class="dpr-error">{{ $message }}</div> @enderror
            </div>
            <div>
                <button type="submit" class="dpr-button" wire:loading.attr="disabled">{{ $t('write-submit', 'Review plaatsen') }}</button>
            </div>
        </form>
    @endif
</div>
