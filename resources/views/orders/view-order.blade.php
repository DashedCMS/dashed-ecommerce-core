<x-filament::page>
    <div class="grid md:grid-cols-6 gap-4">
        <div class="md:col-span-4">
            <div class="grid gap-4">
                <div>
                    @livewire('order-view-statusses', ['order' => $record])
                </div>
                <div>
                    @livewire('order-order-products-list', ['order' => $record])
                </div>
                <div>
                    @livewire('order-shipping-information-list', ['order' => $record])
                </div>
                <div>
                    @livewire('order-payment-information-list', ['order' => $record])
                </div>
                <div>
                    @livewire('order-payments-list', ['order' => $record])
                </div>
            </div>
        </div>
        <div class="md:col-span-2 flex flex-col gap-2">
            @if(!$record->credit_for_order_id)
                @livewire('change-order-fulfillment-status', ['order' => $record])
                @if($record->status == 'pending' || $record->status == 'partially_paid' || $record->status == 'waiting_for_confirmation' || $record->status == 'cancelled')
                    @livewire('add-payment-to-order', ['order' => $record])
                @endif
                @livewire('cancel-order', ['order' => $record])
                @livewire('send-order-confirmation-to-email', ['order' => $record])
            @else
                @livewire('change-order-retour-status', ['order' => $record])
            @endif

            @foreach(ecommerce()->widgets('orders') as $widget)
                @if($widget['width'] == 'sidebar')
                    <livewire:is :component="$widget['name']" :order="$record"></livewire:is>
                @endif
            @endforeach
            @livewire('order-customer-information-block-list', ['order' => $record])
            @livewire('order-logs-list', ['order' => $record])
        </div>
    </div>
</x-filament::page>
