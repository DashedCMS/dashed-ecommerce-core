<div class="space-y-6">
    @foreach ($blocks as $block)
        <div x-data="{ copied: false }" class="space-y-2">
            <div class="flex items-center justify-between gap-4">
                <h3 class="text-sm font-semibold text-gray-950 dark:text-white">{{ $block['title'] }}</h3>

                @if ($block['json'])
                    <button
                        type="button"
                        class="text-sm font-medium text-primary-600 hover:underline dark:text-primary-400"
                        x-on:click="navigator.clipboard.writeText($refs.json.innerText); copied = true; setTimeout(() => copied = false, 2000)"
                    >
                        <span x-show="! copied">{{ $labels['copy'] }}</span>
                        <span x-show="copied" x-cloak>{{ $labels['copied'] }}</span>
                    </button>
                @endif
            </div>

            @if ($block['json'])
                <pre x-ref="json" class="max-h-96 overflow-auto rounded-lg bg-gray-50 p-4 text-xs text-gray-800 dark:bg-white/5 dark:text-gray-200">{{ $block['json'] }}</pre>
            @else
                <p class="text-sm text-gray-500 dark:text-gray-400">{{ $labels['empty'] }}</p>
            @endif
        </div>
    @endforeach
</div>
