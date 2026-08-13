<x-filament-panels::page>
    {{ $this->form }}

    <div class="mt-6 space-y-8">
        @if ($narrative)
            <div class="fi-section rounded-xl bg-white p-5 ring-1 ring-gray-950/5 dark:bg-gray-900 dark:ring-white/10">
                <h2 class="text-base font-semibold text-gray-950 dark:text-white">{{ __('Wat opvalt') }}</h2>
                <div class="mt-2 whitespace-pre-line text-sm text-gray-700 dark:text-gray-300">{{ $narrative }}</div>
            </div>
        @endif

        @if (count($signals))
            <div class="space-y-3">
                <h2 class="text-base font-semibold text-gray-950 dark:text-white">{{ __('Bevindingen') }}</h2>
                @foreach ($signals as $signal)
                    <div class="fi-section rounded-xl bg-white p-4 ring-1 ring-gray-950/5 dark:bg-gray-900 dark:ring-white/10">
                        <div class="flex items-start gap-3">
                            <span @class([
                                'mt-1 inline-block h-2 w-2 shrink-0 rounded-full',
                                'bg-danger-500' => $signal['severity'] === 'urgent',
                                'bg-warning-500' => $signal['severity'] === 'aandacht',
                                'bg-success-500' => $signal['severity'] === 'kans',
                            ])></span>
                            <div class="min-w-0">
                                <div class="text-sm font-medium text-gray-950 dark:text-white">{{ $signal['title'] }}</div>
                                <div class="mt-1 text-sm text-gray-500 dark:text-gray-400">{{ $signal['explanation'] }}</div>
                                @if (count($signal['numbers']))
                                    <div class="mt-2 flex flex-wrap gap-x-4 gap-y-1 text-xs text-gray-500 dark:text-gray-400">
                                        @foreach ($signal['numbers'] as $label => $value)
                                            <span>{{ $label }}: <strong class="text-gray-950 dark:text-white">{{ $value }}</strong></span>
                                        @endforeach
                                    </div>
                                @endif
                            </div>
                        </div>
                    </div>
                @endforeach
            </div>
        @endif

        @foreach ($sections as $group => $analyses)
            <div class="space-y-3">
                <h2 class="text-base font-semibold capitalize text-gray-950 dark:text-white">{{ $group }}</h2>
                @foreach ($analyses as $analysis)
                    <div class="fi-section rounded-xl bg-white p-5 ring-1 ring-gray-950/5 dark:bg-gray-900 dark:ring-white/10">
                        <h3 class="text-sm font-semibold text-gray-950 dark:text-white">{{ $analysis['label'] }}</h3>
                        <pre class="mt-2 overflow-x-auto text-xs text-gray-600 dark:text-gray-400">{{ json_encode($analysis['facts'], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) }}</pre>
                    </div>
                @endforeach
            </div>
        @endforeach

        @foreach ($failed as $key => $label)
            <div class="fi-section rounded-xl bg-white p-4 text-sm text-gray-500 ring-1 ring-gray-950/5 dark:bg-gray-900 dark:text-gray-400 dark:ring-white/10">
                {{ __(':analyse kon niet berekend worden.', ['analyse' => $label]) }}
            </div>
        @endforeach
    </div>
</x-filament-panels::page>
