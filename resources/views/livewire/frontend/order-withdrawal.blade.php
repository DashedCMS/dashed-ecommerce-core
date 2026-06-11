<div class="max-w-xl mx-auto">
    @if ($completed)
        <div class="rounded-lg border border-green-200 bg-green-50 p-6">
            <h2 class="text-lg font-semibold text-green-900">{{ __('Je koop is ongedaan gemaakt') }}</h2>
            <p class="mt-2 text-green-800">
                @if ($completedAt)
                    {{ __('We hebben je verzoek ontvangen voor bestelling :order op :datetime. Je ontvangt een bevestiging per e-mail.', [
                        'order' => $completedOrderLabel,
                        'datetime' => $completedAt,
                    ]) }}
                @else
                    {{ __('We hebben je verzoek ontvangen voor bestelling :order. Je ontvangt een bevestiging per e-mail.', [
                        'order' => $completedOrderLabel,
                    ]) }}
                @endif
            </p>
        </div>
    @elseif ($step === 1)
        @if (($blockData['title'] ?? null))
            <h1 class="text-2xl font-bold">{{ $blockData['title'] }}</h1>
        @endif
        @if (($blockData['intro'] ?? null))
            <div class="mt-2 text-gray-600">{!! $blockData['intro'] !!}</div>
        @endif

        <form wire:submit.prevent="search" class="mt-6 space-y-4">
            <div>
                <label class="block text-sm font-medium">{{ __('Bestelnummer') }}</label>
                <input type="text" wire:model="orderNumber" class="mt-1 w-full rounded border-gray-300" />
                @error('orderNumber') <span class="text-sm text-red-600">{{ $message }}</span> @enderror
            </div>
            <div>
                <label class="block text-sm font-medium">{{ __('E-mailadres') }}</label>
                <input type="email" wire:model="email" class="mt-1 w-full rounded border-gray-300" />
                @error('email') <span class="text-sm text-red-600">{{ $message }}</span> @enderror
            </div>

            @if ($notFound)
                <p class="text-sm text-red-600">{{ __('We konden geen bestelling vinden met deze gegevens.') }}</p>
            @endif
            @if ($rateLimitMessage)
                <p class="text-sm text-red-600">{{ $rateLimitMessage }}</p>
            @endif

            <button type="submit" class="rounded bg-black px-4 py-2 text-white">
                {{ __('Bestelling zoeken') }}
            </button>
        </form>
    @else
        <h2 class="text-lg font-semibold">{{ __('Koop ongedaan maken') }}</h2>
        @if ($this->order)
            <div class="mt-4 rounded border p-4">
                <p class="font-medium">{{ __('Bestelling') }}: {{ $this->order->invoice_id ?: $this->order->id }}</p>
                <p class="text-sm text-gray-600">{{ __('Besteldatum') }}: {{ $this->order->created_at?->format('d-m-Y') }}</p>
            </div>

            <button type="button" wire:click="selectAllLines" class="mt-3 text-sm underline">
                {{ __('Alles selecteren') }}
            </button>

            <form wire:submit.prevent="confirm" class="mt-4 space-y-4">
                @foreach ($this->order->orderProducts as $product)
                    <div class="rounded border p-3">
                        <label class="flex items-center gap-2 font-medium">
                            <input type="checkbox" wire:model.live="selectedLines.{{ $product->id }}.selected" />
                            {{ $product->name }}
                            <span class="text-sm text-gray-500">({{ __('besteld') }}: {{ $product->quantity }})</span>
                        </label>

                        @if (($selectedLines[$product->id]['selected'] ?? false))
                            <div class="mt-3 grid gap-3 sm:grid-cols-3">
                                <div>
                                    <label class="block text-sm">{{ __('Aantal') }}</label>
                                    <input type="number" min="1" max="{{ $product->quantity }}"
                                           wire:model="selectedLines.{{ $product->id }}.quantity"
                                           class="mt-1 w-full rounded border-gray-300" />
                                </div>
                                <div>
                                    <label class="block text-sm">{{ __('Reden') }}</label>
                                    <select wire:model="selectedLines.{{ $product->id }}.reason_id"
                                            class="mt-1 w-full rounded border-gray-300">
                                        <option value="">{{ __('Kies een reden') }}</option>
                                        @foreach ($this->reasons as $reason)
                                            <option value="{{ $reason->id }}">{{ $reason->getTranslation('label', app()->getLocale()) }}</option>
                                        @endforeach
                                    </select>
                                </div>
                                <div>
                                    <label class="block text-sm">{{ __('Toelichting') }}</label>
                                    <input type="text" wire:model="selectedLines.{{ $product->id }}.note"
                                           class="mt-1 w-full rounded border-gray-300" />
                                </div>
                            </div>
                        @endif
                    </div>
                @endforeach

                @error('lines') <p class="text-sm text-red-600">{{ $message }}</p> @enderror

                <button type="submit" class="rounded bg-red-600 px-4 py-2 text-white">
                    {{ __('Geselecteerde producten retourneren') }}
                </button>
            </form>
        @endif
    @endif
</div>
