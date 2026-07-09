<div>
    @if ($this->shouldShow)
        <div class="rounded-2xl border border-gray-200 bg-white p-5 shadow-sm dark:border-gray-700 dark:bg-gray-900">
            @if ($submitted)
                <div class="flex items-start gap-3">
                    <span class="mt-0.5 flex h-9 w-9 shrink-0 items-center justify-center rounded-full bg-green-100 text-green-600 dark:bg-green-900/40 dark:text-green-400">
                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" class="h-5 w-5">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M4.5 12.75l6 6 9-13.5" />
                        </svg>
                    </span>
                    <div>
                        <p class="font-semibold text-gray-900 dark:text-gray-100">
                            {{ Translation::get('back-in-stock-success-title', 'products', 'Bedankt!') }}
                        </p>
                        <p class="mt-1 text-sm text-gray-600 dark:text-gray-400">
                            @if ($alreadySubscribed)
                                {{ Translation::get('back-in-stock-already', 'products', 'Je staat al op de lijst voor dit product.') }}
                            @else
                                {{ Translation::get('back-in-stock-success', 'products', 'Gelukt! Je krijgt bericht zodra dit product weer op voorraad is.') }}
                            @endif
                        </p>
                    </div>
                </div>
            @else
                <div class="flex items-center gap-2">
                    <span class="inline-flex items-center rounded-full bg-gray-100 px-2.5 py-0.5 text-xs font-medium text-gray-600 dark:bg-gray-800 dark:text-gray-300">
                        {{ Translation::get('back-in-stock-title', 'products', 'Uitverkocht') }}
                    </span>
                </div>

                <p class="mt-3 text-sm text-gray-600 dark:text-gray-400">
                    {{ Translation::get('back-in-stock-intro', 'products', 'Dit product is tijdelijk uitverkocht. Laat je e-mailadres achter en we sturen je een bericht zodra het weer op voorraad is.') }}
                </p>

                @if ($product->expectedInStockDate())
                    <p class="mt-2 text-sm font-medium text-gray-900 dark:text-gray-100">
                        {{ str_replace(':date:', $product->expectedInStockDate(), Translation::get('back-in-stock-expected', 'products', 'Verwacht terug op :date:')) }}
                    </p>
                @endif

                <form wire:submit="submit" class="mt-4 space-y-3">
                    <div>
                        <input type="email" wire:model="email" autocomplete="email"
                            placeholder="{{ Translation::get('back-in-stock-email-placeholder', 'products', 'Jouw e-mailadres') }}"
                            class="w-full rounded-lg border border-gray-300 bg-white px-3.5 py-2.5 text-sm text-gray-900 placeholder-gray-400 focus:border-primary-500 focus:outline-none focus:ring-2 focus:ring-primary-500/30 dark:border-gray-600 dark:bg-gray-800 dark:text-gray-100" />
                        @error('email')
                            <span class="mt-1 block text-xs text-red-600 dark:text-red-400">{{ $message }}</span>
                        @enderror
                    </div>

                    <button type="submit" wire:loading.attr="disabled" wire:target="submit"
                        class="inline-flex w-full items-center justify-center gap-2 rounded-lg bg-primary-600 px-4 py-2.5 text-sm font-semibold text-white transition hover:bg-primary-700 focus:outline-none focus:ring-2 focus:ring-primary-500/40 disabled:cursor-not-allowed disabled:opacity-60">
                        <svg wire:loading wire:target="submit" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" class="h-4 w-4 animate-spin">
                            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"></path>
                        </svg>
                        <span>{{ Translation::get('back-in-stock-button', 'products', 'Houd mij op de hoogte') }}</span>
                    </button>
                </form>
            @endif
        </div>
    @endif
</div>
