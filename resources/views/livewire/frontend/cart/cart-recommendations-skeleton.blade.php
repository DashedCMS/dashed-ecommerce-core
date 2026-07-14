<div class="cart-recommendations" aria-hidden="true">
    <div class="mt-6">
        {{-- Heading skeleton --}}
        <div class="mb-3 h-4 w-40 animate-pulse rounded bg-gray-200 dark:bg-white/10"></div>

        {{-- Product card skeletons matching the real grid: 2 cols on mobile, 3 on sm, 4 on lg --}}
        <div class="grid {{ $cols === 2 ? 'grid-cols-2' : 'grid-cols-2 sm:grid-cols-3 lg:grid-cols-4' }} gap-3">
            @for($i = 0; $i < $cols; $i++)
                <div class="rounded-lg border border-gray-200 p-2 dark:border-white/10">
                    {{-- Image placeholder (aspect-square mirrors the real <img>) --}}
                    <div class="mb-2 aspect-square w-full animate-pulse rounded bg-gray-200 dark:bg-white/10"></div>
                    {{-- Name placeholder (two lines, same as line-clamp-2) --}}
                    <div class="mb-1 h-3 w-full animate-pulse rounded bg-gray-200 dark:bg-white/10"></div>
                    <div class="h-3 w-2/3 animate-pulse rounded bg-gray-200 dark:bg-white/10"></div>
                    {{-- Price placeholder --}}
                    <div class="mt-1 h-3 w-1/2 animate-pulse rounded bg-gray-200 dark:bg-white/10"></div>
                </div>
            @endfor
        </div>
    </div>
</div>
