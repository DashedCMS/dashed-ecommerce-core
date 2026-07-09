<div>
    @if ($this->shouldShow)
        @if ($submitted)
            <div>
                @if ($alreadySubscribed)
                    <p>{{ Translation::get('back-in-stock-already', 'products', 'Je staat al op de lijst voor dit product.') }}</p>
                @else
                    <p>{{ Translation::get('back-in-stock-success', 'products', 'Gelukt! Je krijgt bericht zodra dit product weer op voorraad is.') }}</p>
                @endif
            </div>
        @else
            <div>
                <p>{{ Translation::get('back-in-stock-title', 'products', 'Uitverkocht') }}</p>

                @if ($product->expectedInStockDate())
                    <p>{{ str_replace(':date:', $product->expectedInStockDate(), Translation::get('back-in-stock-expected', 'products', 'Verwacht terug op :date:')) }}</p>
                @endif

                <form wire:submit="submit">
                    <input type="email" wire:model="email"
                        placeholder="{{ Translation::get('back-in-stock-email-placeholder', 'products', 'Jouw e-mailadres') }}" />
                    @error('email')
                        <span>{{ $message }}</span>
                    @enderror
                    <button type="submit">
                        {{ Translation::get('back-in-stock-button', 'products', 'Houd mij op de hoogte') }}
                    </button>
                </form>
            </div>
        @endif
    @endif
</div>
