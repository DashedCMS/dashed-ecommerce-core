@php use Dashed\DashedTranslations\Models\Translation; @endphp
<div class="max-w-xl mx-auto">
    @if ($completed)
        <div class="rounded-lg border border-green-200 bg-green-50 p-6">
            <h2 class="text-lg font-semibold text-green-900">{{ Translation::get('return-completed-title', 'returns', 'Je koop is ongedaan gemaakt') }}</h2>
            <p class="mt-2 text-green-800">
                @if ($completedAt)
                    {{ Translation::get('return-completed-with-date', 'returns', 'We hebben je verzoek ontvangen voor bestelling :order: op :datetime:. Je ontvangt een bevestiging per e-mail.', 'text', [
                        'order' => $completedOrderLabel,
                        'datetime' => $completedAt,
                    ]) }}
                @else
                    {{ Translation::get('return-completed', 'returns', 'We hebben je verzoek ontvangen voor bestelling :order:. Je ontvangt een bevestiging per e-mail.', 'text', [
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
                <label class="block text-sm font-medium">{{ Translation::get('return-order-number', 'returns', 'Bestelnummer') }}</label>
                <input type="text" wire:model="orderNumber" class="mt-1 w-full rounded border-gray-300" />
                @error('orderNumber') <span class="text-sm text-red-600">{{ $message }}</span> @enderror
            </div>
            <div>
                <label class="block text-sm font-medium">{{ Translation::get('return-email', 'returns', 'E-mailadres') }}</label>
                <input type="email" wire:model="email" class="mt-1 w-full rounded border-gray-300" />
                @error('email') <span class="text-sm text-red-600">{{ $message }}</span> @enderror
            </div>

            @if ($notFound)
                <p class="text-sm text-red-600">{{ Translation::get('return-not-found', 'returns', 'We konden geen bestelling vinden met deze gegevens.') }}</p>
            @endif
            @if ($rateLimitMessage)
                <p class="text-sm text-red-600">{{ $rateLimitMessage }}</p>
            @endif

            <button type="submit" wire:loading.attr="disabled" wire:target="search" class="button button--primary">
                {{ Translation::get('return-search', 'returns', 'Bestelling zoeken') }}
            </button>
        </form>
    @else
        <h2 class="text-lg font-semibold">{{ Translation::get('return-step2-title', 'returns', 'Koop ongedaan maken') }}</h2>
        @if ($this->order)
            <div class="mt-4 rounded border p-4">
                <p class="font-medium">{{ Translation::get('return-order', 'returns', 'Bestelling') }}: {{ $this->order->invoice_id ?: $this->order->id }}</p>
                <p class="text-sm text-gray-600">{{ Translation::get('return-order-date', 'returns', 'Besteldatum') }}: {{ $this->order->created_at?->format('d-m-Y') }}</p>
            </div>

            <button type="button" wire:click="selectAllLines" class="mt-3 text-sm underline">
                {{ Translation::get('return-select-all', 'returns', 'Alles selecteren') }}
            </button>

            <form wire:submit.prevent="confirm" class="mt-4 space-y-4">
                @foreach ($this->returnableProducts as $product)
                    <div class="rounded border p-3">
                        <label class="flex items-center gap-2 font-medium">
                            <input type="checkbox" wire:model.live="selectedLines.{{ $product->id }}.selected" />
                            @php($img = $product->product?->firstImage ?? null)
                            @if($img)<img src="{{ $img }}" alt="{{ $product->name }}" class="w-12 h-12 object-cover rounded" />@endif
                            {{ $product->name }}
                            <span class="text-sm text-gray-500">({{ Translation::get('return-ordered', 'returns', 'besteld') }}: {{ $product->quantity }})</span>
                        </label>

                        @if (($selectedLines[$product->id]['selected'] ?? false))
                            <div class="mt-3 grid gap-3 sm:grid-cols-3">
                                <div>
                                    <label class="block text-sm">{{ Translation::get('return-quantity', 'returns', 'Aantal') }}</label>
                                    <input type="number" min="1" max="{{ $product->quantity }}"
                                           wire:model="selectedLines.{{ $product->id }}.quantity"
                                           class="mt-1 w-full rounded border-gray-300" />
                                </div>
                                <div>
                                    <label class="block text-sm">{{ Translation::get('return-reason', 'returns', 'Reden') }}</label>
                                    <select wire:model="selectedLines.{{ $product->id }}.reason_id"
                                            class="mt-1 w-full rounded border-gray-300">
                                        <option value="">{{ Translation::get('return-choose-reason', 'returns', 'Kies een reden') }}</option>
                                        @foreach ($this->reasons as $reason)
                                            <option value="{{ $reason->id }}">{{ $reason->getTranslation('label', app()->getLocale()) }}</option>
                                        @endforeach
                                    </select>
                                </div>
                                <div>
                                    <label class="block text-sm">{{ Translation::get('return-note', 'returns', 'Toelichting') }}</label>
                                    <input type="text" wire:model="selectedLines.{{ $product->id }}.note"
                                           class="mt-1 w-full rounded border-gray-300" />
                                </div>
                            </div>
                        @endif
                    </div>
                @endforeach

                @error('lines') <p class="text-sm text-red-600">{{ $message }}</p> @enderror

                <button type="submit" wire:loading.attr="disabled" wire:target="confirm" class="button button--primary">
                    {{ Translation::get('return-submit', 'returns', 'Geselecteerde producten retourneren') }}
                </button>
            </form>
        @endif
    @endif
</div>
