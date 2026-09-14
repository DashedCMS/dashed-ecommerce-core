@php($returns = $record->orderReturns()->with('creditOrder')->orderByDesc('id')->get())
@if($returns->isNotEmpty())
    <x-filament::section>
        <x-slot name="heading">{{ __('Retouren') }}</x-slot>
        <ul class="space-y-1 text-sm">
            @foreach($returns as $return)
                <li class="flex flex-wrap items-center gap-2">
                    <a class="underline" href="{{ route('filament.dashed.resources.order-returns.view', [$return]) }}">{{ __('Retour #:id', ['id' => $return->id]) }}</a>
                    <x-filament::badge :color="match ($return->status) { 'requested' => 'warning', 'approved', 'handled' => 'success', 'rejected' => 'danger', default => 'gray' }">{{ $return->statusLabel() }}</x-filament::badge>
                    @if($return->creditOrder)
                        <a class="underline" href="{{ route('filament.dashed.resources.orders.view', [$return->creditOrder]) }}">{{ __('Creditorder :nummer', ['nummer' => $return->creditOrder->invoice_id]) }}</a>
                        <span>{{ \Dashed\DashedEcommerceCore\Classes\CurrencyHelper::formatPrice($return->creditedAmount()) }}</span>
                        <span>{{ $return->isRefunded() ? __('terugbetaald') : __('nog niet terugbetaald') }}</span>
                    @endif
                </li>
            @endforeach
        </ul>
    </x-filament::section>
@endif
