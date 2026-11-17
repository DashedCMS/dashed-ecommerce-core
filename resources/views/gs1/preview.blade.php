@php
    $plan = $getState();
    $products = \Dashed\DashedEcommerceCore\Models\Product::withTrashed()
        ->whereIn('id', array_merge(array_column($plan->assignments, 'product_id'), $plan->placeholders, array_keys($plan->blocked)))
        ->get()
        ->keyBy('id');
    $excluded = \Dashed\DashedEcommerceCore\Models\Product::query()->gs1ExcludedByHand()->get()
        ->sortBy(fn ($product) => mb_strtolower((string) $product->name), SORT_NATURAL);
    $canEdit = auth()->user()?->can('update', $getRecord()) ?? false;
@endphp

<div class="space-y-4 text-sm">
    <p>
        {{ __(':toegewezen codes toegewezen, :plaatshouders plaatshouders, :geblokkeerd producten wachten op ontbrekende velden.', [
            'toegewezen' => count($plan->assignments),
            'plaatshouders' => count($plan->placeholders),
            'geblokkeerd' => count($plan->blocked),
        ]) }}
        @if ($canEdit)
            <span class="text-gray-500 dark:text-gray-400">{{ __('Moet een product geen code krijgen? Klik op Uitsluiten; dat wordt onthouden.') }}</span>
        @endif
    </p>

    @if (count($plan->assignments))
        <table class="w-full text-left">
            <thead>
                <tr>
                    <th class="py-1">{{ __('GTIN') }}</th>
                    <th class="py-1">{{ __('Product') }}</th>
                    <th class="py-1"></th>
                    <th class="py-1"></th>
                </tr>
            </thead>
            <tbody>
                @foreach ($plan->assignments as $assignment)
                    <tr wire:key="gs1-plan-{{ $assignment['product_id'] }}" class="border-t border-gray-200 dark:border-white/10">
                        <td class="py-1 font-mono">{{ $assignment['gtin'] }}</td>
                        <td class="py-1">{{ $products->get($assignment['product_id'])?->name }}</td>
                        <td class="py-1">
                            @if ($assignment['reused'])
                                <x-filament::badge color="warning">{{ __('Hergebruik, kan door GS1 geweigerd worden') }}</x-filament::badge>
                            @endif
                        </td>
                        <td class="py-1 text-end">
                            @if ($canEdit)
                                <x-filament::link tag="button" color="danger" size="sm" icon="heroicon-m-no-symbol"
                                    wire:click="mountAction('excludeProduct', @js(['product' => $assignment['product_id']]))">
                                    {{ __('Uitsluiten') }}
                                </x-filament::link>
                            @endif
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    @endif

    @if (count($plan->placeholders))
        <div>
            <p class="font-medium">{{ __('Krijgen een plaatshouder (er zijn niet genoeg vrije codes):') }}</p>
            <ul class="divide-y divide-gray-200 dark:divide-white/10">
                @foreach ($plan->placeholders as $productId)
                    <li wire:key="gs1-placeholder-{{ $productId }}" class="flex items-center justify-between gap-3 py-1">
                        <span>{{ $products->get($productId)?->name }}</span>
                        @if ($canEdit)
                            <x-filament::link tag="button" color="danger" size="sm" icon="heroicon-m-no-symbol"
                                wire:click="mountAction('excludeProduct', @js(['product' => $productId]))">
                                {{ __('Uitsluiten') }}
                            </x-filament::link>
                        @endif
                    </li>
                @endforeach
            </ul>
        </div>
    @endif

    @if (count($plan->blocked))
        <div>
            <p class="font-medium">{{ __('Krijgen geen code tot de velden zijn ingevuld:') }}</p>
            <ul class="divide-y divide-gray-200 dark:divide-white/10">
                @foreach ($plan->blocked as $productId => $fields)
                    <li wire:key="gs1-blocked-{{ $productId }}" class="flex items-center justify-between gap-3 py-1">
                        <span>
                            {{ $products->get($productId)?->name }}:
                            <span class="text-gray-500 dark:text-gray-400">{{ collect($fields)->map(fn ($field) => \Dashed\DashedEcommerceCore\Services\Gs1\Gs1MissingFields::label($field))->join(', ') }}</span>
                        </span>
                        @if ($canEdit)
                            <x-filament::link tag="button" color="danger" size="sm" icon="heroicon-m-no-symbol"
                                wire:click="mountAction('excludeProduct', @js(['product' => $productId]))">
                                {{ __('Uitsluiten') }}
                            </x-filament::link>
                        @endif
                    </li>
                @endforeach
            </ul>
        </div>
    @endif

    @if ($excluded->isNotEmpty())
        <details class="rounded-lg border border-gray-200 p-3 dark:border-white/10">
            <summary class="cursor-pointer font-medium">
                {{ trans_choice('{1} :aantal product uitgesloten van GS1|[0,*] :aantal producten uitgesloten van GS1', $excluded->count(), ['aantal' => $excluded->count()]) }}
            </summary>
            <ul class="mt-2 divide-y divide-gray-200 dark:divide-white/10">
                @foreach ($excluded as $product)
                    <li wire:key="gs1-excluded-{{ $product->id }}" class="flex items-center justify-between gap-3 py-1">
                        <span class="text-gray-500 dark:text-gray-400">{{ $product->name }}</span>
                        @if ($canEdit)
                            <x-filament::link tag="button" size="sm" icon="heroicon-m-arrow-uturn-left"
                                wire:click="mountAction('includeProduct', @js(['product' => $product->id]))">
                                {{ __('Weer meenemen') }}
                            </x-filament::link>
                        @endif
                    </li>
                @endforeach
            </ul>
        </details>
    @endif
</div>
