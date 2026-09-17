@php
    $plan = $getState();
    $products = \Dashed\DashedEcommerceCore\Models\Product::withTrashed()
        ->whereIn('id', array_merge(array_column($plan->assignments, 'product_id'), $plan->placeholders, array_keys($plan->blocked)))
        ->get()
        ->keyBy('id');
@endphp

<div class="space-y-4 text-sm">
    <p>
        {{ __(':toegewezen codes toegewezen, :plaatshouders plaatshouders, :geblokkeerd producten wachten op ontbrekende velden.', [
            'toegewezen' => count($plan->assignments),
            'plaatshouders' => count($plan->placeholders),
            'geblokkeerd' => count($plan->blocked),
        ]) }}
    </p>

    @if (count($plan->assignments))
        <table class="w-full text-left">
            <thead>
                <tr>
                    <th class="py-1">{{ __('GTIN') }}</th>
                    <th class="py-1">{{ __('Product') }}</th>
                    <th class="py-1"></th>
                </tr>
            </thead>
            <tbody>
                @foreach ($plan->assignments as $assignment)
                    <tr class="border-t border-gray-200 dark:border-white/10">
                        <td class="py-1 font-mono">{{ $assignment['gtin'] }}</td>
                        <td class="py-1">{{ $products->get($assignment['product_id'])?->name }}</td>
                        <td class="py-1">
                            @if ($assignment['reused'])
                                <x-filament::badge color="warning">{{ __('Hergebruik, kan door GS1 geweigerd worden') }}</x-filament::badge>
                            @endif
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    @endif

    @if (count($plan->blocked))
        <div>
            <p class="font-medium">{{ __('Krijgen geen code tot de velden zijn ingevuld:') }}</p>
            <ul class="list-disc ps-5">
                @foreach ($plan->blocked as $productId => $fields)
                    <li>
                        {{ $products->get($productId)?->name }}:
                        {{ collect($fields)->map(fn ($field) => \Dashed\DashedEcommerceCore\Services\Gs1\Gs1MissingFields::label($field))->join(', ') }}
                    </li>
                @endforeach
            </ul>
        </div>
    @endif
</div>
