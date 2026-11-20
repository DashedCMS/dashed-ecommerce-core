@php($orders = $this->getOrders())
<x-filament-widgets::widget>
    <x-dashed-core::aurora.card :title="__('Recente bestellingen')" icon="heroicon-o-shopping-bag" :flush="true">
        <x-slot name="actions">
            <a href="{{ \Dashed\DashedEcommerceCore\Filament\Resources\OrderResource::getUrl('index') }}" class="au-link" style="font-size:12px; font-weight:600;">
                {{ __('Alle bestellingen') }} <span aria-hidden="true">&rarr;</span>
            </a>
        </x-slot>

        @if ($orders === [])
            <p class="au-muted" style="margin:0; padding:16px 20px;">{{ __('Nog geen bestellingen.') }}</p>
        @else
            <table style="width:100%; border-collapse:collapse; font-size:13px;">
                <caption class="sr-only">{{ __('Recente bestellingen') }}</caption>
                <thead class="sr-only">
                    <tr>
                        <th scope="col">{{ __('Bestelling') }}</th>
                        <th scope="col">{{ __('Klant') }}</th>
                        <th scope="col">{{ __('Bedrag') }}</th>
                        <th scope="col">{{ __('Status') }}</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($orders as $order)
                        <tr class="au-row" style="border-top:1px solid var(--au-line, rgba(127,127,127,.18));">
                            <th scope="row" style="text-align:left; padding:12px 8px 12px 20px; font-weight:700; white-space:nowrap;">
                                <a href="{{ $order['url'] }}" class="au-link">{{ $order['number'] }}</a>
                            </th>
                            <td style="padding:12px 8px; overflow:hidden; text-overflow:ellipsis; max-width:220px; white-space:nowrap;">{{ $order['customer'] }}</td>
                            <td style="padding:12px 8px; text-align:right; white-space:nowrap; font-variant-numeric:tabular-nums;">{{ $order['total'] }}</td>
                            <td style="padding:12px 20px 12px 8px; white-space:nowrap;">
                                <x-dashed-core::aurora.badge :tone="$order['tone']">{{ $order['status'] }}</x-dashed-core::aurora.badge>
                                @if ($order['fulfillment'])
                                    <x-dashed-core::aurora.badge tone="neutral">{{ $order['fulfillment'] }}</x-dashed-core::aurora.badge>
                                @endif
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        @endif
    </x-dashed-core::aurora.card>
</x-filament-widgets::widget>
