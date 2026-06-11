<div class="max-w-xl mx-auto">
    @if ($completed)
        <div class="rounded-lg border border-green-200 bg-green-50 p-6">
            <h2 class="text-lg font-semibold text-green-900">{{ __('Je koop is ongedaan gemaakt') }}</h2>
            <p class="mt-2 text-green-800">
                {{ __('We hebben je verzoek ontvangen voor bestelling :order op :datetime. Je ontvangt een bevestiging per e-mail.', [
                    'order' => $this->order?->invoice_id ?: $this->order?->id,
                    'datetime' => now()->format('d-m-Y H:i'),
                ]) }}
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
                <ul class="mt-2 text-sm text-gray-700">
                    @foreach ($this->order->orderProducts as $line)
                        <li>{{ $line->quantity ?? 1 }}x {{ $line->name ?? '' }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        <form wire:submit.prevent="confirm" class="mt-4 space-y-4">
            <div>
                <label class="block text-sm font-medium">{{ __('Reden (optioneel)') }}</label>
                <textarea wire:model="customerNote" rows="3" class="mt-1 w-full rounded border-gray-300"></textarea>
            </div>
            <button type="submit" class="rounded bg-red-600 px-4 py-2 text-white">
                {{ __('Koop definitief ongedaan maken') }}
            </button>
        </form>
    @endif
</div>
